/*	$OpenBSD: pfctl_qstats.c,v 1.30 2004/04/27 21:47:32 kjc Exp $ */

/*
 * Copyright (c) Henning Brauer <henning@openbsd.org>
 *
 * Permission to use, copy, modify, and distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

#include <sys/cdefs.h>
__FBSDID("$FreeBSD$");

#include <sys/types.h>
#include <sys/ioctl.h>
#include <sys/param.h>
#include <sys/socket.h>
#include <sys/un.h>
#include <sys/sbuf.h>

#include <net/if.h>
#include <netinet/in.h>
#include <net/pfvar.h>
#include <arpa/inet.h>

#include <stdarg.h>
#define _WITH_DPRINTF
#include <stdio.h>
#include <stdlib.h>
#include <fcntl.h>
#include <string.h>
#include <unistd.h>
#include <syslog.h>
#include <errno.h>

#if defined(__FreeBSD__) && __FreeBSD_version >= 1100000
#include <net/altq/altq.h>
#include <net/altq/altq_cbq.h>
#include <net/altq/altq_priq.h>
#include <net/altq/altq_hfsc.h>
#include <net/altq/altq_fairq.h>
#include <net/altq/altq_codel.h>
#else
#include <altq/altq.h>
#include <altq/altq_cbq.h>
#include <altq/altq_priq.h>
#include <altq/altq_hfsc.h>
#include <altq/altq_fairq.h>
#include <altq/altq_codel.h>
#endif

int      pfctl_show_altq(int, const char *, int, int);

#ifndef DEFAULT_PRIORITY
#define DEFAULT_PRIORITY        1
#endif

#ifndef DEFAULT_QLIMIT
#define DEFAULT_QLIMIT          50
#endif
#include "pfctl_parser.h"

union class_stats {
	class_stats_t		cbq_stats;
	struct priq_classstats	priq_stats;
	struct hfsc_classstats	hfsc_stats;
	struct fairq_classstats fairq_stats;
	struct codel_ifstats	codel_stats;
};

#define AVGN_MAX	8
#define STAT_INTERVAL	5

struct queue_stats {
	union class_stats	 data;
	int			 avgn;
	double			 avg_bytes;
	double			 avg_packets;
	u_int64_t		 prev_bytes;
	u_int64_t		 prev_packets;
};

struct pf_altq_node {
	struct pf_altq		 altq;
	struct pf_altq_node	*next;
	struct pf_altq_node	*children;
	struct queue_stats	 qstats;
};

int			 pfctl_update_qstats(int, struct pf_altq_node **);
void			 pfctl_insert_altq_node(struct pf_altq_node **,
			    const struct pf_altq, const struct queue_stats);
struct pf_altq_node	*pfctl_find_altq_node(struct pf_altq_node *,
			    const char *, const char *);
void			 pfctl_print_altq_node(int, const struct pf_altq_node *,
			    unsigned, struct sbuf *);
void			 print_cbqstats(struct queue_stats, struct sbuf *sb, int);
void			 print_codelstats(struct queue_stats, struct sbuf *sb, int);
void			 print_priqstats(struct queue_stats, struct sbuf *sb, int);
void			 print_hfscstats(struct queue_stats, struct sbuf *sb, int);
void			 print_fairqstats(struct queue_stats, struct sbuf *sb, int);
void			 pfctl_free_altq_node(struct pf_altq_node *);
void                     pfctl_print_altq_nodestat(int,
                            const struct pf_altq_node *, struct sbuf *sb, int);

void			 update_avg(struct pf_altq_node *);

int main(int argc, char **argv)
{
	struct pf_altq_node	*root = NULL, *node;
	int			 nodes, ch, req, fd;
	int			 debug = 0, dev = -1;
	char			*iface = NULL, *pidfile = NULL, buf[4096];
	struct sbuf *sb;
	struct sockaddr_un sun, sun1;
	socklen_t len;
                
	while ((ch = getopt(argc, argv, "di:hp:")) != -1) {
                switch(ch) {
                case 'd':
                        debug++;
                        break;
                case 'i':
                        iface = strdup(optarg);
                        break;
		case 'p':
			pidfile = strdup(optarg);
			break;
                case 'h':
                default:
                        //usage((const char *)*argv);
                        return (-1);
                }
        }
        argc -= optind;
        argv += optind;

	if (debug <= 0) {
		closefrom(3);

		if (daemon(0, 1) != 0) {
			syslog(LOG_ERR, "unable to daemonize");
			return (-1);
		}
	}

	dev = open("/dev/pf", O_RDWR);
	if (dev < 0) {
		syslog(LOG_ERR, "could not open pf(4) device for operation");
		return (-1);
	}

	if (pidfile) {
		fd = open(pidfile, O_RDWR|O_CREAT|O_TRUNC);
		if (fd < 0) {
			syslog(LOG_ERR, "Could not open pidfile");
			close(dev);
			return (-1);
		}
		dprintf(fd, "%d\n", getpid());
		close(fd);
	}

	fd = socket(AF_UNIX, SOCK_STREAM, 0);
	if (fd < 0) {
		syslog(LOG_ERR, "Could not create listening socket");
		close(dev);
		return (-1);
	}

	unlink("/var/run/qstats");

	bzero(&sun, sizeof(sun));
        sun.sun_family = PF_UNIX;
        strlcpy(sun.sun_path, "/var/run/qstats", sizeof(sun.sun_path));
        if (bind(fd, (struct sockaddr *)&sun, sizeof(sun)) < 0) {
                syslog(LOG_ERR, "Could not listen for requests");
                close(fd);
		close(dev);
                return (-1);
        }

        if (listen(fd, 30) == -1) {
                syslog(LOG_ERR, "control_listen: listen");
                close(fd);
		close(dev);
                return (-1);
        }

	for (;;) {
		req = accept(fd, (struct sockaddr *)&sun1, &len);
		if (req < 0) {
			if (errno != EWOULDBLOCK && errno != EINTR)
				syslog(LOG_NOTICE, "problems on accept");
			continue;
		}
		sb = sbuf_new_auto();

		sbuf_printf(sb, "<altqstats>\n");
		if ((nodes = pfctl_update_qstats(dev, &root)) >= 0) {
			for (node = root; node != NULL; node = node->next) {
				if (iface != NULL && strcmp(node->altq.ifname, iface))
					continue;
				if (node->altq.local_flags & PFALTQ_FLAG_IF_REMOVED)
					continue;
				pfctl_print_altq_node(dev, node, 0, sb);
			}
		}
		sbuf_printf(sb, "</altqstats>\n");
		sbuf_finish(sb);
		dprintf(req, "%s\n", sbuf_data(sb));
		sbuf_delete(sb);
		close(req);
	}

	pfctl_free_altq_node(root);
	if (iface)
		free(iface);
	if (pidfile)
		free(pidfile);
	close(fd);
	close(dev);
	return (0);
}

/*
 * misc utilities
 */
#define R2S_BUFS        8
#define RATESTR_MAX     16
                
static char *
rate2str(double rate)
{
        char            *buf;
        static char      r2sbuf[R2S_BUFS][RATESTR_MAX];  /* ring bufer */
        static int       idx = 0;
        int              i;
        static const char unit[] = " KMG";
                
        buf = r2sbuf[idx++];
        if (idx == R2S_BUFS)
                idx = 0;
                
        for (i = 0; rate >= 1000 && i <= 3; i++)
                rate /= 1000;
                
        if ((int)(rate * 100) % 100)
                snprintf(buf, RATESTR_MAX, "%.2f%cb", rate, unit[i]);
        else
                snprintf(buf, RATESTR_MAX, "%d%cb", (int)rate, unit[i]);
                 
        return (buf);
}

static void
print_queue(const struct pf_altq *a, unsigned int level,
    struct node_queue_bw *bw, int print_interface,
    struct node_queue_opt *qopts __unused, struct sbuf *sb)
{
        unsigned int    i;
	char buf[20] = { 0 };

        if (a->local_flags & PFALTQ_FLAG_IF_REMOVED)
                sbuf_printf(sb, "<status>INACTIVE</status>");

        for (i = 0; i < level; ++i)
                buf[i] = '\t';
	sbuf_printf(sb, "%s<name>%s</name>\n", buf, a->qname);
        if (print_interface)
		sbuf_printf(sb, "%s<interface>%s</interface>\n", buf, a->ifname);
        if (a->scheduler == ALTQT_CBQ || a->scheduler == ALTQT_HFSC ||
                a->scheduler == ALTQT_FAIRQ) {
                if (bw != NULL && bw->bw_percent > 0) {
                        if (bw->bw_percent < 100)
				sbuf_printf(sb, "%s<bandwidth>%u%%</bandwidth>\n", buf, bw->bw_percent);
                } else
			sbuf_printf(sb, "%s<bandwidth>%s</bandwidth>\n", buf, rate2str((double)a->ifbandwidth));
        }       
        if (a->priority != DEFAULT_PRIORITY)
		sbuf_printf(sb, "%s<priority>%u</priority>\n", buf, a->priority);
        if (a->qlimit != DEFAULT_QLIMIT)
		sbuf_printf(sb, "%s<qlimit>%u</qlimit>\n", buf, a->qlimit);
}

static void
print_altq(const struct pf_altq *a, unsigned int level,
    struct node_queue_bw *bw, struct node_queue_opt *qopts, struct sbuf *sb)
{
        if (a->qname[0] != 0) {
                print_queue(a, level, bw, 1, qopts, sb);
                return;
        }

        if (a->local_flags & PFALTQ_FLAG_IF_REMOVED)
                sbuf_printf(sb, "INACTIVE ");

	sbuf_printf(sb, "\t<interface>%s</interface>\n", a->ifname);

        switch (a->scheduler) {
        case ALTQT_CBQ:
		sbuf_printf(sb, "\t<scheduler>%s</scheduler>\n", "cbq");
                break;
        case ALTQT_PRIQ:
		sbuf_printf(sb, "\t<scheduler>%s</scheduler>\n", "priq");
                break;
        case ALTQT_HFSC:
		sbuf_printf(sb, "\t<scheduler>%s</scheduler>\n", "hfsc");
                break;
        case ALTQT_FAIRQ:
		sbuf_printf(sb, "\t<scheduler>%s</scheduler>\n", "fairq");
                break;
        case ALTQT_CODEL:
		sbuf_printf(sb, "\t<scheduler>%s</scheduler>\n", "codelq");
                break;
        }

        if (bw != NULL && bw->bw_percent > 0) {
                if (bw->bw_percent < 100)
                        sbuf_printf(sb, "\t<bandwidth>%u%%</bandwidth>\n", bw->bw_percent);
        } else
                sbuf_printf(sb, "\t<bandwidth>%s</bandwidth>\n", rate2str((double)a->ifbandwidth));

        if (a->qlimit != DEFAULT_QLIMIT)
                sbuf_printf(sb, "\t<qlimit>%u</qlimit>\n", a->qlimit);
        sbuf_printf(sb, "\t<tbrsize>%u</tbrsize>", a->tbrsize);
}


int
pfctl_update_qstats(int dev, struct pf_altq_node **root)
{
	struct pf_altq_node	*node;
	struct pfioc_altq	 pa;
	struct pfioc_qstats	 pq;
	u_int32_t		 mnr, nr;
	struct queue_stats	 qstats;
	static	u_int32_t	 last_ticket;

	memset(&pa, 0, sizeof(pa));
	memset(&pq, 0, sizeof(pq));
	memset(&qstats, 0, sizeof(qstats));
	if (ioctl(dev, DIOCGETALTQS, &pa)) {
		syslog(LOG_ERR, "Problem with DIOCGETALTQS");
		return (-1);
	}

	/* if a new set is found, start over */
	if (pa.ticket != last_ticket && *root != NULL) {
		pfctl_free_altq_node(*root);
		*root = NULL;
	}
	last_ticket = pa.ticket;

	mnr = pa.nr;
	for (nr = 0; nr < mnr; ++nr) {
		pa.nr = nr;
		if (ioctl(dev, DIOCGETALTQ, &pa)) {
			syslog(LOG_ERR, "Problem with DIOCGETALTQ");
			return (-1);
		}
		if ((pa.altq.qid > 0 || pa.altq.scheduler == ALTQT_CODEL) &&
		    !(pa.altq.local_flags & PFALTQ_FLAG_IF_REMOVED)) {
			pq.nr = nr;
			pq.ticket = pa.ticket;
			pq.buf = &qstats.data;
			pq.nbytes = sizeof(qstats.data);
			if (ioctl(dev, DIOCGETQSTATS, &pq)) {
				syslog(LOG_ERR, "DIOCGETQSTATS");
				return (-1);
			}
			if ((node = pfctl_find_altq_node(*root, pa.altq.qname,
			    pa.altq.ifname)) != NULL) {
				memcpy(&node->qstats.data, &qstats.data,
				    sizeof(qstats.data));
				update_avg(node);
			} else {
				pfctl_insert_altq_node(root, pa.altq, qstats);
			}
		} else if (pa.altq.local_flags & PFALTQ_FLAG_IF_REMOVED) {
			memset(&qstats.data, 0, sizeof(qstats.data));
			if ((node = pfctl_find_altq_node(*root, pa.altq.qname,
			    pa.altq.ifname)) != NULL) {
				memcpy(&node->qstats.data, &qstats.data,
				    sizeof(qstats.data));
				update_avg(node);
			} else {
				pfctl_insert_altq_node(root, pa.altq, qstats);
			}
		}
	}
	return (mnr);
}

void
pfctl_insert_altq_node(struct pf_altq_node **root,
    const struct pf_altq altq, const struct queue_stats qstats)
{
	struct pf_altq_node	*node;

	node = calloc(1, sizeof(struct pf_altq_node));
	if (node == NULL) {
		syslog(LOG_ERR, "pfctl_insert_altq_node: calloc");
		return;
	}
	memcpy(&node->altq, &altq, sizeof(struct pf_altq));
	memcpy(&node->qstats, &qstats, sizeof(qstats));
	node->next = node->children = NULL;

	if (*root == NULL)
		*root = node;
	else if (!altq.parent[0]) {
		struct pf_altq_node	*prev = *root;

		while (prev->next != NULL)
			prev = prev->next;
		prev->next = node;
	} else {
		struct pf_altq_node	*parent;

		parent = pfctl_find_altq_node(*root, altq.parent, altq.ifname);
		if (parent == NULL) {
			syslog(LOG_ERR, "parent %s not found", altq.parent);
			return;
		}
		if (parent->children == NULL)
			parent->children = node;
		else {
			struct pf_altq_node *prev = parent->children;

			while (prev->next != NULL)
				prev = prev->next;
			prev->next = node;
		}
	}
	update_avg(node);
}

struct pf_altq_node *
pfctl_find_altq_node(struct pf_altq_node *root, const char *qname,
    const char *ifname)
{
	struct pf_altq_node	*node, *child;

	for (node = root; node != NULL; node = node->next) {
		if (!strcmp(node->altq.qname, qname)
		    && !(strcmp(node->altq.ifname, ifname)))
			return (node);
		if (node->children != NULL) {
			child = pfctl_find_altq_node(node->children, qname,
			    ifname);
			if (child != NULL)
				return (child);
		}
	}
	return (NULL);
}

void
pfctl_print_altq_node(int dev, const struct pf_altq_node *node,
    unsigned int level, struct sbuf *sb)
{
	const struct pf_altq_node	*child;
	char buf[20] = { 0 };
	int i;
 
	if (node == NULL)
		return;

        for (i = 0; i < level; ++i)
                buf[i] = '\t';

	sbuf_printf(sb, "%s<queue>\n", buf);
	print_altq(&node->altq, level + 1, NULL, NULL, sb);

#if 0
	if (node->children != NULL) {
		sbuf_printf(sb, "{");
		for (child = node->children; child != NULL;
		    child = child->next) {
			sbuf_printf(sb, "%s", child->altq.qname);
			if (child->next != NULL)
				sbuf_printf(sb, ", ");
		}
		sbuf_printf(sb, "}");
	}
	sbuf_printf(sb, "\n");

	pfctl_print_altq_nodestat(dev, node, sb, level);

	sbuf_printf(sb, "  [ qid=%u ifname=%s ifbandwidth=%s ]\n",
	    node->altq.qid, node->altq.ifname,
	    rate2str((double)(node->altq.ifbandwidth)));
#endif
	pfctl_print_altq_nodestat(dev, node, sb, level + 1);
	

	for (child = node->children; child != NULL;
	    child = child->next)
		pfctl_print_altq_node(dev, child, level + 1, sb);
	sbuf_printf(sb, "%s</queue>\n", buf);
}

void
pfctl_print_altq_nodestat(int dev __unused, const struct pf_altq_node *a, struct sbuf *sb, int level)
{
	if (a->altq.qid == 0 && a->altq.scheduler != ALTQT_CODEL)
		return;

	if (a->altq.local_flags & PFALTQ_FLAG_IF_REMOVED)
		return;

	switch (a->altq.scheduler) {
	case ALTQT_CBQ:
		print_cbqstats(a->qstats, sb, level);
		break;
	case ALTQT_PRIQ:
		print_priqstats(a->qstats, sb, level);
		break;
	case ALTQT_HFSC:
		print_hfscstats(a->qstats, sb, level);
		break;
	case ALTQT_FAIRQ:
		print_fairqstats(a->qstats, sb, level);
		break;
	case ALTQT_CODEL:
		print_codelstats(a->qstats, sb, level);
		break;
	}
}

void
print_cbqstats(struct queue_stats cur, struct sbuf *sb, int level)
{
	int i;

        for (i = 0; i < level; ++i)
                sbuf_printf(sb, "\t");

	sbuf_printf(sb, "<pkts>%llu</pkts><bytes>%llu</bytes>"
	    "<droppedpkts>%llu</droppedpkts><droppedbytes>%llu</droppedbytes>",
	    (unsigned long long)cur.data.cbq_stats.xmit_cnt.packets,
	    (unsigned long long)cur.data.cbq_stats.xmit_cnt.bytes,
	    (unsigned long long)cur.data.cbq_stats.drop_cnt.packets,
	    (unsigned long long)cur.data.cbq_stats.drop_cnt.bytes);
	sbuf_printf(sb, "<qlength>%d/%d</qlength><borrows>%u</borrows><suspends>%u</suspends>",
	    cur.data.cbq_stats.qcnt, cur.data.cbq_stats.qmax,
	    cur.data.cbq_stats.borrows, cur.data.cbq_stats.delays);

	if (cur.avgn < 2) {
		sbuf_printf(sb, "\n");
		return;
	}

	sbuf_printf(sb, "<measured>%.1f</measured><measuredspeed>%s</measuredspeed><measuredspeedint>%.1f</measuredspeedint>\n",
	    cur.avg_packets / STAT_INTERVAL,
	    rate2str((8 * cur.avg_bytes) / STAT_INTERVAL),
		(8 * cur.avg_bytes) / STAT_INTERVAL
		);
}

void
print_codelstats(struct queue_stats cur, struct sbuf *sb, int level)
{
	sbuf_printf(sb, "<pkts>%llu</pkts><bytes>%llu</bytes>"
		"<droppedpkts>%llu</droppedpkts><droppedbytes>%llu</droppedbytes>",
		(unsigned long long)cur.data.codel_stats.cl_xmitcnt.packets,
		(unsigned long long)cur.data.codel_stats.cl_xmitcnt.bytes,
		(unsigned long long)cur.data.codel_stats.cl_dropcnt.packets + cur.data.codel_stats.stats.drop_cnt.packets,
		(unsigned long long)cur.data.codel_stats.cl_dropcnt.bytes  + cur.data.codel_stats.stats.drop_cnt.bytes);
	sbuf_printf(sb, "<qlength>%d/%d</qlength>",
		cur.data.codel_stats.qlength, cur.data.codel_stats.qlimit);

	if (cur.avgn < 2)
		return;

	sbuf_printf(sb, "<measured>%.1f</measured><measuredspeed>%s</measuredspeed><measuredspeedint>%.1f</measuredspeedint>\n",
		cur.avg_packets / STAT_INTERVAL,
		rate2str((8 * cur.avg_bytes) / STAT_INTERVAL),
		(8 * cur.avg_bytes) / STAT_INTERVAL
		);
}

void
print_priqstats(struct queue_stats cur, struct sbuf *sb, int level)
{
	int i;

        for (i = 0; i < level; ++i)
                sbuf_printf(sb, "\t");

	sbuf_printf(sb, "<pkts>%llu</pkts><bytes>%llu</bytes>"
	    "<droppedpkts>%llu</droppedpkts><droppedbytes>%llu</droppedbytes>",
	    (unsigned long long)cur.data.priq_stats.xmitcnt.packets,
	    (unsigned long long)cur.data.priq_stats.xmitcnt.bytes,
	    (unsigned long long)cur.data.priq_stats.dropcnt.packets,
	    (unsigned long long)cur.data.priq_stats.dropcnt.bytes);
	sbuf_printf(sb, "<qlength>%d/%d</qlength>",
	    cur.data.priq_stats.qlength, cur.data.priq_stats.qlimit);

	if (cur.avgn < 2) {
		sbuf_printf(sb, "\n");
		return;
	}

	sbuf_printf(sb, "<measured>%.1f</measured><measuredspeed>%s</measuredspeed><measuredspeedint>%.1f</measuredspeedint>\n",
	    cur.avg_packets / STAT_INTERVAL,
	    rate2str((8 * cur.avg_bytes) / STAT_INTERVAL),
		(8 * cur.avg_bytes) / STAT_INTERVAL
		);		
}

void
print_hfscstats(struct queue_stats cur, struct sbuf *sb, int level)
{
	int i;

        for (i = 0; i < level; ++i)
                sbuf_printf(sb, "\t");

	sbuf_printf(sb, "<pkts>%llu</pkts><bytes>%llu</bytes>"
	    "<droppedpkts>%llu</droppedpkts><droppedbytes>%llu</droppedbytes>",
	    (unsigned long long)cur.data.hfsc_stats.xmit_cnt.packets,
	    (unsigned long long)cur.data.hfsc_stats.xmit_cnt.bytes,
	    (unsigned long long)cur.data.hfsc_stats.drop_cnt.packets,
	    (unsigned long long)cur.data.hfsc_stats.drop_cnt.bytes);
	sbuf_printf(sb, "<qlength>%d/%d</qlength>",
	    cur.data.hfsc_stats.qlength, cur.data.hfsc_stats.qlimit);

	if (cur.avgn < 2) {
		sbuf_printf(sb, "\n");
		return;
	}

	sbuf_printf(sb, "<measured>%.1f</measured><measuredspeed>%s</measuredspeed><measuredspeedint>%.1f</measuredspeedint>\n",
	    cur.avg_packets / STAT_INTERVAL,
	    rate2str((8 * cur.avg_bytes) / STAT_INTERVAL),
		(8 * cur.avg_bytes) / STAT_INTERVAL
		);		
}

void
print_fairqstats(struct queue_stats cur, struct sbuf *sb, int level)
{
	int i;

        for (i = 0; i < level; ++i)
                sbuf_printf(sb, "\t");

	sbuf_printf(sb, "<pkts>%llu</pkts><bytes>%llu</bytes>"
	    "<droppedpkts>%llu</droppedpkts><droppedbytes>%llu</droppedbytes>",
	    (unsigned long long)cur.data.fairq_stats.xmit_cnt.packets,
	    (unsigned long long)cur.data.fairq_stats.xmit_cnt.bytes,
	    (unsigned long long)cur.data.fairq_stats.drop_cnt.packets,
	    (unsigned long long)cur.data.fairq_stats.drop_cnt.bytes);
	sbuf_printf(sb, "<qlength>%d/%d</qlength>",
	    cur.data.fairq_stats.qlength, cur.data.fairq_stats.qlimit);

	if (cur.avgn < 2) {
		sbuf_printf(sb, "\n");
		return;
	}

	sbuf_printf(sb, "<measured>%.1f</measured><measuredspeed>%s</measuredspeed><measuredspeedint>%.1f</measuredspeedint>\n",
	    cur.avg_packets / STAT_INTERVAL,
	    rate2str((8 * cur.avg_bytes) / STAT_INTERVAL),
		(8 * cur.avg_bytes) / STAT_INTERVAL
		);		
}

void
pfctl_free_altq_node(struct pf_altq_node *node)
{
	while (node != NULL) {
		struct pf_altq_node	*prev;

		if (node->children != NULL)
			pfctl_free_altq_node(node->children);
		prev = node;
		node = node->next;
		free(prev);
	}
}

void
update_avg(struct pf_altq_node *a)
{
	struct queue_stats	*qs;
	u_int64_t		 b, p;
	int			 n;

	if (a->altq.qid == 0 && a->altq.scheduler != ALTQT_CODEL)
		return;

	qs = &a->qstats;
	n = qs->avgn;

	switch (a->altq.scheduler) {
	case ALTQT_CBQ:
		b = qs->data.cbq_stats.xmit_cnt.bytes;
		p = qs->data.cbq_stats.xmit_cnt.packets;
		break;
	case ALTQT_PRIQ:
		b = qs->data.priq_stats.xmitcnt.bytes;
		p = qs->data.priq_stats.xmitcnt.packets;
		break;
	case ALTQT_HFSC:
		b = qs->data.hfsc_stats.xmit_cnt.bytes;
		p = qs->data.hfsc_stats.xmit_cnt.packets;
		break;
	case ALTQT_FAIRQ:
		b = qs->data.fairq_stats.xmit_cnt.bytes;
		p = qs->data.fairq_stats.xmit_cnt.packets;
		break;
	case ALTQT_CODEL:
		b = qs->data.codel_stats.cl_xmitcnt.bytes;
		p = qs->data.codel_stats.cl_xmitcnt.packets;
		break;
	default:
		b = 0;
		p = 0;
		break;
	}

	if (n == 0) {
		qs->prev_bytes = b;
		qs->prev_packets = p;
		qs->avgn++;
		return;
	}

	if (b >= qs->prev_bytes)
		qs->avg_bytes = ((qs->avg_bytes * (n - 1)) +
		    (b - qs->prev_bytes)) / n;

	if (p >= qs->prev_packets)
		qs->avg_packets = ((qs->avg_packets * (n - 1)) +
		    (p - qs->prev_packets)) / n;

	qs->prev_bytes = b;
	qs->prev_packets = p;
	if (n < AVGN_MAX)
		qs->avgn++;
}
