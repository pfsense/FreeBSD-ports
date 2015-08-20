/*
 * Copyright (c) 1988, 1989, 1990, 1991, 1993, 1994, 1995, 1996
 *	The Regents of the University of California.  All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that: (1) source code distributions
 * retain the above copyright notice and this paragraph in its entirety, (2)
 * distributions including binary code include the above copyright notice and
 * this paragraph in its entirety in the documentation or other materials
 * provided with the distribution, and (3) all advertising materials mentioning
 * features or use of this software display the following acknowledgement:
 * ``This product includes software developed by the University of California,
 * Lawrence Berkeley Laboratory and its contributors.'' Neither the name of
 * the University nor the names of its contributors may be used to endorse
 * or promote products derived from this software without specific prior
 * written permission.
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND WITHOUT ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, WITHOUT LIMITATION, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE.
 *
 * $FreeBSD$
 */

#include <sys/types.h>
#include <sys/socket.h>
#include <sys/sbuf.h>
#include <netinet/in.h>
#include <netinet/ip.h>
#include <netinet/tcp.h>
#include <netinet/udp.h>
#include <arpa/inet.h>

#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include "common.h"

/*
 * Interface Control Message Protocol Definitions.
 * Per RFC 792, September 1981.
 */

/*
 * Structure of an icmp header.
 */
struct icmp {
	u_int8_t  icmp_type;		/* type of message, see below */
	u_int8_t  icmp_code;		/* type sub code */
	u_int16_t icmp_cksum;		/* ones complement cksum of struct */
	union {
		u_int8_t ih_pptr;			/* ICMP_PARAMPROB */
		struct in_addr ih_gwaddr;	/* ICMP_REDIRECT */
		struct ih_idseq {
			u_int16_t icd_id;
			u_int16_t icd_seq;
		} ih_idseq;
		u_int32_t ih_void;
	} icmp_hun;
#define	icmp_pptr	icmp_hun.ih_pptr
#define	icmp_gwaddr	icmp_hun.ih_gwaddr
#define	icmp_id		icmp_hun.ih_idseq.icd_id
#define	icmp_seq	icmp_hun.ih_idseq.icd_seq
#define	icmp_void	icmp_hun.ih_void
	union {
		struct id_ts {
			u_int32_t its_otime;
			u_int32_t its_rtime;
			u_int32_t its_ttime;
		} id_ts;
		struct id_ip  {
			struct ip idi_ip;
			/* options and then 64 bits of data */
		} id_ip;
		u_int32_t id_mask;
		u_int8_t id_data[1];
	} icmp_dun;
#define	icmp_otime	icmp_dun.id_ts.its_otime
#define	icmp_rtime	icmp_dun.id_ts.its_rtime
#define	icmp_ttime	icmp_dun.id_ts.its_ttime
#define	icmp_ip		icmp_dun.id_ip.idi_ip
#define	icmp_mask	icmp_dun.id_mask
#define	icmp_data	icmp_dun.id_data
};

#define ICMP_MPLS_EXT_EXTRACT_VERSION(x) (((x)&0xf0)>>4) 
#define ICMP_MPLS_EXT_VERSION 2

/*
 * Lower bounds on packet lengths for various types.
 * For the error advice packets must first insure that the
 * packet is large enought to contain the returned ip header.
 * Only then can we do the check to see if 64 bits of packet
 * data have been returned, since we need to check the returned
 * ip header length.
 */
#define	ICMP_MINLEN	8				/* abs minimum */
#define ICMP_EXTD_MINLEN (156 - sizeof (struct ip))     /* draft-bonica-internet-icmp-08 */
#define	ICMP_TSLEN	(8 + 3 * sizeof (u_int32_t))	/* timestamp */
#define	ICMP_MASKLEN	12				/* address mask */
#define	ICMP_ADVLENMIN	(8 + sizeof (struct ip) + 8)	/* min */
#define	ICMP_ADVLEN(p)	(8 + (IP_HL(&(p)->icmp_ip) << 2) + 8)
	/* N.B.: must separately check that ip_hl >= 5 */

/*
 * Definition of type and code field values.
 */
#define	ICMP_ECHOREPLY		0		/* echo reply */
#define	ICMP_UNREACH		3		/* dest unreachable, codes: */
#define		ICMP_UNREACH_NET	0		/* bad net */
#define		ICMP_UNREACH_HOST	1		/* bad host */
#define		ICMP_UNREACH_PROTOCOL	2		/* bad protocol */
#define		ICMP_UNREACH_PORT	3		/* bad port */
#define		ICMP_UNREACH_NEEDFRAG	4		/* IP_DF caused drop */
#define		ICMP_UNREACH_SRCFAIL	5		/* src route failed */
#define		ICMP_UNREACH_NET_UNKNOWN 6		/* unknown net */
#define		ICMP_UNREACH_HOST_UNKNOWN 7		/* unknown host */
#define		ICMP_UNREACH_ISOLATED	8		/* src host isolated */
#define		ICMP_UNREACH_NET_PROHIB	9		/* prohibited access */
#define		ICMP_UNREACH_HOST_PROHIB 10		/* ditto */
#define		ICMP_UNREACH_TOSNET	11		/* bad tos for net */
#define		ICMP_UNREACH_TOSHOST	12		/* bad tos for host */
#define	ICMP_SOURCEQUENCH	4		/* packet lost, slow down */
#define	ICMP_REDIRECT		5		/* shorter route, codes: */
#define		ICMP_REDIRECT_NET	0		/* for network */
#define		ICMP_REDIRECT_HOST	1		/* for host */
#define		ICMP_REDIRECT_TOSNET	2		/* for tos and net */
#define		ICMP_REDIRECT_TOSHOST	3		/* for tos and host */
#define	ICMP_ECHO		8		/* echo service */
#define	ICMP_ROUTERADVERT	9		/* router advertisement */
#define	ICMP_ROUTERSOLICIT	10		/* router solicitation */
#define	ICMP_TIMXCEED		11		/* time exceeded, code: */
#define		ICMP_TIMXCEED_INTRANS	0		/* ttl==0 in transit */
#define		ICMP_TIMXCEED_REASS	1		/* ttl==0 in reass */
#define	ICMP_PARAMPROB		12		/* ip header bad */
#define		ICMP_PARAMPROB_OPTABSENT 1		/* req. opt. absent */
#define	ICMP_TSTAMP		13		/* timestamp request */
#define	ICMP_TSTAMPREPLY	14		/* timestamp reply */
#define	ICMP_IREQ		15		/* information request */
#define	ICMP_IREQREPLY		16		/* information reply */
#define	ICMP_MASKREQ		17		/* address mask request */
#define	ICMP_MASKREPLY		18		/* address mask reply */

#define	ICMP_MAXTYPE		18

#define	ICMP_INFOTYPE(type) \
	((type) == ICMP_ECHOREPLY || (type) == ICMP_ECHO || \
	(type) == ICMP_ROUTERADVERT || (type) == ICMP_ROUTERSOLICIT || \
	(type) == ICMP_TSTAMP || (type) == ICMP_TSTAMPREPLY || \
	(type) == ICMP_IREQ || (type) == ICMP_IREQREPLY || \
	(type) == ICMP_MASKREQ || (type) == ICMP_MASKREPLY)
#define	ICMP_MPLS_EXT_TYPE(type) \
	((type) == ICMP_UNREACH || \
         (type) == ICMP_TIMXCEED || \
         (type) == ICMP_PARAMPROB)
/* rfc1700 */
#ifndef ICMP_UNREACH_NET_UNKNOWN
#define ICMP_UNREACH_NET_UNKNOWN	6	/* destination net unknown */
#endif
#ifndef ICMP_UNREACH_HOST_UNKNOWN
#define ICMP_UNREACH_HOST_UNKNOWN	7	/* destination host unknown */
#endif
#ifndef ICMP_UNREACH_ISOLATED
#define ICMP_UNREACH_ISOLATED		8	/* source host isolated */
#endif
#ifndef ICMP_UNREACH_NET_PROHIB
#define ICMP_UNREACH_NET_PROHIB		9	/* admin prohibited net */
#endif
#ifndef ICMP_UNREACH_HOST_PROHIB
#define ICMP_UNREACH_HOST_PROHIB	10	/* admin prohibited host */
#endif
#ifndef ICMP_UNREACH_TOSNET
#define ICMP_UNREACH_TOSNET		11	/* tos prohibited net */
#endif
#ifndef ICMP_UNREACH_TOSHOST
#define ICMP_UNREACH_TOSHOST		12	/* tos prohibited host */
#endif

/* rfc1716 */
#ifndef ICMP_UNREACH_FILTER_PROHIB
#define ICMP_UNREACH_FILTER_PROHIB	13	/* admin prohibited filter */
#endif
#ifndef ICMP_UNREACH_HOST_PRECEDENCE
#define ICMP_UNREACH_HOST_PRECEDENCE	14	/* host precedence violation */
#endif
#ifndef ICMP_UNREACH_PRECEDENCE_CUTOFF
#define ICMP_UNREACH_PRECEDENCE_CUTOFF	15	/* precedence cutoff */
#endif

/* Most of the icmp types */
static struct tok icmp2str[] = {
	{ ICMP_ECHOREPLY,		"echo reply" },
	{ ICMP_SOURCEQUENCH,		"source quench" },
	{ ICMP_ECHO,			"echo request" },
	{ ICMP_ROUTERSOLICIT,		"router solicitation" },
	{ ICMP_TSTAMP,			"time stamp request" },
	{ ICMP_TSTAMPREPLY,		"time stamp reply" },
	{ ICMP_IREQ,			"information request" },
	{ ICMP_IREQREPLY,		"information reply" },
	{ ICMP_MASKREQ,			"address mask request" },
	{ 0,				NULL }
};

/* Formats for most of the ICMP_UNREACH codes */
static struct tok unreach2str[] = {
	{ ICMP_UNREACH_NET,		"net %s unreachable" },
	{ ICMP_UNREACH_HOST,		"host %s unreachable" },
	{ ICMP_UNREACH_SRCFAIL,
	    "%s unreachable - source route failed" },
	{ ICMP_UNREACH_NET_UNKNOWN,	"net %s unreachable - unknown" },
	{ ICMP_UNREACH_HOST_UNKNOWN,	"host %s unreachable - unknown" },
	{ ICMP_UNREACH_ISOLATED,
	    "%s unreachable - source host isolated" },
	{ ICMP_UNREACH_NET_PROHIB,
	    "net %s unreachable - admin prohibited" },
	{ ICMP_UNREACH_HOST_PROHIB,
	    "host %s unreachable - admin prohibited" },
	{ ICMP_UNREACH_TOSNET,
	    "net %s unreachable - tos prohibited" },
	{ ICMP_UNREACH_TOSHOST,
	    "host %s unreachable - tos prohibited" },
	{ ICMP_UNREACH_FILTER_PROHIB,
	    "host %s unreachable - admin prohibited filter" },
	{ ICMP_UNREACH_HOST_PRECEDENCE,
	    "host %s unreachable - host precedence violation" },
	{ ICMP_UNREACH_PRECEDENCE_CUTOFF,
	    "host %s unreachable - precedence cutoff" },
	{ 0,				NULL }
};

/* Formats for the ICMP_REDIRECT codes */
static struct tok type2str[] = {
	{ ICMP_REDIRECT_NET,		"redirect %s to net %s" },
	{ ICMP_REDIRECT_HOST,		"redirect %s to host %s" },
	{ ICMP_REDIRECT_TOSNET,		"redirect-tos %s to net %s" },
	{ ICMP_REDIRECT_TOSHOST,	"redirect-tos %s to host %s" },
	{ 0,				NULL }
};

/* rfc1191 */
struct mtu_discovery {
	u_int16_t unused;
	u_int16_t nexthopmtu;
};

/* rfc1256 */
struct ih_rdiscovery {
	u_int8_t ird_addrnum;
	u_int8_t ird_addrsiz;
	u_int16_t ird_lifetime;
};

struct id_rdiscovery {
	u_int32_t ird_addr;
	u_int32_t ird_pref;
};

/*
 * draft-bonica-internet-icmp-08
 *
 * The Destination Unreachable, Time Exceeded
 * and Parameter Problem messages are slighly changed as per
 * the above draft. A new Length field gets added to give
 * the caller an idea about the length of the piggypacked
 * IP packet before the MPLS extension header starts.
 *
 * The Length field represents length of the padded "original datagram"
 * field  measured in 32-bit words.
 *
 * 0                   1                   2                   3
 * 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
 * +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 * |     Type      |     Code      |          Checksum             |
 * +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 * |     unused    |    Length     |          unused               |
 * +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 * |      Internet Header + leading octets of original datagram    |
 * |                                                               |
 * |                           //                                  |
 * |                                                               |
 * +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 */

struct icmp_ext_t {
    u_int8_t icmp_type;
    u_int8_t icmp_code;
    u_int8_t icmp_checksum[2];
    u_int8_t icmp_reserved;
    u_int8_t icmp_length;
    u_int8_t icmp_reserved2[2];
    u_int8_t icmp_ext_legacy_header[128]; /* extension header starts 128 bytes after ICMP header */
    u_int8_t icmp_ext_version_res[2];
    u_int8_t icmp_ext_checksum[2];
    u_int8_t icmp_ext_data[1];
};

struct icmp_mpls_ext_object_header_t {
    u_int8_t length[2];
    u_int8_t class_num;
    u_int8_t ctype;
};

#if 0
static const struct tok icmp_mpls_ext_obj_values[] = {
    { 1, "MPLS Stack Entry" },
    { 2, "Extended Payload" },
    { 0, NULL}
};
#endif

/* prototypes */
const char *icmp_tstamp_print(u_int);

/* print the milliseconds since midnight UTC */
const char *
icmp_tstamp_print(u_int tstamp) {
    u_int msec,sec,min,hrs;

    static char buf[64];

    msec = tstamp % 1000;
    sec = tstamp / 1000;
    min = sec / 60; sec -= min * 60;
    hrs = min / 60; min -= hrs * 60;
    snprintf(buf, sizeof(buf), "%02u:%02u:%02u.%03u",hrs,min,sec,msec);
    return buf;
}
 
void
icmp_print(struct sbuf *sbuf, const u_char *bp, u_int plen, const u_char *bp2, int fragmented __unused)
{
	const struct icmp *dp;
	const struct ip *ip;
	const struct ip *oip;
	const struct udphdr *ouh;
	u_int hlen, dport, mtu;

	dp = (const struct icmp *)bp;
	ip = (const struct ip *)bp2;

	switch (dp->icmp_type) {
	case ICMP_ECHO:
	case ICMP_ECHOREPLY:
		sbuf_printf(sbuf, "%s,%u,%u",
                               dp->icmp_type == ICMP_ECHO ?
                               "request" : "reply",
                               EXTRACT_16BITS(&dp->icmp_id),
                               EXTRACT_16BITS(&dp->icmp_seq));
		break;

	case ICMP_UNREACH:
		switch (dp->icmp_code) {
		case ICMP_UNREACH_PROTOCOL:
			sbuf_printf(sbuf,
			    "unreachproto,%s,%d",
			    inet_ntoa(dp->icmp_ip.ip_dst),
			    dp->icmp_ip.ip_p);
			break;

		case ICMP_UNREACH_PORT:
			oip = &dp->icmp_ip;
			hlen = IP_HL(oip) * 4;
			ouh = (const struct udphdr *)(((const u_char *)oip) + hlen);
			dport = EXTRACT_16BITS(&ouh->uh_dport);
			sbuf_printf(sbuf, "unreachport,");
			switch (oip->ip_p) {
			case IPPROTO_TCP:
				sbuf_printf(sbuf,
					"%s,TCP,%u",
					inet_ntoa(oip->ip_dst),
					dport);
				break;

			case IPPROTO_UDP:
				sbuf_printf(sbuf,
					"%s,UDP,%u",
					inet_ntoa(oip->ip_dst),
					dport);
				break;

			default:
				sbuf_printf(sbuf,
					"%s,%d,%d",
					inet_ntoa(oip->ip_dst),
					oip->ip_p, dport);
				break;
			}
			break;

		case ICMP_UNREACH_NEEDFRAG:
		    {
			register const struct mtu_discovery *mp;
			mp = (const struct mtu_discovery *)(const u_char *)&dp->icmp_void;
			mtu = EXTRACT_16BITS(&mp->nexthopmtu);
			if (mtu) {
				sbuf_printf(sbuf,
				    "needfrag,%s,%d",
				    inet_ntoa(dp->icmp_ip.ip_dst), mtu);
			} else {
				sbuf_printf(sbuf,
				    "needfrag,%s need to frag",
				    inet_ntoa(dp->icmp_ip.ip_dst));
			}
		    }
			break;

		default:
			sbuf_printf(sbuf, "unreach,");
			sbuf_printf(sbuf, code2str(unreach2str, "%s unreachable", dp->icmp_code),
			    inet_ntoa(dp->icmp_ip.ip_dst));
			break;
		}
		break;

	case ICMP_REDIRECT:
		sbuf_printf(sbuf, "redirect,");
		sbuf_printf(sbuf, code2str(type2str, "redirect-?? %s to net %s,", dp->icmp_code),
		    inet_ntoa(dp->icmp_ip.ip_dst),
		    inet_ntoa(dp->icmp_gwaddr));
		break;

	case ICMP_ROUTERADVERT:
	    {
		register const struct ih_rdiscovery *ihp;
		u_int lifetime, num, size;

		sbuf_printf(sbuf, "routeradv");

		ihp = (const struct ih_rdiscovery *)&dp->icmp_void;
		lifetime = EXTRACT_16BITS(&ihp->ird_lifetime);
		if (lifetime < 60) {
			sbuf_printf(sbuf, "%u,",
			    lifetime);
		} else if (lifetime < 60 * 60) {
			sbuf_printf(sbuf, "%u:%02u,",
			    lifetime / 60, lifetime % 60);
		} else {
			sbuf_printf(sbuf,
			    "%u:%02u:%02u,",
			    lifetime / 3600,
			    (lifetime % 3600) / 60,
			    lifetime % 60);
		}

		num = ihp->ird_addrnum;
		sbuf_printf(sbuf, "%d,", num);

		size = ihp->ird_addrsiz;
		if (size != 2) {
			sbuf_printf(sbuf,
			    "%d,", size);
			break;
		}
	    }
		break;

	case ICMP_TIMXCEED:
		sbuf_printf(sbuf, "timexceed,");
		switch (dp->icmp_code) {
		case ICMP_TIMXCEED_INTRANS:
			sbuf_printf(sbuf, "time exceeded in-transit");
			break;

		case ICMP_TIMXCEED_REASS:
			sbuf_printf(sbuf, "ip reassembly time exceeded");
			break;

		default:
			sbuf_printf(sbuf, "time exceeded-#%d",
			    dp->icmp_code);
			break;
		}
		break;

	case ICMP_PARAMPROB:
		if (dp->icmp_code)
			sbuf_printf(sbuf,
			    "paramprob, parameter problem - code %d", dp->icmp_code);
		else {
			sbuf_printf(sbuf,
			    "paramprob, parameter problem - octet %d", dp->icmp_pptr);
		}
		break;

	case ICMP_MASKREPLY:
		sbuf_printf(sbuf, "maskreply, address mask is 0x%08x",
		    EXTRACT_32BITS(&dp->icmp_mask));
		break;

	case ICMP_TSTAMP:
		sbuf_printf(sbuf,
		    "tstamp,%u,%u",
		    EXTRACT_16BITS(&dp->icmp_id),
		    EXTRACT_16BITS(&dp->icmp_seq));
		break;

	case ICMP_TSTAMPREPLY:
		sbuf_printf(sbuf,
		    "tstampreply, %u,%u,%s,",
                               EXTRACT_16BITS(&dp->icmp_id),
                               EXTRACT_16BITS(&dp->icmp_seq),
                               icmp_tstamp_print(EXTRACT_32BITS(&dp->icmp_otime)));

                sbuf_printf(sbuf, "%s,",
                         icmp_tstamp_print(EXTRACT_32BITS(&dp->icmp_rtime)));
                sbuf_printf(sbuf, "%s",
                         icmp_tstamp_print(EXTRACT_32BITS(&dp->icmp_ttime)));
                break;

	default:
		sbuf_printf(sbuf, "%s", code2str(icmp2str, "type-??", dp->icmp_type));
		break;
	}
	sbuf_printf(sbuf, "%u", plen);

	return;
}
