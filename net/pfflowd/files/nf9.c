#include <sys/types.h>
#include <sys/ioctl.h>
#include <sys/time.h>
#include <sys/socket.h>
#include <net/if.h>
#include <net/bpf.h>
#include <net/pfvar.h>
#include <net/if_pfsync.h>
#include <net/route.h>
#include <arpa/inet.h>
#include <errno.h>
#include <pcap.h>
#include <pwd.h>
#include <grp.h>
#include <signal.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <syslog.h>
#include <time.h>
#include <unistd.h>
#if defined(__FreeBSD__)
# include "pidfile.h"
#else
# include <util.h>
#endif
#include <netdb.h>

#include <netinet/in.h>

#include "pfflowd.h"
#include "nf9.h"

/*
 * Ingress Record for Internet Protocol version 4 (IPv4)
 */

u_int16_t NF9_IPV4_INGRESS[] =
{
   TID(AF_INET,NF9_IN), NF9_FIELD_COUNT,
     NF9_IN_BYTES, NF9_COUNTER_SIZE,
     NF9_IN_PKTS, NF9_COUNTER_SIZE,
     NF9_FIRST_SWITCHED,4,
     NF9_LAST_SWITCHED,4,
     NF9_IPV4_SRC_ADDR, 4,
     NF9_IPV4_DST_ADDR,4,
     NF9_L4_SRC_PORT, 2,
     NF9_L4_DST_PORT ,2,
     NF9_INPUT_SNMP, 2,
     NF9_OUTPUT_SNMP, 2,
     NF9_PROTOCOL, 1,
     NF9_DIRECTION,1
};

/*
 *  Egress Record for Internet Protocol version 4 (IPv4)
 */

u_int16_t NF9_IPV4_EGRESS[] =
{

   TID(AF_INET,NF9_OUT), NF9_FIELD_COUNT,
     NF9_OUT_BYTES, NF9_COUNTER_SIZE,
     NF9_OUT_PKTS, NF9_COUNTER_SIZE,
     NF9_FIRST_SWITCHED,4,
     NF9_LAST_SWITCHED,4,
     NF9_IPV4_SRC_ADDR, 4,
     NF9_IPV4_DST_ADDR,4,
     NF9_L4_SRC_PORT, 2,
     NF9_L4_DST_PORT ,2,
     NF9_INPUT_SNMP, 2,
     NF9_OUTPUT_SNMP, 2,
     NF9_PROTOCOL, 1,
     NF9_DIRECTION,1
}
;

/*
 * Ingress Record  for Internet Protocol version 6 (IPv6)
 */

u_int16_t NF9_IPV6_INGRESS[] =
{
   TID(AF_INET6,NF9_IN), NF9_FIELD_COUNT,
     NF9_IN_BYTES, NF9_COUNTER_SIZE,
     NF9_IN_PKTS, NF9_COUNTER_SIZE,
     NF9_FIRST_SWITCHED, 4,
     NF9_LAST_SWITCHED,4,
     NF9_IPV6_SRC_ADDR, 16,
     NF9_IPV6_DST_ADDR, 16,
     NF9_L4_SRC_PORT, 2,
     NF9_L4_DST_PORT ,2,
     NF9_INPUT_SNMP, 2,
     NF9_OUTPUT_SNMP, 2,
     NF9_PROTOCOL, 1,
     NF9_DIRECTION,1
};

/*
 *  Egress Record for Internet Protocol version 6 (IPv6)
 */

u_int16_t NF9_IPV6_EGRESS[] =
{

   TID(AF_INET6,NF9_OUT), NF9_FIELD_COUNT,
     NF9_OUT_BYTES, NF9_COUNTER_SIZE,
     NF9_OUT_PKTS, NF9_COUNTER_SIZE,
     NF9_FIRST_SWITCHED,4,
     NF9_LAST_SWITCHED,4,
     NF9_IPV6_SRC_ADDR, 16,
     NF9_IPV6_DST_ADDR,16,
     NF9_L4_SRC_PORT, 2,
     NF9_L4_DST_PORT ,2,
     NF9_INPUT_SNMP, 2,
     NF9_OUTPUT_SNMP, 2,
     NF9_PROTOCOL, 1,
     NF9_DIRECTION,1
}
;

static char rt_buf[RTBUFLEN];

static int rt_sequence = 0;

static u_int32_t export_sequence =0;

static u_int8_t packet[NF9_MAX_PACKET_SIZE];

static struct timeval last_refreshed;

static int sprint_time(char* , int, int);

static u_int64_t
  ntoh64(u_int64_t val)
{
   int i;
   u_int64_t result;

   u_int8_t *src = (u_int8_t *)&val;
   u_int8_t *dst = (u_int8_t *)&result;

   for(i =0 ; i < sizeof(u_int64_t) ; i++)
     {
	dst[sizeof(u_int64_t) - 1 - i] = src[i];
     }
   return result;
}

int print_ipv6(struct NF9_IPV6_DATA *data)
{

   char src_ip[INET6_ADDRSTRLEN];
   char   dst_ip[INET6_ADDRSTRLEN];

   inet_ntop(AF_INET,&data->src_ip,src_ip, sizeof(src_ip));
   inet_ntop(AF_INET,&data->dst_ip,dst_ip, sizeof(dst_ip));

   syslog(LOG_DEBUG,"<RECORD>");

   syslog(LOG_DEBUG,"FLOW %s:%d -> %s:%d",src_ip,ntohs(data->src_port)
	  ,dst_ip,ntohs(data->dst_port));

   syslog(LOG_DEBUG,"DIRECTION %d IP 6 PROTOCOL %d IN %d OUT %d",
	  data->direction, data->protocol, ntohs(data->src_index), ntohs(data->dst_index) );

#ifdef NF9_ULL
   syslog(LOG_DEBUG,"BYTES %lu PACKETS %lu", ntoh64(data->octets) ,ntoh64(data->packets) );
#else
   syslog(LOG_DEBUG,"BYTES %d PACKETS %d", ntohl(data->octets) ,ntohl(data->packets) );
#endif /*NF9_UL*/

   syslog(LOG_DEBUG,"UPTIME START %d msecs FINISH %d msecs", ntohl(data->flow_start),ntohl(data->flow_finish));

   return NF9_IPV6_DATA_SIZE;
}

int print_ipv4(struct NF9_IPV4_DATA *data)
{
   u_int64_t i;

   char  src_ip[INET_ADDRSTRLEN];
   char  dst_ip[INET_ADDRSTRLEN];

   inet_ntop(AF_INET,&data->src_ip,src_ip, sizeof(src_ip));

   inet_ntop(AF_INET,&data->dst_ip,dst_ip, sizeof(dst_ip));

   syslog(LOG_DEBUG, "<RECORD>" );

   syslog(LOG_DEBUG,"FLOW %s:%d -> %s:%d",src_ip,ntohs(data->src_port)
	  ,dst_ip,ntohs(data->dst_port));

   syslog(LOG_DEBUG,"DIRECTION %d IP 4 PROTOCOL %d IN %d OUT %d",
	  data->direction, data->protocol, ntohs(data->src_index), ntohs(data->dst_index) );

#ifdef NF9_ULL
   
   syslog(LOG_DEBUG,"BYTES %lu PACKETS %lu", ntoh64(data->octets) ,ntoh64(data->packets) );
#else
   syslog(LOG_DEBUG,"BYTES %d PACKETS %d", ntohl(data->octets) ,ntohl(data->packets) );
#endif /*NF9_UL*/

   syslog(LOG_DEBUG,"UPTIME START %d msecs FINISH %d msecs", ntohl(data->flow_start),ntohl(data->flow_finish));

   return  NF9_IPV4_DATA_SIZE;
}

int print_template( u_int16_t tpl[] )
{
   int i;
   int count = ntohs(tpl[1]);

   syslog(LOG_DEBUG, "TID %hu COUNT %d", ntohs(tpl[0]) , count );

   for( i =1 ; i <= count ; i++)
     {
	syslog(LOG_DEBUG, "TYPE %hu LEN %hu", ntohs(tpl[2*i]) ,ntohs(tpl[2*i +1]) );

     }

   return   (count + 1) * 2 *sizeof(u_int16_t);
}

int print_packet(u_int8_t packet[], int verbose_flag)
{
   char buf[64];
   time_t time_tt;
   int count =0;
   int length =0;
   int id;
   int offset =0;
   int bite;

   struct NF9_FLOWSET_HEADER *fst = NULL;
   struct NF9_IPV4_DATA *ipv4 =NULL;
   struct NF9_IPV6_DATA *ipv6 =NULL;
   struct NF9_PACKET_HEADER* hdr = NULL;

   u_int16_t* tpl= NULL;

   hdr = (struct NF9_PACKET_HEADER* )packet;

   if(hdr)
     {
	count = ntohs(hdr->count);

	syslog(LOG_DEBUG, "<PACKET> VER %hu COUNT %d", ntohs(hdr->version), count);

	syslog(LOG_DEBUG, "ID %u UPTIME %u msecs",ntohl(hdr->source_id), ntohl(hdr->uptime_ms));

        sprint_time(buf, sizeof(buf),ntohl(hdr->time_sec));

	syslog(LOG_DEBUG, "EXPORT TIME %s SEQ %u", buf, ntohl(hdr->export_sequence));

	offset = NF9_PACKET_HEADER_SIZE;

	while(count >0)
	  {

	     fst = (struct NF9_FLOWSET_HEADER *) &packet[offset];

	     id = ntohs(fst->id);
	     length =ntohs(fst->length);

	     syslog(LOG_DEBUG, "<FLOWSET> TID %d LEN %d", id, length);

	     length -= NF9_FLOWSET_HEADER_SIZE;
	     offset +=NF9_FLOWSET_HEADER_SIZE;

	     switch(id)
	       {

		case  NF9_TEMPLATE_FLOWSET_ID:
		    {

		       while(length>0)
			 {
			    bite= print_template( (u_int16_t*)&packet[offset]);

			    length-=bite;
			    offset+=bite;

			    count--;
			 }

		    }

		  break;

		case TID(AF_INET,NF9_IN):
		case TID(AF_INET,NF9_OUT):
		    {

		       while(length >= NF9_IPV4_DATA_SIZE)
			 {

			    bite= print_ipv4( (struct NF9_IPV4_DATA *)&packet[offset]);

			    length-=bite;
			    offset+=bite;

			    count--;
			 }

		       offset+=length;
		    }

		  break;
		case TID(AF_INET6,NF9_IN):
		case TID(AF_INET6,NF9_OUT):
		    {

		       while(length>= NF9_IPV6_DATA_SIZE)
			 {

			    bite= print_ipv6( (struct NF9_IPV6_DATA *)&packet[offset]);

			    length-=bite;
			    offset+=bite;

			    count--;
			 }

		       offset+=length;

		    }

		  break;

		default:
		    {
		       syslog(LOG_DEBUG, "ERROR unknown TID");
		       return (-1);
		    }

		  break;
	       }

	  }

     }

   return 0;
}

static int
sprint_time(char* buf ,int n ,int sec)
{

   time_t in = sec;
   struct tm out;

   localtime_r(&in, &out);
   strftime(buf, n, "%Y-%m-%dT%H:%M:%S", &out);

   return (0);
}

static u_int32_t
  timeval_sub_ms(struct timeval *t1, struct timeval *t2)
{
   struct timeval res;

   res.tv_sec = t1->tv_sec - t2->tv_sec;
   res.tv_usec = t1->tv_usec - t2->tv_usec;
   if (res.tv_usec < 0)
     {

	res.tv_usec += 1000000L;
	res.tv_sec--;
     }
   return ((u_int32_t)res.tv_sec * 1000 + (u_int32_t)res.tv_usec / 1000);
}

static int
  copy_template(u_int8_t *m , u_int16_t tmpl[] , int sz)
{
   int i;
   int count = sz / sizeof(u_int16_t);
   u_int16_t* net =(u_int16_t*) m;

   for(i =0 ; i< count ; i++)
     {
	net[i] = htons(tmpl[i]);
     }
   return sz;
}

int resolve_interface(struct pf_addr *host , int af)
{
   int index = 0;

   int n, pid;

   struct rt_msghdr *hdr ;

   struct sockaddr_in * addr;
   struct sockaddr_in6 * addr6;

   int s = socket(PF_ROUTE, SOCK_RAW, 0); /* requires superuser privileges */

   if ( -1 != s )
     {

	hdr  = (struct rt_msghdr *) rt_buf;

	if ( AF_INET == af)
	  {
	     memset(rt_buf, 0, sizeof (struct rt_msghdr) + sizeof (struct sockaddr_in ));

	     hdr->rtm_msglen = sizeof(struct rt_msghdr) + sizeof(struct sockaddr_in);

	     addr  = (struct sockaddr_in *) ( hdr + 1);
	     addr->sin_len = sizeof(struct sockaddr_in);
	     addr->sin_family = AF_INET;
	     addr->sin_addr = host->v4;

	  }
	else /*if (AF_INET6 == af)*/
	  {
	     memset(rt_buf, 0, sizeof (struct rt_msghdr) + sizeof (struct sockaddr_in6 ));

	     hdr->rtm_msglen = sizeof(struct rt_msghdr) + sizeof(struct sockaddr_in6);

	     addr6 = (struct sockaddr_in6 *) (hdr + 1);
	     addr6->sin6_len = sizeof(struct sockaddr_in6);
	     addr6->sin6_family = AF_INET;
	     addr6->sin6_addr = host->v6;

	  }

	hdr->rtm_version = RTM_VERSION;
	hdr->rtm_type = RTM_GET;
	hdr->rtm_addrs = RTA_DST;
	hdr->rtm_pid = pid = getpid();
	hdr->rtm_seq = rt_sequence;

	if( -1 !=  write(s, hdr, hdr->rtm_msglen))
	  {
	     do
	       {
		  n = read(s, hdr, RTBUFLEN);
	       }
	     while ( (0 < n) && ( hdr->rtm_type != RTM_GET || hdr->rtm_seq != rt_sequence ||
				  hdr->rtm_pid != pid));

	     if(n>0 && !hdr->rtm_errno )
	       {
		  index = hdr->rtm_index;
	       }

	  }

	rt_sequence++;

	close(s);
     }

   return (index);
}

int
  send_netflow_v9(const struct pfsync_state *st, u_int n, u_int *flows_exp
		  , int netflow_socket, int direction, struct timeval start_time, int verbose_flag
		  , int refresh_minutes_interval, int refresh_packets_interval, int source_id)
{

   time_t now;
   struct tm now_tm;
   char now_s[64];
   struct timeval now_tv;
   int i,offset, records ,padding ,err ;
   int k, d, start, dir ,src_idx ,dst_idx;
   socklen_t errsz  ;
   struct pf_state_host  src, dst;
   u_int32_t creation, uptime_ms;
   struct NF9_PACKET_HEADER *hdr = NULL;
   struct NF9_FLOWSET_HEADER *fst = NULL;
   struct NF9_IPV4_DATA *ipv4 =NULL;
   struct NF9_IPV6_DATA *ipv6 =NULL;
#if __FreeBSD_version > 900000
   const struct pfsync_state_key *sk, *nk;
#endif

   if (verbose_flag)
     {
	now = time(NULL);
	localtime_r(&now, &now_tm);
	strftime(now_s, sizeof(now_s), "%Y-%m-%dT%H:%M:%S", &now_tm);
     }

   for( records=offset = i = 0; i < n ; i++)
     {
#ifdef NF9_IPV6
	if( ( st[i].af == AF_INET ||st[i].af == AF_INET6   ) &&
#else
	    if( ( st[i].af == AF_INET ) &&
#endif
		( st[i].packets[0][0] || st[i].packets[0][1] || st[i].packets[1][0] || st[i].packets[1][1] )  &&
		(  direction ? (st[i].direction == direction) : 1  ))
	    {
	       /*Packet is full, send*/
	       if (hdr &&( NF9_MAX_PACKET_SIZE <  (offset  + ( fst ? fst->length : 0 ) + NF9_MAX_STATE_REQUIREMENT ) ))
		 {
		    if(fst)
		      {
		       /*Close open flowset*/
			 padding = ((fst->length + 3)/4)*4 - fst->length  ;

			 if(padding)
			   {
			     /*padding*/
			      memset(&packet[offset + fst->length],0,padding);
			      fst->length += padding;
			   }

			 offset += fst->length;

			 fst->id = htons(fst->id);
			 fst->length = htons( fst->length);

			 fst = NULL;
		      }

	          /*close packet*/

		    records +=hdr->count;

		    hdr->version = htons(NF9_VERSION);
		    hdr->count = htons(hdr->count);
		    hdr->uptime_ms = htonl(uptime_ms);
		    hdr->time_sec = htonl(now_tv.tv_sec);
		    hdr->export_sequence = htonl(export_sequence++);
		    hdr->source_id = htonl(source_id);

		    if (netflow_socket != -1 )
		      {
			 if (verbose_flag)
			   {
			      syslog(LOG_DEBUG,
				     "Sending flow packet len = %d", offset);
			   }

			 errsz = sizeof(err);
			 getsockopt(netflow_socket, SOL_SOCKET, SO_ERROR,
				    &err, &errsz); /* Clear ICMP errors */
			 if (send(netflow_socket, packet,
				  (size_t)offset, 0) == -1)
			   {
			      syslog(LOG_DEBUG, "send : %s", strerror(errno));
			      return -1;
			   }

		      }

		    print_packet(packet, verbose_flag);

		    hdr = NULL;
		    offset = 0;

		 }

	       /*Start new packet*/
	       if(!hdr)
		 {
		    hdr = (struct NF9_PACKET_HEADER *)packet;
		    offset = NF9_PACKET_HEADER_SIZE;

		    hdr->count =0;

		    gettimeofday(&now_tv, NULL);

		    if(0 == export_sequence)
		      {
			 last_refreshed = now_tv;
		      }

		    uptime_ms = timeval_sub_ms(&now_tv, &start_time);

		    if( ( ! (export_sequence % refresh_packets_interval))  ||
			( (now_tv.tv_sec -  last_refreshed.tv_sec)  >= refresh_minutes_interval *60))
		      {
			 last_refreshed = now_tv;

			 fst  = (struct NF9_FLOWSET_HEADER *)&packet[offset];

			 fst->id = NF9_TEMPLATE_FLOWSET_ID;

			 fst->length= NF9_FLOWSET_HEADER_SIZE;

			 fst->length+= copy_template(&packet[offset+ fst->length]
						     ,NF9_IPV4_INGRESS,sizeof(NF9_IPV4_INGRESS));
			 hdr->count++;
#ifdef NF9_EGRESS
			 fst->length+= copy_template(&packet[offset + fst->length]
						     ,NF9_IPV4_EGRESS,sizeof(NF9_IPV4_EGRESS));
			 hdr->count++;

#endif /*NF9_EGRESS*/

#ifdef NF9_IPV6
			 fst->length+=  copy_template(&packet[offset+ fst->length]
						      ,NF9_IPV6_INGRESS,sizeof( NF9_IPV6_INGRESS));
			 hdr->count++;
#endif /*NF9_IPV6*/

#if defined(NF9_EGRESS) && defined(NF9_IPV6)
			 fst->length+=  copy_template(&packet[offset + fst->length]
						      ,NF9_IPV6_EGRESS,sizeof(NF9_IPV6_INGRESS));
			 hdr->count++;

#endif /* defined(NF9_EGRESS) && defined(NF9_IPV6)*/

			 padding = ((fst->length + 3)/4)*4 - fst->length  ;

			 if(padding)
			   { /*padding*/
			      memset(&packet[offset + fst->length],0,padding);
			      fst->length += padding;
			   }

			 offset += fst->length;
			 fst->id = htons(fst->id);

			 fst->length = htons( fst->length);

			 fst = NULL;

		      }

		 }

	     /*add data records*/

#if __FreeBSD_version > 900000
		if (st[i].direction == PF_OUT)
		  {
		     sk = &st[i].key[PF_SK_STACK];
		     nk = &st[i].key[PF_SK_WIRE];
		  }
		else
		  {
		     sk = &st[i].key[PF_SK_WIRE];
		     nk = &st[i].key[PF_SK_STACK];
		  }
		src.addr = nk->addr[1];
		src.port = nk->port[1];
		dst.addr = nk->addr[0];
		dst.port = nk->port[0];
#else
	       /* 0 , src -> dst, 1 dst -> source , */
	       if (st[i].direction == PF_OUT)
		 {
		    memcpy(&src, &st[i].lan, sizeof(src));
		    memcpy(&dst, &st[i].ext, sizeof(dst));
		 }
	       else
		 {
		  /*src -> dst packet = ingress*/
		    memcpy(&src, &st[i].ext, sizeof(src));
		    memcpy(&dst, &st[i].lan, sizeof(dst));
		 }
#endif

	       src_idx = resolve_interface( &src.addr, st[i].af);
	       dst_idx = resolve_interface( &dst.addr, st[i].af);

	       /*creation = no. of  millisecs ago the state was created */
	       creation = ntohl(st[i].creation) * 1000;

	       if (creation > uptime_ms)
		 creation = uptime_ms;

               /* Tries to reuse flowset header (if any )
		* st[i].direction  == PF_IN  , st[i].packets[0] is ingress template , src -> dst
		* st[i].direction  == PF_IN  , st[i].packets[1] is egress template  , dst -> src
		* st[i].direction  == PF_OUT , st[i].packets[0] is egress template  , src -> dst
		* st[i].direction  == PF_OUT , st[i].packets[1] is ingress template , dst -> src
		*/
	       start =( (fst ? TID2DIR(fst->id) : NF9_IN ) == CONVDIR(st[i].direction)) ? 0 :1;

	       for( k=0 ; k <2 ; k++)
		 {
		    d = (start + k)% 2;

		    if(  st[i].packets[d][0] || st[i].packets[d][1] )
		      {

			 if(fst &&  TID(st[i].af, INDEXDIR(st[i].direction, d)) != fst->id )
			   {
			      /*Close open flowset*/
			      padding = ((fst->length + 3)/4)*4 - fst->length  ;

			      if(padding)
				{
				  /*padding*/
				   memset(&packet[offset + fst->length],0,padding);
				   fst->length += padding;
				}

			      offset += fst->length;

			      fst->id = htons(fst->id);
			      fst->length = htons( fst->length);

			      fst = NULL;

			   }

			 if(!fst)
			   {
			      fst = (struct NF9_FLOWSET_HEADER *)&packet[offset];
#ifdef NF9_EGRESS
			      fst->id =  TID(st[i].af, INDEXDIR(st[i].direction, d));
#else
			      fst->id =  TID(st[i].af, NF9_IN);
#endif /*NF9_EGRESS*/
			      fst->length = NF9_FLOWSET_HEADER_SIZE;
			   }

			 if(AF_INET == st[i].af )
			   {
			      ipv4 = (struct NF9_IPV4_DATA *)&packet[ offset + fst->length];

			      hdr->count++;
			      fst->length +=  NF9_IPV4_DATA_SIZE;

			      ipv4->direction = INDEXDIR(st[i].direction, d);

			      ipv4->flow_start = htonl(uptime_ms - creation);
			      ipv4->flow_finish =htonl(uptime_ms);

			      ipv4->protocol =st[i].proto;

#ifdef NF9_ULL
                              bcopy(st[i].bytes[d],&ipv4->octets, sizeof (ipv4->octets));
			      bcopy(st[i].packets[d],&ipv4->packets, sizeof( ipv4->packets));

#else
			      ipv4->octets = st[i].bytes[d][1];
			      ipv4->packets = st[i].packets[d][1];
#endif /* NF9_ULL*/

			      if(d)
				{
				   ipv4->src_ip =dst.addr.v4.s_addr;
				   ipv4->dst_ip =src.addr.v4.s_addr;
				   ipv4->src_port = dst.port;
				   ipv4->dst_port =src.port;
				   ipv4->src_index =htons(dst_idx);
				   ipv4->dst_index =htons(src_idx);
				}
			      else
				{
				   ipv4->src_ip =src.addr.v4.s_addr;
				   ipv4->dst_ip =dst.addr.v4.s_addr;
				   ipv4->src_port = src.port;
				   ipv4->dst_port =dst.port;
				   ipv4->src_index =htons(src_idx);
				   ipv4->dst_index =htons(dst_idx);

				}

			   }
#ifdef NF9_IPV6
			 else
			   {

			      ipv6 = (struct NF9_IPV6_DATA *)&packet[ offset + fst->length];

			      hdr->count++;
			      fst->length +=  NF9_IPV6_DATA_SIZE;

			      ipv6->direction = INDEXDIR(st[i].direction, d);

			      ipv6->flow_start = htonl(uptime_ms - creation);
			      ipv6->flow_finish =htonl(uptime_ms);

			      ipv6->protocol =st[i].proto;

# ifdef NF9_ULL
			      bcopy(st[i].bytes[d],&ipv4->octets, sizeof (ipv4->octets));
			      bcopy(st[i].packets[d],&ipv4->packets,  sizeof( ipv4->packets));
# else
			      ipv4->octets = st[i].bytes[d][1];
			      ipv4->packets = st[i].packets[d][1];
# endif /* NF9_ULL*/

			      if(d)
				{

				   bcopy(  &dst.addr.v6,&ipv6->src_ip, sizeof(ipv6->src_ip));
				   bcopy( &src.addr.v6,  &ipv6->dst_ip,sizeof(ipv6->dst_ip));

				   ipv6->src_port = dst.port;
				   ipv6->dst_port =src.port;
				   ipv6->src_index =htons(dst_idx);
				   ipv6->dst_index =htons(src_idx);
				}

			      else
				{
				   bcopy(  &src.addr.v6,&ipv6->src_ip,  sizeof(ipv6->src_ip));
				   bcopy( &dst.addr.v6, &ipv6->dst_ip,  sizeof(ipv6->dst_ip));

				   ipv6->src_port = src.port;
				   ipv6->dst_port =dst.port;
				   ipv6->src_index =htons(src_idx);
				   ipv6->dst_index =htons(dst_idx);

				}

			   }
#endif /*NF9_IPV6*/
		      }

		 }

	    }

	 }
   /*for loop*/

	if (hdr )
	  {

	     if(fst)
	       {

		         /*Close open flowset*/
		  padding = ((fst->length + 3)/4)*4 - fst->length  ;

		  if(padding)
		    {

		            /*padding*/
		       memset(&packet[offset + fst->length],0,padding);
		       fst->length += padding;
		    }

		  offset += fst->length;

		  fst->id = htons(fst->id);
		  fst->length = htons( fst->length);

		  fst = NULL;
	       }

	               /*close packet*/

	     records +=hdr->count;

	     hdr->version = htons(NF9_VERSION);
	     hdr->count = htons(hdr->count);
	     hdr->uptime_ms = htonl(uptime_ms);
	     hdr->time_sec = htonl(now_tv.tv_sec);
	     hdr->export_sequence = htonl(export_sequence++);
	     hdr->source_id = htonl(source_id);

	     if (netflow_socket != -1 )
	       {

		  if (verbose_flag)
		    {

		       syslog(LOG_DEBUG,
			      "Sending flow packet len = %d", offset);
		    }

		  errsz = sizeof(err);
		  getsockopt(netflow_socket, SOL_SOCKET, SO_ERROR,
			     &err, &errsz); /* Clear ICMP errors */
		  if (send(netflow_socket, packet,
			   (size_t)offset, 0) == -1)
		    {

		       syslog(LOG_DEBUG, "send: %s", strerror(errno));
		       return -1;
		    }

	       }

	     print_packet(packet, verbose_flag);

	     hdr = NULL;
	     offset = 0;

	  }

   /*close flowset and close packet*/

   /*Send*/
	return (records);
     }

