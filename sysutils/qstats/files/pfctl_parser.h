/*	$OpenBSD: pfctl_parser.h,v 1.86 2006/10/31 23:46:25 mcbride Exp $ */

/*
 * Copyright (c) 2001 Daniel Hartmeier
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *    - Redistributions of source code must retain the above copyright
 *      notice, this list of conditions and the following disclaimer.
 *    - Redistributions in binary form must reproduce the above
 *      copyright notice, this list of conditions and the following
 *      disclaimer in the documentation and/or other materials provided
 *      with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT HOLDERS OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * $FreeBSD$
 */

#ifndef _PFCTL_PARSER_H_
#define _PFCTL_PARSER_H_

struct pfr_buffer;      /* forward definition */

struct pfctl {
        int dev;
        int opts;
        int optimize;
        int loadopt;
        int asd;                        /* anchor stack depth */
        int bn;                         /* brace number */
        int brace;
        int tdirty;                     /* kernel dirty */
#define PFCTL_ANCHOR_STACK_DEPTH 64
        struct pf_anchor *astack[PFCTL_ANCHOR_STACK_DEPTH];
        struct pfioc_pooladdr paddr;
        struct pfioc_altq *paltq;
        struct pfioc_queue *pqueue;
        struct pfr_buffer *trans;
        struct pf_anchor *anchor, *alast;
        const char *ruleset;

        /* 'set foo' options */
        u_int32_t        timeout[PFTM_MAX];
        u_int32_t        limit[PF_LIMIT_MAX];
        u_int32_t        debug;
        u_int32_t        hostid;
        char            *ifname;

        u_int8_t         timeout_set[PFTM_MAX];
        u_int8_t         limit_set[PF_LIMIT_MAX];
        u_int8_t         debug_set;
        u_int8_t         hostid_set;
        u_int8_t         ifname_set;
};

struct node_if {
	char			 ifname[IFNAMSIZ];
	u_int8_t		 not;
	u_int8_t		 dynamic; /* antispoof */
	u_int			 ifa_flags;
	struct node_if		*next;
	struct node_if		*tail;
};

struct node_host {
	struct pf_addr_wrap	 addr;
	struct pf_addr		 bcast;
	struct pf_addr		 peer;
	sa_family_t		 af;
	u_int8_t		 not;
	u_int32_t		 ifindex;	/* link-local IPv6 addrs */
	char			*ifname;
	u_int			 ifa_flags;
	struct node_host	*next;
	struct node_host	*tail;
};

struct node_queue_bw {
	u_int32_t	bw_absolute;
	u_int16_t	bw_percent;
};

struct node_hfsc_sc {
	struct node_queue_bw	m1;	/* slope of 1st segment; bps */
	u_int			d;	/* x-projection of m1; msec */
	struct node_queue_bw	m2;	/* slope of 2nd segment; bps */
	u_int8_t		used;
};

struct node_hfsc_opts {
	struct node_hfsc_sc	realtime;
	struct node_hfsc_sc	linkshare;
	struct node_hfsc_sc	upperlimit;
	int			flags;
};

struct node_fairq_sc {
	struct node_queue_bw	m1;	/* slope of 1st segment; bps */
	u_int			d;	/* x-projection of m1; msec */
	struct node_queue_bw	m2;	/* slope of 2nd segment; bps */
	u_int8_t		used;
};

struct node_fairq_opts {
	struct node_fairq_sc	linkshare;
	struct node_queue_bw	hogs_bw;
	u_int			nbuckets;
	int			flags;
};

struct node_queue_opt {
	int			 qtype;
	union {
		struct cbq_opts		cbq_opts;
		struct priq_opts	priq_opts;
		struct node_hfsc_opts	hfsc_opts;
		struct node_fairq_opts	fairq_opts;
	}			 data;
};

#ifdef __FreeBSD__
/*
 * XXX
 * Absolutely this is not correct location to define this.
 * Should we use an another sperate header file?
 */
#define	SIMPLEQ_HEAD			STAILQ_HEAD
#define	SIMPLEQ_HEAD_INITIALIZER	STAILQ_HEAD_INITIALIZER
#define	SIMPLEQ_ENTRY			STAILQ_ENTRY
#define	SIMPLEQ_FIRST			STAILQ_FIRST
#define	SIMPLEQ_END(head)		NULL
#define	SIMPLEQ_EMPTY			STAILQ_EMPTY
#define	SIMPLEQ_NEXT			STAILQ_NEXT
/*#define	SIMPLEQ_FOREACH			STAILQ_FOREACH*/
#define	SIMPLEQ_FOREACH(var, head, field)		\
    for((var) = SIMPLEQ_FIRST(head);			\
	(var) != SIMPLEQ_END(head);			\
	(var) = SIMPLEQ_NEXT(var, field))
#define	SIMPLEQ_INIT			STAILQ_INIT
#define	SIMPLEQ_INSERT_HEAD		STAILQ_INSERT_HEAD
#define	SIMPLEQ_INSERT_TAIL		STAILQ_INSERT_TAIL
#define	SIMPLEQ_INSERT_AFTER		STAILQ_INSERT_AFTER
#define	SIMPLEQ_REMOVE_HEAD		STAILQ_REMOVE_HEAD
#endif
SIMPLEQ_HEAD(node_tinithead, node_tinit);
struct node_tinit {	/* table initializer */
	SIMPLEQ_ENTRY(node_tinit)	 entries;
	struct node_host		*host;
	char				*file;
};


TAILQ_HEAD(pf_opt_queue, pf_opt_rule);

int	eval_pfaltq(struct pfctl *, struct pf_altq *, struct node_queue_bw *,
	    struct node_queue_opt *);
int	eval_pfqueue(struct pfctl *, struct pf_altq *, struct node_queue_bw *,
	    struct node_queue_opt *);

#define PFCTL_FLAG_FILTER	0x02
#define PFCTL_FLAG_NAT		0x04
#define PFCTL_FLAG_OPTION	0x08
#define PFCTL_FLAG_ALTQ		0x10
#define PFCTL_FLAG_TABLE	0x20

#endif /* _PFCTL_PARSER_H_ */
