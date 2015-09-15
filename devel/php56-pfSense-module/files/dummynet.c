/*
 * Copyright (c) 2002-2003,2010 Luigi Rizzo
 * Copyright (c) 2012 Ermal Luçi
 *
 * Redistribution and use in source forms, with and without modification,
 * are permitted provided that this entire comment appears intact.
 *
 * Redistribution in binary form may occur without any restrictions.
 * Obviously, it would be nice if you gave credit where credit is due
 * but requiring it would be too onerous.
 *
 * This software is provided ``AS IS'' without any warranties of any kind.
 *
 * $FreeBSD$
 *
 * dummynet support
 */

#include <sys/types.h>
#include <sys/socket.h>
/* XXX there are several sysctl leftover here */
#include <sys/sysctl.h>


#include <ctype.h>
//#include <err.h>
//#include <errno.h>
//#include <libutil.h>
//#include <netdb.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sysexits.h>

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include <net/if.h>
#include <net/ethernet.h>
#include <netinet/in.h>
#include <netinet/ip_fw.h>
#include <netinet/ip_dummynet.h>
#include <arpa/inet.h>	/* inet_ntoa */

#include "ipfw2.h"
#include "php.h"
#include "php_ini.h"
#include "php_pfSense.h"

#define NEED(_p, msg)	{if (!_p) goto end;}
#define NEED1(msg)	{if (!(*av)) goto end;}

ZEND_DECLARE_MODULE_GLOBALS(pfSense)

static struct _s_x dummynet_params[] = {
	{ "plr",		TOK_PLR },
	{ "noerror",		TOK_NOERROR },
	{ "buckets",		TOK_BUCKETS },
	{ "dst-ip",		TOK_DSTIP },
	{ "src-ip",		TOK_SRCIP },
	{ "dst-port",		TOK_DSTPORT },
	{ "src-port",		TOK_SRCPORT },
	{ "proto",		TOK_PROTO },
	{ "weight",		TOK_WEIGHT },
	{ "lmax",		TOK_LMAX },
	{ "maxlen",		TOK_LMAX },
	{ "all",		TOK_ALL },
	{ "mask",		TOK_MASK }, /* alias for both */
	{ "sched_mask",		TOK_SCHED_MASK },
	{ "flow_mask",		TOK_FLOW_MASK },
	{ "droptail",		TOK_DROPTAIL },
	{ "red",		TOK_RED },
	{ "gred",		TOK_GRED },
	{ "bw",			TOK_BW },
	{ "bandwidth",		TOK_BW },
	{ "link",		TOK_LINK },
	{ "pipe",		TOK_PIPE },
	{ "queue",		TOK_QUEUE },
	{ "flowset",		TOK_FLOWSET },
	{ "sched",		TOK_SCHED },
	{ "pri",		TOK_PRI },
	{ "priority",		TOK_PRI },
	{ "type",		TOK_TYPE },
	{ "flow-id",		TOK_FLOWID},
	{ "dst-ipv6",		TOK_DSTIP6},
	{ "dst-ip6",		TOK_DSTIP6},
	{ "src-ipv6",		TOK_SRCIP6},
	{ "src-ip6",		TOK_SRCIP6},
	{ "profile",		TOK_PROFILE},
	{ "dummynet-params",	TOK_NULL },
	{ NULL, 0 }	/* terminator */
};

#define O_NEXT(p, len) ((void *)((char *)p + len))

static void
oid_fill(struct dn_id *oid, int len, int type, uintptr_t id)
{
	oid->len = len;
	oid->type = type;
	oid->subtype = 0;
	oid->id = id;
}

/* make room in the buffer and move the pointer forward */
static void *
o_next(struct dn_id **o, int len, int type)
{
	struct dn_id *ret = *o;
	oid_fill(ret, len, type, 0);
	*o = O_NEXT(*o, len);
	return ret;
}

/*
 * _substrcmp2 takes three strings and returns 1 if the first two do not match,
 * and 0 if they match exactly or the second string is a sub-string
 * of the first.  A warning is printed to stderr in the case that the
 * first string does not match the third.
 *
 * This function exists to warn about the bizzare construction
 * strncmp(str, "by", 2) which is used to allow people to use a shotcut
 * for "bytes".  The problem is that in addition to accepting "by",
 * "byt", "byte", and "bytes", it also excepts "by_rabid_dogs" and any
 * other string beginning with "by".
 *
 * This function will be removed in the future through the usual
 * deprecation process.
 */
static int
_substrcmp2(const char *str1, const char* str2, const char* str3)
{
 
        if (strncmp(str1, str2, strlen(str2)) != 0)
                return 1;
 
        if (strcmp(str1, str3) != 0)
                php_printf("DEPRECATED: '%s' matched '%s'",
                    str1, str3);
        return 0;
}

/*
 * conditionally runs the command.
 * Selected options or negative -> getsockopt
 */
static int
do_cmd(int optname, void *optval, uintptr_t optlen)
{
        int i;

        if (PFSENSE_G(ipfw) == -1)
                PFSENSE_G(ipfw) = socket(AF_INET, SOCK_RAW, IPPROTO_RAW);
        if (PFSENSE_G(ipfw) < 0)
                return (-1);

        if (optname == IP_FW_GET || optname == IP_DUMMYNET_GET ||
            optname == IP_FW_ADD || optname == IP_FW_TABLE_LIST ||
            optname == IP_FW_TABLE_GETSIZE ||
            optname == IP_FW_NAT_GET_CONFIG ||
            optname < 0 ||
            optname == IP_FW_NAT_GET_LOG) {
                if (optname < 0)
                        optname = -optname;
                i = getsockopt(PFSENSE_G(ipfw), IPPROTO_IP, optname, optval,
                        (socklen_t *)optlen);
        } else
                i = setsockopt(PFSENSE_G(ipfw), IPPROTO_IP, optname, optval, optlen);

        return i;
}

/**
 * match_token takes a table and a string, returns the value associated
 * with the string (-1 in case of failure).
 */
static int
match_token(struct _s_x *table, char *string)
{
        struct _s_x *pt;
        uint i = strlen(string);

        for (pt = table ; i && pt->s != NULL ; pt++)
                if (strlen(pt->s) == i && !bcmp(string, pt->s, i))
                        return pt->x;
        return -1;
}

/* n2mask sets n bits of the mask */
static void
n2mask(struct in6_addr *mask, int n)
{
        static int      minimask[9] =
            { 0x00, 0x80, 0xc0, 0xe0, 0xf0, 0xf8, 0xfc, 0xfe, 0xff };
        u_char          *p;

        memset(mask, 0, sizeof(struct in6_addr));
        p = (u_char *) mask;
        for (; n > 0; p++, n -= 8) {
                if (n >= 8)
                        *p = 0xff;
                else
                        *p = minimask[n];
        }
        return;
}

/*
 * Delete pipe, queue or scheduler i
 */
int
ipfw_delete_pipe(int do_pipe, int i)
{
	struct {
		struct dn_id oid;
		uintptr_t a[1];	/* add more if we want a list */
	} cmd;
	oid_fill((void *)&cmd, sizeof(cmd), DN_CMD_DELETE, DN_API_VERSION);
	cmd.oid.subtype = (do_pipe == 1) ? DN_LINK :
		( (do_pipe == 2) ? DN_FS : DN_SCH);
	cmd.a[0] = i;
	i = do_cmd(IP_DUMMYNET3, &cmd, cmd.oid.len);
	if (i) {
		i = 1;
		php_printf("rule %u: setsockopt(IP_DUMMYNET_DEL)", i);
	}
	return i;
}

/*
 * Take as input a string describing a bandwidth value
 * and return the numeric bandwidth value.
 * set clocking interface or bandwidth value
 */
static int
read_bandwidth(char *arg, int *bandwidth, char *if_name, int namelen)
{
	if (*bandwidth != -1) {
		php_printf("duplicate token, override bandwidth value!");
		return (-1);
	}

	if (arg[0] >= 'a' && arg[0] <= 'z') {
		if (!if_name) {
			return (-1);
		}
		if (namelen >= IFNAMSIZ)
			php_printf("interface name truncated");
		namelen--;
		/* interface name */
		strncpy(if_name, arg, namelen);
		if_name[namelen] = '\0';
		*bandwidth = 0;
	} else {	/* read bandwidth value */
		double bw;
		char *end = NULL;

		bw = strtod(arg, &end);
		if (*end == 'K' || *end == 'k') {
			end++;
			bw *= 1000;
		} else if (*end == 'M' || *end == 'm') {
			end++;
			bw *= 1000000;
		}
		if ((*end == 'B' &&
			_substrcmp2(end, "Bi", "Bit/s") != 0) ||
		    _substrcmp2(end, "by", "bytes") == 0)
			bw *= 8;

		if (bw < 0)
			return (-1);

		*bandwidth = (int)bw;
		if (if_name)
			if_name[0] = '\0';
	}

	return (0);
}

/*
 * configuration of pipes, schedulers, flowsets.
 * When we configure a new scheduler, an empty pipe is created, so:
 * 
 * do_pipe = 1 -> "pipe N config ..." only for backward compatibility
 *	sched N+Delta type fifo sched_mask ...
 *	pipe N+Delta <parameters>
 *	flowset N+Delta pipe N+Delta (no parameters)
 *	sched N type wf2q+ sched_mask ...
 *	pipe N <parameters>
 *
 * do_pipe = 2 -> flowset N config
 *	flowset N parameters
 *
 * do_pipe = 3 -> sched N config
 *	sched N parameters (default no pipe)
 *	optional Pipe N config ...
 * pipe ==>
 */
int
ipfw_config_pipe(int ac, char **av, int do_pipe)
{
	int i, j;
	char *end;
	void *par = NULL;
	struct dn_id *buf, *base;
	struct dn_sch *sch = NULL;
	struct dn_link *p = NULL;
	struct dn_fs *fs = NULL;
	struct ipfw_flow_id *mask = NULL;
	int lmax;
	uint32_t _foo = 0, *flags = &_foo , *buckets = &_foo;

	/*
	 * allocate space for 1 header,
	 * 1 scheduler, 1 link, 1 flowset, 1 profile
	 */
	lmax = sizeof(struct dn_id);	/* command header */
	lmax += sizeof(struct dn_sch) + sizeof(struct dn_link) +
		sizeof(struct dn_fs) + sizeof(struct dn_profile);

	av++; ac--;
	/* Pipe number */
	if (ac && isdigit(**av)) {
		i = atoi(*av); av++; ac--;
	} else
		i = -1;
	if (i <= 0) {
		php_printf("need a pipe/flowset/sched number");
		return (-1);
	}
	base = buf = calloc(1, lmax);
	if (base == NULL)
		return (-1);
	/* all commands start with a 'CONFIGURE' and a version */
	o_next(&buf, sizeof(struct dn_id), DN_CMD_CONFIG);
	base->id = DN_API_VERSION;

	switch (do_pipe) {
	case 1: /* "pipe N config ..." */
		/* Allocate space for the WF2Q+ scheduler, its link
		 * and the FIFO flowset. Set the number, but leave
		 * the scheduler subtype and other parameters to 0
		 * so the kernel will use appropriate defaults.
		 * XXX todo: add a flag to record if a parameter
		 * is actually configured.
		 * If we do a 'pipe config' mask -> sched_mask.
		 * The FIFO scheduler and link are derived from the
		 * WF2Q+ one in the kernel.
		 */
		sch = o_next(&buf, sizeof(*sch), DN_SCH);
		p = o_next(&buf, sizeof(*p), DN_LINK);
		fs = o_next(&buf, sizeof(*fs), DN_FS);

		sch->sched_nr = i;
		sch->oid.subtype = 0;	/* defaults to WF2Q+ */
		mask = &sch->sched_mask;
		flags = &sch->flags;
		buckets = &sch->buckets;
		*flags |= DN_PIPE_CMD;

		p->link_nr = i;

		/* This flowset is only for the FIFO scheduler */
		fs->fs_nr = i + 2*DN_MAX_ID;
		fs->sched_nr = i + DN_MAX_ID;
		break;

	case 2: /* "queue N config ... " */
		fs = o_next(&buf, sizeof(*fs), DN_FS);
		fs->fs_nr = i;
		mask = &fs->flow_mask;
		flags = &fs->flags;
		buckets = &fs->buckets;
		break;

	case 3: /* "sched N config ..." */
		sch = o_next(&buf, sizeof(*sch), DN_SCH);
		fs = o_next(&buf, sizeof(*fs), DN_FS);
		sch->sched_nr = i;
		mask = &sch->sched_mask;
		flags = &sch->flags;
		buckets = &sch->buckets;
		/* fs is used only with !MULTIQUEUE schedulers */
		fs->fs_nr = i + DN_MAX_ID;
		fs->sched_nr = i;
		break;
	}
	/* set to -1 those fields for which we want to reuse existing
	 * values from the kernel.
	 * Also, *_nr and subtype = 0 mean reuse the value from the kernel.
	 * XXX todo: support reuse of the mask.
	 */
	if (p)
		p->bandwidth = -1;
	for (j = 0; j < sizeof(fs->par)/sizeof(fs->par[0]); j++)
		fs->par[j] = -1;
	while (ac > 0) {
		double d;
		int tok = match_token(dummynet_params, *av);
		ac--; av++;

		switch(tok) {
		case TOK_NOERROR:
			NEED(fs, "noerror is only for pipes");
			fs->flags |= DN_NOERROR;
			break;

		case TOK_PLR:
			NEED(fs, "plr is only for pipes");
			NEED1("plr needs argument 0..1\n");
			d = strtod(av[0], NULL);
			if (d > 1)
				d = 1;
			else if (d < 0)
				d = 0;
			fs->plr = (int)(d*0x7fffffff);
			ac--; av++;
			break;

		case TOK_QUEUE:
			NEED(fs, "queue is only for pipes or flowsets");
			NEED1("queue needs queue size\n");
			end = NULL;
			fs->qsize = strtoul(av[0], &end, 0);
			if (*end == 'K' || *end == 'k') {
				fs->flags |= DN_QSIZE_BYTES;
				fs->qsize *= 1024;
			} else if (*end == 'B' ||
			    _substrcmp2(end, "by", "bytes") == 0) {
				fs->flags |= DN_QSIZE_BYTES;
			}
			ac--; av++;
			break;

		case TOK_BUCKETS:
			NEED(fs, "buckets is only for pipes or flowsets");
			NEED1("buckets needs argument\n");
			*buckets = strtoul(av[0], NULL, 0);
			ac--; av++;
			break;

		case TOK_FLOW_MASK:
		case TOK_SCHED_MASK:
		case TOK_MASK:
			NEED(mask, "tok_mask");
			NEED1("mask needs mask specifier\n");
			/*
			 * per-flow queue, mask is dst_ip, dst_port,
			 * src_ip, src_port, proto measured in bits
			 */
			par = NULL;

			bzero(mask, sizeof(*mask));
			end = NULL;

			while (ac >= 1) {
			    uint32_t *p32 = NULL;
			    uint16_t *p16 = NULL;
			    uint32_t *p20 = NULL;
			    struct in6_addr *pa6 = NULL;
			    uint32_t a;

			    tok = match_token(dummynet_params, *av);
			    ac--; av++;
			    switch(tok) {
			    case TOK_ALL:
				    /*
				     * special case, all bits significant
				     * except 'extra' (the queue number)
				     */
				    mask->dst_ip = ~0;
				    mask->src_ip = ~0;
				    mask->dst_port = ~0;
				    mask->src_port = ~0;
				    mask->proto = ~0;
				    n2mask(&mask->dst_ip6, 128);
				    n2mask(&mask->src_ip6, 128);
				    mask->flow_id6 = ~0;
				    *flags |= DN_HAVE_MASK;
				    goto end_mask;

			    case TOK_QUEUE:
				    mask->extra = ~0;
				    *flags |= DN_HAVE_MASK;
				    goto end_mask;

			    case TOK_DSTIP:
				    mask->addr_type = 4;
				    p32 = &mask->dst_ip;
				    break;

			    case TOK_SRCIP:
				    mask->addr_type = 4;
				    p32 = &mask->src_ip;
				    break;

			    case TOK_DSTIP6:
				    mask->addr_type = 6;
				    pa6 = &mask->dst_ip6;
				    break;
			    
			    case TOK_SRCIP6:
				    mask->addr_type = 6;
				    pa6 = &mask->src_ip6;
				    break;

			    case TOK_FLOWID:
				    mask->addr_type = 6;
				    p20 = &mask->flow_id6;
				    break;

			    case TOK_DSTPORT:
				    p16 = &mask->dst_port;
				    break;

			    case TOK_SRCPORT:
				    p16 = &mask->src_port;
				    break;

			    case TOK_PROTO:
				    break;

			    default:
				    ac++; av--; /* backtrack */
				    goto end_mask;
			    }
			    if (ac < 1) {
				    php_printf("mask: value missing");
				    goto end;
			    }
			    if (*av[0] == '/') {
				    a = strtoul(av[0]+1, &end, 0);
				    if (pa6 == NULL)
					    a = (a == 32) ? ~0 : (1 << a) - 1;
			    } else
				    a = strtoul(av[0], &end, 0);
			    if (p32 != NULL)
				    *p32 = a;
			    else if (p16 != NULL) {
				    if (a > 0xFFFF) {
					php_printf("port mask must be 16 bit");
					goto end;
				    }
				    *p16 = (uint16_t)a;
			    } else if (p20 != NULL) {
				    if (a > 0xfffff) {
					 php_printf("flow_id mask must be 20 bit");
					 goto end;
				    }
				    *p20 = (uint32_t)a;
			    } else if (pa6 != NULL) {
				    if (a > 128) {
					    php_printf("in6addr invalid mask len");
					   goto end;
				    }
				    else
					n2mask(pa6, a);
			    } else {
				    if (a > 0xFF) {
					php_printf("proto mask must be 8 bit");
					goto end;
				    }
				    mask->proto = (uint8_t)a;
			    }
			    if (a != 0)
				    *flags |= DN_HAVE_MASK;
			    ac--; av++;
			} /* end while, config masks */
end_mask:
			break;

		case TOK_DROPTAIL:
			NEED(fs, "droptail is only for flowsets");
			fs->flags &= ~(DN_IS_RED|DN_IS_GENTLE_RED);
			break;

		case TOK_BW:
			NEED(p, "bw is only for links");
			NEED1("bw needs bandwidth or interface\n");
			if (read_bandwidth(av[0], &p->bandwidth, NULL, 0) < 0)
				goto end;
			ac--; av++;
			break;

		case TOK_DELAY:
			NEED(p, "delay is only for links");
			NEED1("delay needs argument 0..10000ms\n");
			p->delay = strtoul(av[0], NULL, 0);
			ac--; av++;
			break;

		case TOK_TYPE: {
			int l;
			NEED(sch, "type is only for schedulers");
			NEED1("type needs a string");
			l = strlen(av[0]);
			if (l == 0 || l > 15) {
				php_printf("type %s too long\n", av[0]);
				goto end;
			}
			strcpy(sch->name, av[0]);
			sch->oid.subtype = 0; /* use string */
			ac--; av++;
			break;
		    }

		case TOK_WEIGHT:
			NEED(fs, "weight is only for flowsets");
			NEED1("weight needs argument\n");
			fs->par[0] = strtol(av[0], &end, 0);
			ac--; av++;
			break;

		case TOK_LMAX:
			NEED(fs, "lmax is only for flowsets");
			NEED1("lmax needs argument\n");
			fs->par[1] = strtol(av[0], &end, 0);
			ac--; av++;
			break;

		case TOK_PRI:
			NEED(fs, "priority is only for flowsets");
			NEED1("priority needs argument\n");
			fs->par[2] = strtol(av[0], &end, 0);
			ac--; av++;
			break;

		case TOK_SCHED:
		case TOK_PIPE:
			NEED(fs, "pipe/sched");
			NEED1("pipe/link/sched needs number\n");
			fs->sched_nr = strtoul(av[0], &end, 0);
			ac--; av++;
			break;

		default:
			php_printf("unrecognised option ``%s''", av[-1]);
			goto end;
		}
	}

	/* check validity of parameters */
	if (p) {
		if (p->delay > 10000) {
			php_printf("delay must be < 10000");
			goto end;
		}
		if (p->bandwidth == -1)
			p->bandwidth = 0;
	}
	if (fs) {
		/* XXX accept a 0 scheduler to keep the default */
	    if (fs->flags & DN_QSIZE_BYTES) {
		size_t len;
		long limit;

		len = sizeof(limit);
		if (sysctlbyname("net.inet.ip.dummynet.pipe_byte_limit",
			&limit, &len, NULL, 0) == -1)
			limit = 1024*1024;
		if (fs->qsize > limit) {
			php_printf("queue size must be < %ldB", limit);
			goto end;
		}
	    } else {
		size_t len;
		long limit;

		len = sizeof(limit);
		if (sysctlbyname("net.inet.ip.dummynet.pipe_slot_limit",
			&limit, &len, NULL, 0) == -1)
			limit = 100;
		if (fs->qsize > limit) {
			php_printf("2 <= queue size <= %ld", limit);
			goto end;
		}
	    }

	    if (fs->flags & DN_IS_RED) {
		size_t len;
		int lookup_depth, avg_pkt_size;
		double w_q;

		if (fs->min_th >= fs->max_th) {
		    php_printf("min_th %d must be < than max_th %d",
			fs->min_th, fs->max_th);
			goto end;
		}
		if (fs->max_th == 0) {
			php_printf("max_th must be > 0");
			goto end;
		}

		len = sizeof(int);
		if (sysctlbyname("net.inet.ip.dummynet.red_lookup_depth",
			&lookup_depth, &len, NULL, 0) == -1)
			lookup_depth = 256;
		if (lookup_depth == 0) {
		    php_printf("net.inet.ip.dummynet.red_lookup_depth"
			" must be greater than zero");
			goto end;
		}

		len = sizeof(int);
		if (sysctlbyname("net.inet.ip.dummynet.red_avg_pkt_size",
			&avg_pkt_size, &len, NULL, 0) == -1)
			avg_pkt_size = 512;

		if (avg_pkt_size == 0) {
			    php_printf("net.inet.ip.dummynet.red_avg_pkt_size must"
			    " be greater than zero");
			goto end;
		}

		/*
		 * Ticks needed for sending a medium-sized packet.
		 * Unfortunately, when we are configuring a WF2Q+ queue, we
		 * do not have bandwidth information, because that is stored
		 * in the parent pipe, and also we have multiple queues
		 * competing for it. So we set s=0, which is not very
		 * correct. But on the other hand, why do we want RED with
		 * WF2Q+ ?
		 */
#if 0
		if (p.bandwidth==0) /* this is a WF2Q+ queue */
			s = 0;
		else
			s = (double)ck.hz * avg_pkt_size * 8 / p.bandwidth;
#endif
		/*
		 * max idle time (in ticks) before avg queue size becomes 0.
		 * NOTA:  (3/w_q) is approx the value x so that
		 * (1-w_q)^x < 10^-3.
		 */
		w_q = ((double)fs->w_q) / (1 << SCALE_RED);
#if 0 // go in kernel
		idle = s * 3. / w_q;
		fs->lookup_step = (int)idle / lookup_depth;
		if (!fs->lookup_step)
			fs->lookup_step = 1;
		weight = 1 - w_q;
		for (t = fs->lookup_step; t > 1; --t)
			weight *= 1 - w_q;
		fs->lookup_weight = (int)(weight * (1 << SCALE_RED));
#endif
	    }
	}

	i = do_cmd(IP_DUMMYNET3, base, (char *)buf - (char *)base);

	if (i)
		php_printf("setsockopt(%s)", "IP_DUMMYNET_CONFIGURE");

	free(base);

	return (0); /* XXX: i? */
end:
	if (base != NULL)
		free(base);
	return (-1);
}
