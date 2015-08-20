/*-
 * Copyright (c) 2008 Michael Telahun Makonnen <mtm@FreeBSD.Org>
 * Copyright (c) 2009 - 2010 Ermal Luçi <eri@pfsense.org>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED.  IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
 * OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
 * SUCH DAMAGE.
 *
 * $Id: classifyd.c 580 2008-08-02 12:48:12Z mtm $
 */

#include <sys/types.h>
#include <sys/socket.h>
#include <sys/ioctl.h>
#include <sys/event.h>
#include <sys/time.h>

#include <net/if.h>
#include <arpa/inet.h>
#include <netinet/in.h>
#include <netinet/in_systm.h>
#include <netinet/ip.h>
#include <netinet/tcp.h>
#include <netinet/udp.h>
#include <net/pfvar.h>

#include <assert.h>
#include <err.h>
#include <fcntl.h>
#include <libgen.h>
#include <libutil.h>
#include <limits.h>
#include <pthread.h>
#include <signal.h>
#include <stdarg.h>
#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <sysexits.h>
#include <syslog.h>
#include <unistd.h>
#include <time.h>
#include <errno.h>

#include "hashtable.h"
#include "hashtable_private.h"
#include "pathnames.h"
#include "protocols.h"

#define IC_DPORT	7777
#define IC_HASHSZ	4096
static int IC_PKTMAXMATCH = 5;
#define IC_PKTSTACKSZ	2048
#define IC_PKTSZ	1500
#define IC_QMAXSZ	256

#define DIVERT_ALTQ 0x1000
#define DIVERT_DNCOOKIE 0x2000
#define DIVERT_ACTION 0x4001
#define DIVERT_TAG 0x8000

/*
 * Internal representation of a packet.
 */
struct ic_pkt {
	STAILQ_ENTRY(ic_pkt) fp_link;
	struct sockaddr_in   fp_saddr;	/* divert(4) address/port of packet */
	socklen_t	     fp_salen;	/* size in bytes of fp_addr */
	size_t	 fp_pktlen;		/* size in bytes of packet */
	u_char	 fp_pkt[IC_PKTSZ];	/* raw packet from divert(4) */
};

STAILQ_HEAD(pkt_head, ic_pkt);

/*
 * Structure on which incomming/outgoing packets are queued.
 */
struct ic_queue {
	pthread_cond_t	fq_condvar;	/* signaled when pkts are available */
	pthread_mutex_t fq_mtx;		/* syncronization mutex */
	struct pkt_head fq_pkthead;	/* queue head */
	int fq_maxsz;			/* max size (in packets) of queue */
	int fq_size;			/* current size */
};

/*
 * Contains information about a particular ip flow.
 */
struct ip_flow {
	char	 *if_data;	/* concatenated payload (max QMAXSZ pkts) */
	uint32_t if_datalen;	/* length in bytes of if_data */
	uint16_t if_pktcount;	/* number of packets concatenated */
	uint16_t if_fwrule;	/* ipfw(4) rule associated with flow */
	time_t	 expire;	/* flow expire time */
};

/*
 * Structure used as key for maintaining hash table of IP flows.
 */
struct ip_flow_key {
	struct in_addr ik_src;		/* src IP address */
	struct in_addr ik_dst;		/* dst IP address */
	uint16_t  ik_sport;		/* src port */
	uint16_t  ik_dport;		/* dst port */
};

/*
 * IP packet header.
 */
struct allhdr {
	struct ip ah_ip;
	union {
		struct tcphdr tcp;
		struct udphdr udp;
	} ah_nexthdr;
#define ah_tcp		ah_nexthdr.tcp
#define ah_udp		ah_nexthdr.udp
};

struct pktmem_stack {
	struct pkt_head ps_pkts;
	pthread_mutex_t ps_mtx;
	uint16_t	ps_size;
	uint16_t	ps_maxsz;
};

/*
 * Global incomming and outgoing queues.
 */
static struct ic_queue outQ;
struct pktmem_stack *g_ps;
struct hashtable *th, *uh;

/*
 * Since getting time is a expensive operation dedicate 
 * a thread to it.
 */
static struct timeval   t_time;
static int reconfigure_protocols = 0;

/* divert(4) socket */
static int dvtSin = 0;
static int dvtSout = 0;

/* config file path */
static const char *conf = IC_CONFIG_PATH;

/* Directory containing protocol files with matching RE patterns */
static const char *protoDir = IC_PROTO_PATH;

/* List of protocols available to the system */
pthread_rwlock_t fp_lock;
#define FP_LOCK_INIT    pthread_rwlock_init(&fp_lock, NULL)
#define FP_LOCK         pthread_rwlock_rdlock(&fp_lock)
#define FP_UNLOCK       pthread_rwlock_unlock(&fp_lock)
#define FP_WLOCK        pthread_rwlock_wrlock(&fp_lock)

struct ic_protocols *fp; /* For keeping all known protocols. */
struct phead plist; /* For actually configured protocols. */

static time_t time_expire = 40; /* 40 seconds */
static int debug = 0; /* Debugging messages */

/*
 * Forward function declarations.
 */
void		*classify_pthread(void *);
void		*write_pthread(void *);
void		*garbage_pthread(void *);
void		*classifyd_get_time(void *);
static int	equalkeys(void *, void *);
static unsigned int hashfromkey(void *);
static void	reconfigure_protos(void);
static void	handle_signal(int);
static void	clear_proto_list(void);
static int	read_config(const char *);
static void	usage(const char *);
static struct pktmem_stack *pktmem_init(uint16_t);
static void	pktmem_fini(struct pktmem_stack *);
static struct ic_pkt *pktmem_pop(struct pktmem_stack *);
static void	pktmem_push(struct pktmem_stack *, struct ic_pkt *);

void *
classifyd_get_time(void *arg __unused) 
{
	struct timespec ts;

	/* wakeup every 10 seconds */
	ts.tv_sec = 10;
	ts.tv_nsec = 0;

	/* loop forever */
	for (;;) {
		while(gettimeofday(&t_time, NULL) != 0)
			;

		nanosleep(&ts, 0);
	}
}

int
main(int argc, char **argv)
{
	struct sockaddr_in addr;
	struct sigaction sa;
	pthread_t  classifytd, writetd, time_thread, garbagetd;
	const char *errstr;
	long long  num;
	uint16_t   port, qmaxsz;
	int	   ch, error;

	port = IC_DPORT;
	qmaxsz = IC_QMAXSZ;
	while ((ch = getopt(argc, argv, "n:e:htc:P:p:q:")) != -1) {
		switch(ch) {
		case 'c':
			conf = strdup(optarg);
			if (conf == NULL)
				err(EX_TEMPFAIL, "config file path");
			break;
		case 'd':
			debug++;
			break;
		case 'e':
			num = strtonum((const char *)optarg, 1, 400, &errstr);
			if (num == 0 && errstr != NULL) {
				errx(EX_USAGE, "invalud expire seconds: %s", errstr);	
			}
			time_expire = (time_t)num;
			break;
		case 'n':
                        num = strtonum((const char *)optarg, 1, 65535, &errstr);
                        if (num == 0 && errstr != NULL) {
                                errx(EX_USAGE, "invalid number for packets: %s", errstr);
                        }
			IC_PKTMAXMATCH = num;
			break;
		case 'P':
			protoDir = strdup(optarg);
			if (protoDir == NULL)
				err(EX_TEMPFAIL, "protocols directory path");
			break;
		case 'p':
			num = strtonum((const char *)optarg, 1, 65535, &errstr);
			if (num == 0 && errstr != NULL) {
				errx(EX_USAGE, "invalid divert port: %s", errstr);
			}
			port = (uint16_t)num;
			break;
		case 'q':
			num = strtonum((const char *)optarg, 0, 65535, &errstr);
			if (num == 0 && errstr != NULL) {
				errx(EX_USAGE, "invalid queue length: %s", errstr);
			}
			qmaxsz = (uint16_t)num;
			break;
		case 'h':
		default:
			usage((const char *)*argv);
			exit(EX_USAGE);
		}
	}
	argc -= optind;
	argv += optind;

	closefrom(3);

	if (daemon(0, 1) != 0)
		err(EX_OSERR, "unable to daemonize");

	/*
	 * Initialize stack that will hold memory for our packet objects.
	 */
	g_ps = pktmem_init(IC_PKTSTACKSZ);
	if (g_ps == NULL) {
		syslog(LOG_ERR, "unable to initialize stack");
		exit(EX_TEMPFAIL);
	}

	SLIST_INIT(&plist);

	/*
	 * Initialize outgoing queue.
	 */
	STAILQ_INIT(&outQ.fq_pkthead);
	outQ.fq_size = 0;
	outQ.fq_maxsz = qmaxsz;
	outQ.fq_mtx = PTHREAD_MUTEX_INITIALIZER;
	outQ.fq_condvar = PTHREAD_COND_INITIALIZER;

	/*
	 * Create and bind the divert(4) socket.
	 */
	memset((void *)&addr, 0, sizeof(addr));
	addr.sin_family = AF_INET;
	addr.sin_port = htons(port);
	addr.sin_addr.s_addr = INADDR_ANY;
	dvtSin = socket(PF_INET, SOCK_RAW, IPPROTO_DIVERT);
	if (dvtSin == -1)
		err(EX_OSERR, "unable to create in divert socket");
	error = bind(dvtSin, (struct sockaddr *)&addr, sizeof(addr));
	if (error != 0)
		err(EX_OSERR, "unable to bind in divert socket");
	dvtSout = socket(PF_INET, SOCK_RAW, IPPROTO_DIVERT);
	if (dvtSout == -1)
		err(EX_OSERR, "unable to create out divert socket");
	addr.sin_port = htons(port + 1);
	error = bind(dvtSout, (struct sockaddr *)&addr, sizeof(addr));
	if (error != 0)
		err(EX_OSERR, "unable to bind out divert socket");

	/*
	 * Initialize list of available protocols.
	 */
	FP_LOCK_INIT;
	fp = init_protocols(protoDir);
	if (fp == NULL) {
		syslog(LOG_ERR, "unable to initialize list of protocols: %m");
		exit(EX_SOFTWARE);
	}

	/*
	 * Match protocol to ipfw(4) rule from configuration file.
	 */
	error = read_config(conf);
	if (error != 0){
		syslog(LOG_ERR, "unable to read configuration file");
		exit(error);
	}

        /*
         * Catch SIGHUP in order to reread configuration file.
         */
        sa.sa_handler = handle_signal;
        sa.sa_flags = SA_SIGINFO|SA_RESTART;
        sigemptyset(&sa.sa_mask);
        error = sigaction(SIGHUP, &sa, NULL);
        if (error == -1)
                err(EX_OSERR, "unable to set signal handler");
        error = sigaction(SIGTERM, &sa, NULL);
        if (error == -1)
                err(EX_OSERR, "unable to set signal handler");

	/*
	 * Create the various threads.
	 */
	error = pthread_create(&classifytd, NULL, classify_pthread, NULL);
	if (error != 0)
		err(EX_OSERR, "unable to create classifier thread");
	error = pthread_create(&writetd, NULL, write_pthread, NULL);
	if (error != 0)
		err(EX_OSERR, "unable to create writer thread");
	error = pthread_create(&garbagetd, NULL, garbage_pthread, NULL);
	if (error != 0)
		err(EX_OSERR, "unable to create writer thread");


	error = pthread_create(&time_thread, NULL, classifyd_get_time, NULL);
        if (error != 0) {
                syslog(LOG_ERR, "unable to create time reading thread");
		err(EX_OSERR, "unable to create time reading thread");
        }

	/*
	 * Wait for our threads to exit.
	 */
	pthread_join(writetd, NULL);
	pthread_join(classifytd, NULL);
	pthread_join(garbagetd, NULL);
	pthread_join(time_thread, NULL);

	/*
	 * Cleanup
	 */
	if (dvtSin > 0)
		close(dvtSin);
	if (dvtSout > 0)
		close(dvtSout);

	FP_WLOCK;
	clear_proto_list();
	fini_protocols(fp);
	FP_UNLOCK;

	pktmem_fini(g_ps);

	return (error);
}

#define SET_KEY(k, hdr, sp, dp)						\
	do {								\
		if ((sp) > (dp)) {				\
			(k)->ik_src = (hdr)->ah_ip.ip_src;		\
			(k)->ik_dst = (hdr)->ah_ip.ip_dst;		\
			(k)->ik_sport = (sp);			\
			(k)->ik_dport = (dp);			\
		} else {					\
			(k)->ik_src = (hdr)->ah_ip.ip_dst;		\
			(k)->ik_dst = (hdr)->ah_ip.ip_src;		\
			(k)->ik_sport = (dp);			\
			(k)->ik_dport = (sp);			\
		}						\
	} while (0)

/*
 * XXX - Yeah, I know. This is messy, but I want the classifier and pattern
 *	 tester (-t switch) to use the same code, but I didn't want to put
 *	 it in a separate function of its own for performance reasons.
 */
#define CLASSIFY(pkt, proto, flow, key, pmatch, error, regerr) 	\
	do {									\
		FP_LOCK;							\
		SLIST_FOREACH((proto), &plist, p_next) {		\
			(pmatch).rm_so = 0;					\
			(pmatch).rm_eo = (flow)->if_datalen;			\
			(error) = regexec(&(proto)->p_preg, (flow)->if_data,	\
				1, &(pmatch), REG_STARTEND);			\
                        if ((error) == 0) {                                     \
                                (flow)->if_fwrule = (proto)->p_fwrule;          \
                                (pkt)->fp_saddr.sin_port = (flow)->if_fwrule;   \
				if (debug > 0) {				\
					syslog(LOG_WARNING, "Found Protocol: %s (rule %s)", \
						(proto)->p_name, ((proto)->p_fwrule == DIVERT_ACTION) ? "action block": \
						((proto)->p_fwrule & DIVERT_DNCOOKIE) ? "dnpipe" : \
						((proto)->p_fwrule & DIVERT_ALTQ) ? "altq" : "tag"); \
				}						\
				break;						\
			} else if (error < 0) { 			\
				regerror((error), &(proto)->p_preg, (regerr), sizeof((regerr))); \
				syslog(LOG_WARNING, "error matching %s:%d -> %s:%d against %s: %s", \
					inet_ntoa((key)->ik_src), ntohs((key)->ik_sport), \
					inet_ntoa((key)->ik_dst), ntohs((key)->ik_dport), \
					(proto)->p_name, (regerr));		\
			}							\
		}								\
		FP_UNLOCK;							\
	} while (0)

void *
classify_pthread(void *arg __unused)
{
	char		 errbuf[LINE_MAX];
	struct allhdr	 *hdr;
	struct ip_flow_key tmpKey;
	struct ip_flow_key *key;
	struct ip_flow	 *flow;
	struct tcphdr	 *tcp = NULL;;
	struct udphdr	 *udp;
	struct ic_pkt	 *pkt;
	struct protocol *proto;
	struct hashtable *h = NULL;
	regmatch_t	 pmatch;
	u_char		 *data, *payload = NULL;
	int		 len, datalen = 0, error;

	memset(&tmpKey, 0, sizeof tmpKey);

	th = create_hashtable(IC_HASHSZ, hashfromkey, equalkeys);
	if (th == NULL) {
		syslog(LOG_ERR, "unable to create TCP tracking table");
		exit(EX_SOFTWARE);
	}
	uh = create_hashtable(IC_HASHSZ, hashfromkey, equalkeys);
	if (uh == NULL) {
		syslog(LOG_ERR, "unable to create UDP tracking table");
		exit(EX_SOFTWARE);
	}

	flow = NULL;
	key = NULL;
	while(1) {
		pkt = pktmem_pop(g_ps);
		if (pkt == NULL) {
			syslog(LOG_ERR, "could not allocate packet memory");
			exit(EX_TEMPFAIL);
		}

getinput:
                memset(&pkt->fp_saddr, '\0', sizeof(struct sockaddr_in));
                pkt->fp_salen = sizeof(struct sockaddr_in);
                len = recvfrom(dvtSin, (void *)pkt->fp_pkt, IC_PKTSZ, 0,
                    (struct sockaddr *)&pkt->fp_saddr, &pkt->fp_salen);
                if (len == -1) {
			syslog(LOG_ERR, "receive from divert socket failed: %m");
                        goto getinput; /* XXX */
                }
		pkt->fp_pktlen = len;

		/*
		 * Check if new and insert into appropriate table.
		 */
		hdr = (struct allhdr *)pkt->fp_pkt;
		if (hdr->ah_ip.ip_p == IPPROTO_TCP) {
			tcp = &hdr->ah_tcp;
			payload = (u_char *)((u_char *)tcp + (tcp->th_off * 4));
			datalen = ntohs(hdr->ah_ip.ip_len) -
			    (int)((caddr_t)payload - (caddr_t)&hdr->ah_ip);
			if (datalen < 0) {
				syslog(LOG_WARNING, "TCP packet payload length < 0");
				goto enqueue;
			}
			key = &tmpKey;
			SET_KEY(key, hdr, tcp->th_sport, tcp->th_dport);
			h = th;
		} else if (hdr->ah_ip.ip_p == IPPROTO_UDP) {
                        udp = &hdr->ah_udp;
                        payload = (u_char *)((u_char *)udp + sizeof(*udp));
                        datalen = ntohs(hdr->ah_ip.ip_len) -
                            (int)((caddr_t)payload - (caddr_t)&hdr->ah_ip);
			if (datalen < 0) {
				syslog(LOG_WARNING, "UDP packet payload length < 0");
				goto enqueue;
			}
			key = &tmpKey;
			SET_KEY(key, hdr, udp->uh_sport, udp->uh_dport);
			h = uh;
		}

		assert(datalen >= 0);

		/*
		 * Look in the regular table first since most
		 * packets will belong to an already established
		 * session.
		 */
		flow = hashtable_search(h, (void *)key);
		if (flow == NULL) {
			key = (struct ip_flow_key *)
			    malloc(sizeof(struct ip_flow_key));
			if (key == NULL) {
				syslog(LOG_WARNING, "packet dropped: %m");
				pktmem_push(g_ps, pkt);
				continue;
			}
			*key = tmpKey;

			flow = (struct ip_flow *)malloc(sizeof(struct ip_flow));
                        if (flow == NULL) {
                        	syslog(LOG_WARNING, "packet dropped: %m");
                                free(key);
				pktmem_push(g_ps, pkt);
                                continue;
			}

			if (datalen > 0) {
                         	data = (char *)malloc(datalen);
                                if (data == NULL) {
                                	syslog(LOG_WARNING, "packet dropped: %m");
                                        free(flow);
                                        free(key);
					pktmem_push(g_ps, pkt);
                                        continue;
                                 }
                                 memcpy((void *)data, (void *)payload, datalen);
			} else
				data = NULL;

			flow->if_data = data;
			flow->if_datalen = datalen;
                        flow->if_pktcount = 1;
                        flow->if_fwrule = 0;
                        if (hashtable_insert(h, (void *)key, (void *)flow) == 0) {
				syslog(LOG_WARNING,
					"packet dropped: unable to insert into table");
				if (data != NULL)
                                	free(data);
				free(flow);
                                free(key);
				pktmem_push(g_ps, pkt);
                                continue;
			}
			
		} else if (datalen > 0 && flow->if_fwrule == 0 &&
		    flow->if_pktcount <= IC_PKTMAXMATCH) {
			data = (char *)realloc((void *)flow->if_data,
			    flow->if_datalen + datalen);
			if (data == NULL) {
				syslog(LOG_WARNING, "packet dropped: %m");
				pktmem_push(g_ps, pkt);
				continue;
			}
			memcpy((void *)(data + flow->if_datalen),
			    (void *)payload, datalen);
			flow->if_data = data;
			flow->if_datalen += datalen;
			flow->if_pktcount++;

		}

		/*
		 * Inform divert(4) what rule to send it to by
		 * modifying the port number of the associated sockaddr_in
		 * structure. Note: we subtract one from the ipfw(4) rule
		 * number because processing in ipfw(4) will start with
		 * the next rule *after* the supplied rule number.
		 */
		if (flow != NULL) {
			flow->expire = t_time.tv_sec;

			if (flow->if_fwrule != 0)
				pkt->fp_saddr.sin_port = flow->if_fwrule;
			else if (datalen > 0 && flow->if_pktcount <= IC_PKTMAXMATCH)
				/*
				 * Packet has not been classified yet. Attempt to classify it.
				 */
				CLASSIFY(pkt, proto, flow, key, pmatch, error, errbuf);
		}

enqueue:
		/* Drop the packet if the output queue is full */
		if (outQ.fq_size >= outQ.fq_maxsz) {
			syslog(LOG_WARNING, "packet dropped: output queue full");
			pktmem_push(g_ps, pkt);
			continue;
		}

		/*
		 * Enqueue for writing back to divert(4) socket.
		 */
		pthread_mutex_lock(&outQ.fq_mtx);
		STAILQ_INSERT_HEAD(&outQ.fq_pkthead, pkt, fp_link);
		outQ.fq_size++;
		pthread_cond_signal(&outQ.fq_condvar);
		pthread_mutex_unlock(&outQ.fq_mtx);
	}

	/* NOTREACHED */
	return (NULL);
}

void *
write_pthread(void *arg __unused)
{
	char errbuf[LINE_MAX];
	struct pkt_head pkts;
	struct ic_pkt *pkt;
	int	  error, len;

	STAILQ_INIT(&pkts);
	while (1) {
		pthread_mutex_lock(&outQ.fq_mtx);
		pkt = STAILQ_LAST(&outQ.fq_pkthead, ic_pkt, fp_link);
		while (pkt == NULL) {
			error = pthread_cond_wait(&outQ.fq_condvar, &outQ.fq_mtx);
			if (error != 0) {
				strerror_r(error, errbuf, sizeof(errbuf));
				syslog(LOG_ERR,
				    "unable to wait on output queue: %s",
				    errbuf);
				    exit(EX_OSERR);
			}
			pkt = STAILQ_LAST(&outQ.fq_pkthead, ic_pkt, fp_link);
		}
		
		/*
		 * Drain the output queue of all currently queued packets.
		 */
		while (pkt != NULL) {
			STAILQ_REMOVE(&outQ.fq_pkthead, pkt, ic_pkt, fp_link);
			outQ.fq_size--;
			STAILQ_INSERT_HEAD(&pkts, pkt, fp_link);
			pkt = STAILQ_LAST(&outQ.fq_pkthead, ic_pkt, fp_link);
		}

		pthread_mutex_unlock(&outQ.fq_mtx);

		while (!STAILQ_EMPTY(&pkts)) {
			pkt = STAILQ_LAST(&pkts, ic_pkt, fp_link);
			STAILQ_REMOVE(&pkts, pkt, ic_pkt, fp_link);
			len = sendto(dvtSout, (void *)pkt->fp_pkt, pkt->fp_pktlen, 0,
				(const struct sockaddr *)&pkt->fp_saddr, pkt->fp_salen);
			if (len == -1) {
				if (errno == EACCES)
					syslog(LOG_WARNING,
						"packet dropped by security policy! %m");
				else
					syslog(LOG_WARNING,
			    			"unable to write to divert socket: %m");
			} else if ((size_t)len != pkt->fp_pktlen) {
				if (errno == EMSGSIZE)
					syslog(LOG_WARNING, "packet to big %zu bytes.",
						pkt->fp_pktlen);
				else
					syslog(LOG_WARNING,
			    		"complete packet not written: wrote %d of %zu", len,
			    			pkt->fp_pktlen);
			}
			/*
			 * Cleanup
			 */
			pktmem_push(g_ps, pkt);
		}
	}

	/* NOTREACHED */
	return (NULL);
}

void *
garbage_pthread(void *arg __unused)
{
	struct entry *e, *f;
	unsigned int i, j, flows_expired;
	struct ip_flow *flow;
	struct hashtable *h;
        struct kevent change;    /* event we want to monitor */
        struct kevent event;     /* event that was triggered */
        int kq, nev;

initkqueue:
        /* create a new kernel event queue */
        if ((kq = kqueue()) == -1) {
                syslog(LOG_ERR, "Could not initialize kqueue");
                return NULL;
        }

        /* wakeup every 5 seconds */
        EV_SET(&change, 1, EVFILT_TIMER, EV_ADD | EV_ENABLE, 0, 30000, NULL);

        /* loop forever */
        for (;;) {
                nev = kevent(kq, &change, 1, &event, 1, NULL);

        	if (nev < 0) {
                	goto initkqueue;
        	}

        	else if (nev > 0) {
                	if (event.flags & EV_ERROR) {   /* report any error */
                        	syslog(LOG_ERR, "EV_ERROR: %s\n", strerror(event.data));
                        	goto initkqueue;
                	}

			flows_expired = 0;
			t_time.tv_sec -= time_expire;

			j = 2;
			while (j > 0) {
				if (j == 2)
					h = th;
				else
					h = uh;
				for (i = 0; i < h->tablelength; i++) {
                        		e = h->table[i];
                        		while (e != NULL) {
                                		f = e; e = e->next;
                                		if (f->v != NULL && ((struct ip_flow *)f->v)->expire < t_time.tv_sec) {
                                        		freekey(f->k);
                                        		h->entrycount--;
                                        		if (f->v != NULL) {
								flow = f->v;
								if (flow->if_data != NULL)
									free(flow->if_data);
								free(f->v);
							}
                                        		free(f);
							flows_expired++;
							h->table[i] = e;
                                		}
                        		}
                		}
				j--;
			}
#if 0
			syslog(LOG_WARNING, "expired %u flows", flows_expired);
#endif
		}

		/* If SIGHUP is catched reload protos */
		if (reconfigure_protocols) {
			reconfigure_protos();
			reconfigure_protocols = 0;
		}
	}

	close(kq);

	/* NOTREACHED */
	return (NULL);
}

static void
clear_proto_list()
{
	struct protocol *p;

	while (!SLIST_EMPTY(&plist)) {
                p = SLIST_FIRST(&plist);
                SLIST_REMOVE_HEAD(&plist, p_next);
		if (p->p_path != NULL)
                        free(p->p_path);
                if (p->p_name != NULL)
                        free(p->p_name);
                if (p->p_re != NULL)
                        free(p->p_re);
                regfree(&p->p_preg);
                free(p);
        }
}

/*
 * NOTE: The protocol list (plist) passed as an argument is a global
 *	 variable. It is accessed from 3 functions: classify_pthread,
 *	 re_test, and handle_signal. However, we don't need to implement
 *	 syncronization mechanisms (like mutexes) because only one
 *	 of them at a time will have access to it: the first and
 *	 second functions run in mutually exclusive contexts, and
 *	 since handle_signal is a signal handler there is no chance that
 *	 it will run concurrently with either of the other two. Second,
 *	 the list is created once and no additions or deletions are
 *	 made during the lifetime of the program. The only modification
 *	 this function makes is to change the firewall rule associated
 *	 with a protocol.
 */
static int
read_config(const char *file)
{
	enum { bufsize = 2048 };
	struct protocol *proto, *prototmp;
	properties	props;
	const char	*errmsg, *name;
	char		*value;
	int		fd, fdpf;
	uint16_t	rule;
	struct pfioc_ruleset trule;
	char **ap, *argf[bufsize];

	clear_proto_list();

	fdpf = open("/dev/pf", O_RDONLY);
	if (fdpf == -1) {
		syslog(LOG_ERR, "unable to open /dev/pf");
		return (EX_OSERR);
	}
	fd = open(file, O_RDONLY);
	if (fd == -1) {
		syslog(LOG_ERR, "unable to open configuration file");
		close(fdpf);
		return (EX_OSERR);
	}
	props = properties_read(fd);
	if (props == NULL) {
		syslog(LOG_ERR, "error reading configuration file");
		close(fd);
		close(fdpf);
		return (EX_DATAERR);
	}
	
	fp->fp_inuse = 0;
	SLIST_FOREACH_SAFE(proto, &fp->fp_p, p_next, prototmp) {
		if (proto->p_name == NULL)
			continue;
		name = proto->p_name;
		value = property_find(props, name);
		/* Do not match traffic against this pattern */
		if (value == NULL)
			continue;
		for (ap = argf; (*ap = strsep(&value, " \t")) != NULL;)
 	       		if (**ap != '\0')
        	     		if (++ap >= &argf[bufsize])
                			break;
		if (!strncmp(argf[0], "queue", strlen("queue"))) {
			bzero(&trule, sizeof(trule));
			strlcpy(trule.name, argf[1], sizeof(trule.name));
			if (ioctl(fdpf, DIOCGETNAMEDALTQ, &trule)) {
				syslog(LOG_WARNING, 
					"could not get ALTQ translation for"
					" queue %s", argf[1]);
				continue;
			}
			if (trule.nr == 0) {
				syslog(LOG_WARNING,
					"queue %s does not exists!", argf[1]);
				continue;
			}
			trule.nr |= DIVERT_ALTQ;
			rule = trule.nr;
		} else if (!strncmp(argf[0], "dnqueue", strlen("dnqueue"))) {
			rule = strtonum(argf[1], 1, 65535, &errmsg);
			rule |= DIVERT_DNCOOKIE;
		} else if (!strncmp(argf[0], "dnpipe", strlen("dnpipe"))) {
			rule = strtonum(argf[1], 1, 65535, &errmsg);
			rule |= DIVERT_DNCOOKIE;
		} else if (!strncmp(argf[0], "tag", strlen("tag"))) {
                        if (ioctl(fdpf, DIOCGETNAMEDTAG, &rule)) {
                                syslog(LOG_WARNING,
                                        "could not get tag translation for"
                                        " queue %s", argf[1]);
                                continue;
                        }
                        if (rule == 0) {
                                syslog(LOG_WARNING,
                                        "tag %s does not exists!", argf[1]);
                                continue;
                        }
			rule |= DIVERT_TAG;
		} else if (!strncmp(argf[0], "action", strlen("action"))) {
			if (strncmp(argf[1], "block", strlen("block"))) 
				rule = PF_DROP;
			else if (strncmp(argf[1], "allow", strlen("allow"))) 
				rule = PF_PASS;
			else
				continue;
			rule = DIVERT_ACTION;
		} else {
			syslog(LOG_WARNING,
			    "invalid action specified for %s protocol: %s",
			    proto->p_name, errmsg);
			continue;
		}
		proto->p_fwrule = rule;
		fp->fp_inuse++;
		syslog(LOG_NOTICE, "Loaded Protocol: %s (rule %s)",
		    proto->p_name, (rule == DIVERT_ACTION) ? "action block": 
					(rule & DIVERT_DNCOOKIE) ? "dnpipe" : 
					(rule & DIVERT_ALTQ) ? "altq" : "tag");
		
		SLIST_REMOVE(&fp->fp_p, proto, protocol, p_next);
		SLIST_INSERT_HEAD(&plist, proto, p_next);
	}
	properties_free(props);
	close(fd);
	close(fdpf);
	return (0);
}

/* Called by garbage pthread */
static void
reconfigure_protos()
{

	syslog(LOG_WARNING, "Reloading config...");
	FP_WLOCK;
	fini_protocols(fp);
	fp = init_protocols(protoDir);
	if (fp == NULL) {
		syslog(LOG_ERR, "unable to initialize list of protocols: %m");
		exit(EX_SOFTWARE);
	}
	if (read_config(conf) != 0) {
		syslog(LOG_ERR, "Could not read config exiting.");
		exit(-1);
	}
	FP_UNLOCK;
}

static void
handle_signal(int sig)
{
	switch(sig) {
	case SIGHUP:
		reconfigure_protocols = 1;
		break;
	default:
		syslog(LOG_WARNING, "unhandled signal");
	}
}

static void
usage(const char *arg0)
{
	printf("usage: %s [-h] [-c file] [-e seconds] [-n packets] "
		"[-p port] [-P dir] [-q length]\n", basename(arg0));
	printf("usage: %s -t -P dir\n", basename(arg0));
	printf(	"    -c file   : path to configuration file\n"
		"    -e secs   : number of seconds before a flow is expired\n"
		"    -h        : this help screen\n"
		"    -n packets: number of packets before the garbage collector"
			" tries to expire flows\n"
		"    -P dir    : directory containing protocol patterns\n"
		"    -p port   : port number of divert socket\n"
		"    -q length : max length (in packets) of in/out queues\n"
		"    -t        : test the sample protocol data supplied on "
		"the standard input stream\n");
}

static struct pktmem_stack *
pktmem_init(uint16_t num)
{
	struct pktmem_stack *ps;
	struct ic_pkt	*p;
	int i;

	ps = (struct pktmem_stack *)malloc(sizeof(struct pktmem_stack));
	if (ps == NULL)
		return (NULL);
	ps->ps_mtx = PTHREAD_MUTEX_INITIALIZER;
	STAILQ_INIT(&ps->ps_pkts);
	ps->ps_size = 0;
	ps->ps_maxsz = num;
	for (i = 0; i < num; i++) {
		p = (struct ic_pkt *)malloc(sizeof(struct ic_pkt));
		if (p == NULL) {
			while (!STAILQ_EMPTY(&ps->ps_pkts)) {
				p = STAILQ_FIRST(&ps->ps_pkts);
				STAILQ_REMOVE_HEAD(&ps->ps_pkts, fp_link);
				free(p);
			}
			free(ps);
			return (NULL);
		}
		STAILQ_INSERT_HEAD(&ps->ps_pkts, p, fp_link);
		ps->ps_size++;
	}
	return (ps);		
}

static void
pktmem_fini(struct pktmem_stack *ps)
{
	struct ic_pkt *p;

	while (!STAILQ_EMPTY(&ps->ps_pkts)) {
		p = STAILQ_FIRST(&ps->ps_pkts);
		STAILQ_REMOVE_HEAD(&ps->ps_pkts, fp_link);
		free(p);
	}
	free(ps);
}

static struct ic_pkt *
pktmem_pop(struct pktmem_stack *ps)
{
	struct ic_pkt *p;

	pthread_mutex_lock(&ps->ps_mtx);
	p = STAILQ_FIRST(&ps->ps_pkts);
	STAILQ_REMOVE_HEAD(&ps->ps_pkts, fp_link);
	if (p != NULL)
		ps->ps_size--;
	else
		p = (struct ic_pkt *)malloc(sizeof(struct ic_pkt));
	pthread_mutex_unlock(&ps->ps_mtx);
	return (p);
}

static void
pktmem_push(struct pktmem_stack *ps, struct ic_pkt *p)
{
	pthread_mutex_lock(&ps->ps_mtx);
	if (ps->ps_size < ps->ps_maxsz) {
		STAILQ_INSERT_HEAD(&ps->ps_pkts, p, fp_link);
		ps->ps_size++;
	} else
		free(p);
	pthread_mutex_unlock(&ps->ps_mtx);
}

/*
 * Credits: Christopher Clark <firstname.lastname@cl.cam.ac.uk>
 */
static unsigned int
hashfromkey(void *ky)
{
    struct ip_flow_key *k = (struct ip_flow_key *)ky;
    return (((k->ik_src.s_addr << 17) | (k->ik_src.s_addr >> 15)) ^ k->ik_dst.s_addr) +
            (k->ik_sport * 17) + (k->ik_dport * 13 * 29);
}

static int
equalkeys(void *k1, void *k2)
{
    return (0 == memcmp(k1,k2,sizeof(struct ip_flow_key)));
}
