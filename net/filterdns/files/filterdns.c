/*
 * Copyright (C) 2009 - 2011 Ermal Luçi
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

#include <sys/types.h>
#include <sys/param.h>
#include <sys/socket.h>
#include <sys/ioctl.h>
#include <sys/queue.h>

#include <net/if.h>
#include <net/pfvar.h>
#include <net/ethernet.h>
#include <netinet/in.h>
#include <arpa/inet.h>
#include <netinet/ip_fw.h>

#include <stdio.h>
#include <stddef.h>
#include <stdlib.h>
#include <unistd.h>
#include <netdb.h>
#include <pthread.h>
#include <pthread_np.h>
#include <syslog.h>
#include <stdarg.h>
#include <err.h>
#include <sysexits.h>
#include <string.h>
#include <fcntl.h>
#include <errno.h>

#include "filterdns.h"

static int interval = 30;
static int dev = -1;
static int debug = 0;
static int ipfwctx;
static char *file = NULL;
static pthread_attr_t attr;
static pthread_cond_t sig_condvar;
static pthread_mutex_t sig_mtx;
static pthread_t sig_thr;
static pthread_rwlock_t main_lock;

static int pf_tableentry(struct thread_data *, struct sockaddr *, int);
static int ipfw_tableentry(struct thread_data *, struct sockaddr *, int);
static int host_dns(struct thread_data *, int);
static int filterdns_clean_table(struct thread_data *, int);
static void clear_config(struct thread_list *);
static void clear_hostname_addresses(struct thread_data *);
static int filterdns_check_sameip_diff_hostname(struct thread_data *, struct table *);

#define DELETE 1
#define ADD 2
#define TABLENAME(name)	name != NULL ? name : ""

#define satosin(sa)	((struct sockaddr_in *)(sa))
#define satosin6(sa)	((struct sockaddr_in6 *)(sa))

#if 0
	static void
flush_table(char *tablename)
{
	struct pfioc_table io;

	memset(&io, 0, sizeof(io));
	if (strlcpy(io.pfrio_table.pfrt_name, tablename,
				sizeof(io.pfrio_table.pfrt_name)) >=
			sizeof(io.pfrio_table.pfrt_name))
		return; /* XXX */
	/* pfctl -Tflush */
	if (ioctl(dev, DIOCRCLRADDRS, &io) == -1)
		syslog(LOG_WARNING, "Cannot flush table %s addresses", tablename);
}
#endif

static int
get_present_table_entries(struct thread_data *thr)
{
	struct pfioc_table io;
	struct pfr_table *table;
	struct pfr_addr *addr;
	struct table *ent;
	int i;

	memset(&io, 0, sizeof(io));
	table = &io.pfrio_table;
	memset(table, 0, sizeof(*table));

	if (strlcpy(table->pfrt_name, thr->tablename, sizeof(table->pfrt_name)) >= sizeof(table->pfrt_name))
		return (-1);

	io.pfrio_buffer = NULL;
	io.pfrio_esize = sizeof(struct pfr_addr);

	if (ioctl(dev, DIOCRGETADDRS, &io) < 0) {
		if (debug >= 3)
			syslog(LOG_WARNING, "\tCould not get number of entries from table %s", TABLENAME(thr->tablename));
		free(io.pfrio_buffer);
		io.pfrio_buffer = NULL;
		return (-1);
	}
	if (debug >= 3)
		syslog(LOG_WARNING, "\tTable %s has %u entries", TABLENAME(thr->tablename), io.pfrio_size);
	io.pfrio_buffer = calloc(1, io.pfrio_size * io.pfrio_esize);
	if (io.pfrio_buffer == NULL)
		return (-1);

	if (debug >= 3)
		syslog(LOG_WARNING, "\tTable %s has %u entries", TABLENAME(thr->tablename), io.pfrio_size);

	if (ioctl(dev, DIOCRGETADDRS, &io) < 0) {
		if (debug >= 3)
			syslog(LOG_WARNING, "\tCould not retrieve entries from table %s", TABLENAME(thr->tablename));
		free(io.pfrio_buffer);
		io.pfrio_buffer = NULL;
		return (-1);
	}

	if (debug >= 3)
		syslog(LOG_WARNING, "\tFetched %s has %u entries", TABLENAME(thr->tablename), io.pfrio_size);

	addr = io.pfrio_buffer;
	for (i = 0; i < io.pfrio_size; i++) {
		ent = calloc(1, sizeof(*ent));
		if (ent == NULL) {
			if (debug >= 3)
				syslog(LOG_ERR, "\tCould not allocate one entry retrying");
			continue;
		}

		if (addr[i].pfra_af == AF_INET)
			ent->addr = calloc(1, sizeof(struct sockaddr_in));
		else
			ent->addr = calloc(1, sizeof(struct sockaddr_in6));
		if (ent->addr == NULL) {
			free(ent);
			if (debug >= 3)
				syslog(LOG_WARNING, "\tFailed to allocate new address entry for table %s.", TABLENAME(thr->tablename));
			continue;
		}
		if (addr[i].pfra_af == AF_INET) {
			ent->addr->sa_len = sizeof(struct sockaddr_in);
			ent->addr->sa_family = AF_INET;
			((struct sockaddr_in *)ent->addr)->sin_addr = addr[i].pfra_ip4addr;
		} else {
			ent->addr->sa_len = sizeof(struct sockaddr_in6);
			ent->addr->sa_family = AF_INET6;
			((struct sockaddr_in6 *)ent->addr)->sin6_addr = addr[i].pfra_ip6addr;
		}
		if (filterdns_check_sameip_diff_hostname(thr, ent) != EEXIST)
			TAILQ_INSERT_HEAD(&thr->static_rnh, ent, entry);
	}
	free(io.pfrio_buffer);

	if (debug >=3)
		syslog(LOG_WARNING, "\tTable fetching finished");
	return (io.pfrio_size);
}

static int
need_to_monitor(struct thread_data *thr, struct sockaddr *addr)
{
	struct table *tmp;
	char buffer[INET6_ADDRSTRLEN] = { 0 };

	if (!TAILQ_EMPTY(&thr->static_rnh)) {
		TAILQ_FOREACH(tmp, &thr->static_rnh, entry) {
			if (tmp->addr->sa_family != addr->sa_family)
				continue;
			if (!memcmp(addr, tmp->addr, addr->sa_len)) {
				if (debug >= 2) {
					if (addr->sa_family == AF_INET)
						syslog(LOG_WARNING, "\t\tentry %s is static on table %s", inet_ntop(addr->sa_family, &satosin(addr)->sin_addr.s_addr, buffer, sizeof buffer), TABLENAME(thr->tablename));
					else if (addr->sa_family == AF_INET6)
						syslog(LOG_WARNING, "\t\tentry %s is static on table %s", inet_ntop(addr->sa_family, satosin6(addr)->sin6_addr.s6_addr, buffer, sizeof buffer), TABLENAME(thr->tablename));
				}
				return (0);
			}
		}
	}

	return (1);
}

static int
add_table_entry(struct thread_data *thrdata, struct sockaddr *addr, int forceupdate)
{
	struct table *ent, *tmp;
	char buffer[INET6_ADDRSTRLEN] = { 0 };
	int error = 0;

	TAILQ_FOREACH(tmp, &thrdata->rnh, entry) {
		if (tmp->addr->sa_family != addr->sa_family)
			continue;
		if (!memcmp(addr, tmp->addr, addr->sa_len)) {
			if (debug >= 2) {
				if (addr->sa_family == AF_INET)
					syslog(LOG_WARNING, "\t\tentry %s exists in table %s", inet_ntop(addr->sa_family, &satosin(addr)->sin_addr.s_addr, buffer, sizeof buffer), TABLENAME(thrdata->tablename));
				else if (addr->sa_family == AF_INET6)
					syslog(LOG_WARNING, "\t\tentry %s exists in table %s", inet_ntop(addr->sa_family, satosin6(addr)->sin6_addr.s6_addr, buffer, sizeof buffer), TABLENAME(thrdata->tablename));
			}
			tmp->refcnt++;
			if (forceupdate) {
				if (thrdata->type == PF_TYPE) {
					if (debug >= 2) {
						if(addr->sa_family == AF_INET)
							syslog(LOG_WARNING, "\tREFRESHING entry %s on table %s for host %s", inet_ntop(addr->sa_family, addr->sa_data + 2, buffer, sizeof buffer), TABLENAME(thrdata->tablename), thrdata->hostname);
						if(addr->sa_family == AF_INET6)
							syslog(LOG_WARNING, "\tREFRESHING entry %s on table %s for host %s", inet_ntop(addr->sa_family, addr->sa_data + 6, buffer, sizeof buffer), TABLENAME(thrdata->tablename), thrdata->hostname);
					}
					error = pf_tableentry(thrdata, addr, ADD);
				}
				else if (thrdata->type == IPFW_TYPE) {
					if (debug >= 2)
						syslog(LOG_WARNING, "\tadding entry %s to table %s on host %s", inet_ntop(addr->sa_family, addr->sa_data + 2, buffer, sizeof buffer), TABLENAME(thrdata->tablename), thrdata->hostname);
					error = ipfw_tableentry(thrdata, addr, ADD);
				}
				if (error > 0)
					return (EPIPE);
			}
			return (EEXIST);
		}
	}

	ent = calloc(1, sizeof(*ent));
	if (ent == NULL) {
		syslog(LOG_ERR, "\tFILTERDNS: Failed to allocate new entry for table %s.", TABLENAME(thrdata->tablename));
		return (ENOMEM);
	}
	ent->addr = calloc(1, addr->sa_len);
	if (ent->addr == NULL) {
		free(ent);
		syslog(LOG_WARNING, "\tFILTERDNS: Failed to allocate new address entry for table %s.", TABLENAME(thrdata->tablename));
		return (ENOMEM);
	}
	memcpy(ent->addr, addr, addr->sa_len);
	ent->refcnt = 2;
	TAILQ_INSERT_HEAD(&thrdata->rnh, ent, entry);
	if (thrdata->type == PF_TYPE) {
		if(addr->sa_family == AF_INET)
			syslog(LOG_NOTICE, "\tadding entry %s to table %s on host %s", inet_ntop(addr->sa_family, addr->sa_data + 2, buffer, sizeof buffer), TABLENAME(thrdata->tablename), thrdata->hostname);
		if(addr->sa_family == AF_INET6)
			syslog(LOG_NOTICE, "\tadding entry %s to table %s on host %s", inet_ntop(addr->sa_family, addr->sa_data + 6, buffer, sizeof buffer), TABLENAME(thrdata->tablename), thrdata->hostname);
		error = pf_tableentry(thrdata, ent->addr, ADD);
	}
	else if (thrdata->type == IPFW_TYPE) {
		syslog(LOG_NOTICE, "\tadding entry %s to table %s on host %s", inet_ntop(addr->sa_family, addr->sa_data + 2, buffer, sizeof buffer), TABLENAME(thrdata->tablename), thrdata->hostname);
		error = ipfw_tableentry(thrdata, addr, ADD);
	}

	if (error > 0)
		return (EAGAIN);
	else
		return (0);
}

static int
filterdns_check_sameip_diff_hostname(struct thread_data *thread, struct table *ip)
{
	struct thread_data *thr;
	struct table *e;
	char buffer[INET6_ADDRSTRLEN] = { 0 };

	if (TAILQ_EMPTY(&thread_list))
		return (0);
	if (thread->tablename == NULL)
		return (0);

	TAILQ_FOREACH(thr, &thread_list, next) {
		/* Same thread! */
		if (thr->thr_pid == thread->thr_pid)
			continue;
#if 0
		if (!strncmp(thr->hostname, thread->hostname, strlen(thr->hostname)))
			continue;
		if (strlen(thr->hostname) != strlen(thread->hostname))
			continue;
#endif
		if (thr->tablename == NULL)
			continue;
		if (strlen(thr->tablename) != strlen(thread->tablename))
			continue;
		if (strncmp(thr->tablename, thread->tablename, strlen(thr->tablename)))
			continue;

		TAILQ_FOREACH(e, &thr->rnh, entry) {
			if (e->addr->sa_family != ip->addr->sa_family)
				continue;
			if (!memcmp(ip->addr, e->addr, ip->addr->sa_len)) {
				syslog(LOG_INFO,
				    "IP address %s already present on table %s as address of hostname %s",
				    inet_ntop(e->addr->sa_family, e->addr->sa_data + 2, buffer, sizeof buffer),
				    TABLENAME(thr->tablename),
				    thr->hostname);
				return (EEXIST);
			}
		}
	}

	return (0);
}

static int
filterdns_clean_table(struct thread_data *thrdata, int donotcheckrefcount)
{
	struct table *e, *tmp;
	char buffer[INET6_ADDRSTRLEN] = { 0 };
	int removed = 0;
	int error;

	if (TAILQ_EMPTY(&thrdata->rnh))
		return (0);

	TAILQ_FOREACH_SAFE(e, &thrdata->rnh, entry, tmp) {
		e->refcnt--;
		if (donotcheckrefcount || (e->refcnt <= 0)) {
			/* If 2 dns names have same ip do not do any operation */
			if (filterdns_check_sameip_diff_hostname(thrdata, e) == EEXIST)
				continue;

			error = 0;
			if (thrdata->type == PF_TYPE) {
				if(e->addr->sa_family == AF_INET)
					syslog(LOG_NOTICE, "\t\tclearing entry %s from table %s on host %s", inet_ntop(e->addr->sa_family, e->addr->sa_data + 2, buffer, sizeof buffer), TABLENAME(thrdata->tablename), thrdata->hostname);
				if(e->addr->sa_family == AF_INET6)
					syslog(LOG_NOTICE, "\t\tclearing entry %s from table %s on host %s", inet_ntop(e->addr->sa_family, e->addr->sa_data + 6, buffer, sizeof buffer), TABLENAME(thrdata->tablename), thrdata->hostname);
				error = pf_tableentry(thrdata, e->addr, DELETE);
			}
			else if (thrdata->type == IPFW_TYPE) {
				syslog(LOG_NOTICE, "\t\tclearing entry %s from table %s on host %s", inet_ntop(e->addr->sa_family, e->addr->sa_data + 2, buffer, sizeof buffer), TABLENAME(thrdata->tablename), thrdata->hostname);
				error = ipfw_tableentry(thrdata, e->addr, DELETE);
			}

			if (error == 0) {
				TAILQ_REMOVE(&thrdata->rnh, e, entry);
				free(e->addr);
				free(e);
				if (!donotcheckrefcount)
					removed++;
			} else {
				syslog(LOG_ERR, "\t\tCOULD NOT clear entry %s from table %s on host %s will retry later", inet_ntop(e->addr->sa_family, e->addr->sa_data + 2, buffer, sizeof buffer), TABLENAME(thrdata->tablename), thrdata->hostname);
			}
		} else if (debug >= 2) {
			if (thrdata->type == PF_TYPE || thrdata->type == CMD_TYPE) {
				if (debug >= 2) {
					if(e->addr->sa_family == AF_INET)
						syslog(LOG_WARNING, "\tNOT clearing entry %s from table %s on host %s", inet_ntop(e->addr->sa_family, e->addr->sa_data + 2, buffer, sizeof buffer), TABLENAME(thrdata->tablename), thrdata->hostname);
					if(e->addr->sa_family == AF_INET6)
						syslog(LOG_WARNING, "\tNOT clearing entry %s from table %s on host %s", inet_ntop(e->addr->sa_family, e->addr->sa_data + 6, buffer, sizeof buffer), TABLENAME(thrdata->tablename), thrdata->hostname);
				}
			}
			else if (thrdata->type == IPFW_TYPE) {
				if (debug >= 2)
					syslog(LOG_WARNING, "\tNOT clearing entry %s from table %s on host %s", inet_ntop(e->addr->sa_family, e->addr->sa_data + 2, buffer, sizeof buffer), TABLENAME(thrdata->tablename), thrdata->hostname);
			}
		}
	}

	return (removed);
}

static int
host_dns(struct thread_data *hostd, int forceupdate)
{
	struct addrinfo hints, *res0, *res;
	int execcmd, error, retry;
	char buffer[INET6_ADDRSTRLEN];

	memset(&hints, 0, sizeof(hints));
	hints.ai_family = AF_UNSPEC;
	hints.ai_socktype = SOCK_DGRAM;
	res0 = NULL;
	error = getaddrinfo(hostd->hostname, NULL, &hints, &res0);
	if (error) {
		syslog(LOG_WARNING, "failed to resolve host %s will retry later again.", hostd->hostname);
		if (res0 != NULL)
			freeaddrinfo(res0);
		return (-1);
	}

	execcmd = 0;
	retry = 0;
	for (res = res0; res; res = res->ai_next) {
		if (res->ai_addr == NULL) {
			if (debug >=4)
				syslog(LOG_WARNING, "Skipping empty address for hostname %s", hostd->hostname);
			continue;
		}
		if (hostd->type == PF_TYPE && !need_to_monitor(hostd, res->ai_addr))
			continue;
		if (res->ai_family == AF_INET) {
			if (debug > 9)
				syslog(LOG_WARNING, "\t\tfound entry %s for %s", inet_ntop(res->ai_family, res->ai_addr->sa_data + 2, buffer, sizeof buffer), TABLENAME(hostd->tablename));
			if (hostd->mask > 32) {
				syslog(LOG_WARNING, "\t\tinvalid mask for %s/%d", inet_ntop(res->ai_family, res->ai_addr->sa_data + 2, buffer, sizeof buffer), hostd->mask);
				hostd->mask = 32;
			}
		}
		if(res->ai_family == AF_INET6) {
			if (debug > 9)
				syslog(LOG_WARNING, "\t\tfound entry %s for %s", inet_ntop(res->ai_family, res->ai_addr->sa_data + 6, buffer, sizeof buffer), TABLENAME(hostd->tablename));
		}
		error = add_table_entry(hostd, res->ai_addr, forceupdate);
		if (error == 0)
			execcmd++;
		else if (error == EAGAIN) {
			retry++;
			execcmd++;
		} else if (error == EPIPE)
			retry++;
	}
	freeaddrinfo(res0);

	error = filterdns_clean_table(hostd, 0);
	if (error > 0) {
		execcmd++;
		if (debug >= 4)
			syslog(LOG_WARNING, "Cleared %d entries for host(%s) table (%s)", error, hostd->hostname, TABLENAME(hostd->tablename));
	}

	if (execcmd > 0 && hostd->cmd != NULL) {
		execcmd = system(hostd->cmd);
		if (debug >= 2)
			syslog(LOG_WARNING, "Ran command %s with exit status %d because a dns change on hostname %s was detected.", hostd->cmd, execcmd, hostd->hostname);
	}

	if (retry > 0)
		return (EAGAIN);
	else
		return (0);
}

static int
ipfw_tableentry(struct thread_data *ipfwd, struct sockaddr *address, int action)
{
	static int s = -1;
	int error, i = 3;

	struct _entry {
		ip_fw3_opheader op3;
		ipfw_table_xentry xent;
	} entry;
	int addrlen;

	error = 0;
	while (i-- > 0) {
		if (s == -1)
			s = socket(AF_INET, SOCK_RAW, IPPROTO_RAW);
		if (s < 0) {
			error++;
			continue;
		}
		bzero(&entry, sizeof(entry));
		entry.xent.tbl = ipfwd->tablenr;
		entry.xent.value = ipfwd->pipe; /* XXX */
		entry.xent.type = IPFW_TABLE_CIDR;
		if (address->sa_family == AF_INET) {
			memcpy(&entry.xent.k.addr6, &satosin(address)->sin_addr, sizeof(struct in_addr));
			entry.xent.flags = IPFW_TCF_INET;
			entry.xent.masklen = ipfwd->mask;
			addrlen = sizeof(struct in_addr);
		} else if (address->sa_family == AF_INET6) {
			memcpy(&entry.xent.k.addr6, &satosin6(address)->sin6_addr, sizeof(struct in6_addr));
			entry.xent.masklen = ipfwd->mask6;
			addrlen = sizeof(struct in6_addr);
		}
		entry.xent.len = offsetof(ipfw_table_xentry, k) + addrlen;
		entry.op3.opcode = action == ADD ? IP_FW_TABLE_XADD : IP_FW_TABLE_XDEL;
#if (__FreeBSD_version < 1100000) /* XXX: Missing ipfw patch on 11 */
		entry.op3.ctxid = ipfwctx;
#endif

		if (setsockopt(s, IPPROTO_IP, IP_FW3, (void *)&entry, sizeof(entry)) < 0) {
			error++;
			continue;
		}
	}

	return (error);
}

static void
set_ipmask(struct in6_addr *h, int b)
{
	struct pf_addr m;
	int i, j = 0;

	memset(&m, 0, sizeof m);

	while (b >= 32) {
		m.addr32[j++] = 0xffffffff;
		b -= 32;
	}
	for (i = 31; i > 31-b; --i)
		m.addr32[j] |= (1 << i);
	if (b)
		m.addr32[j] = htonl(m.addr32[j]);

	/* Mask off bits of the address that will never be used. */
	for (i = 0; i < 4; i++)
		h->__u6_addr.__u6_addr32[i] = h->__u6_addr.__u6_addr32[i] & m.addr32[i];
}

static int
pf_tableentry(struct thread_data *pfd, struct sockaddr *address, int action)
{
	struct pfioc_table io;
	struct pfr_table table;
	struct pfr_addr addr;
	int error, i = 3;

	if (pfd->tablename == NULL)
		return (0);

	bzero(&table, sizeof(table));
	if (strlcpy(table.pfrt_name, pfd->tablename,
		sizeof(table.pfrt_name)) >= sizeof(table.pfrt_name)) {
		if (debug >= 1)
			syslog(LOG_WARNING, "could not add address to table %s", pfd->tablename);
		return (0);
	}

	bzero(&addr, sizeof(addr));
	if (address->sa_family == AF_INET) {
		addr.pfra_af = address->sa_family;
		addr.pfra_net = pfd->mask;
		addr.pfra_ip4addr = satosin(address)->sin_addr;
	}
	if (address->sa_family == AF_INET6) {
		addr.pfra_af = address->sa_family;
		memcpy(&addr.pfra_ip6addr, &satosin6(address)->sin6_addr, sizeof(addr.pfra_ip6addr));
		addr.pfra_net = pfd->mask6;
		set_ipmask(&addr.pfra_ip6addr, pfd->mask6);
	}
	if(debug >= 4)
		syslog(LOG_WARNING, "setting subnet mask for family %i to %i", addr.pfra_af, addr.pfra_net);

	error = 0;
	while (i-- > 0) {
		bzero(&io, sizeof io);
		io.pfrio_table = table;
		io.pfrio_buffer = &addr;
		io.pfrio_esize = sizeof(addr);
		io.pfrio_size = 1;

		if (action == DELETE) {
			if (ioctl(dev, DIOCRDELADDRS, &io)) {
				if (debug >= 3)
					syslog(LOG_WARNING, "FAILED to delete address from table %s.", pfd->tablename);
				error++;
			} else {
				if (debug >= 3)
					syslog(LOG_WARNING, "\t DELETED %d addresses(%d) to table %s.", io.pfrio_ndel, address->sa_family, pfd->tablename);
				break;
			}
		} else if (action == ADD) {
			if (ioctl(dev, DIOCRADDADDRS, &io)) {
				if (debug >= 3)
					syslog(LOG_WARNING, "FAILED to add address to table %s with errno %d.", pfd->tablename, errno);
				error++;
			} else {
				if (debug >= 3)
					syslog(LOG_WARNING, "\t ADDED %d addresses(%d) to table %s.", io.pfrio_nadd, address->sa_family, pfd->tablename);
				break;
			}
		}
	}

	return (error);
}

static int
is_ipaddrv6(const char *s, struct sockaddr_in6 *sin6)
{
	struct addrinfo hints, *res;
	int result = 0;

	memset(&hints, 0, sizeof(hints));
	hints.ai_family = AF_INET6;
	hints.ai_socktype = SOCK_DGRAM; /*dummy*/
	hints.ai_flags = AI_NUMERICHOST;
	if (getaddrinfo(s, "0", &hints, &res) == 0) {
		sin6->sin6_len = sizeof(*sin6);
		sin6->sin6_family = AF_INET6;
		memcpy(&sin6->sin6_addr, &((struct sockaddr_in6 *)res->ai_addr)->sin6_addr,
			sizeof(struct in6_addr));
		freeaddrinfo(res);
		result = 1;
	}

	return (result);
}

static void *
check_hostname(void *arg)
{
	struct thread_data *thrd = arg;
	struct timespec ts;
	struct sockaddr_in in;
	struct sockaddr_in6 in6;
	int tmp, added, error;

	if (!thrd->hostname)
		return (NULL);

	if (debug >= 2)
		syslog(LOG_WARNING, "Found hostname %s with netmask %d.", thrd->hostname, thrd->mask);

	if (thrd->type == PF_TYPE)
		get_present_table_entries(thrd);

	added = 0;
	tmp = 0;
	pthread_mutex_lock(&thrd->mtx);
	for (;;) {
		clock_gettime(CLOCK_MONOTONIC, &ts);
		ts.tv_sec += interval;
		ts.tv_sec += (interval % 30);
		ts.tv_nsec = 0;

		if (dev < 0) {
			dev = open("/dev/pf", O_RDWR);
			if (dev < 0)
				syslog(LOG_ERR, "firewall device could not be opened for operation...skipping this time");
		}

		if (dev > 0) {
			pthread_rwlock_rdlock(&main_lock);

			if (thrd->exit == 1) {
				pthread_rwlock_unlock(&main_lock);
				break;
			} else if (thrd->exit == 2) {
				tmp = 1;
				added = 0;
				thrd->exit = 0;
			}

			/* Detect if and ip address was passed in */
			if (added == 0 && inet_pton(AF_INET, thrd->hostname, &in.sin_addr) == 1) {
				added = 1;
				in.sin_family = AF_INET;
				in.sin_len = sizeof(in);
				if (thrd->mask > 32) {
					syslog(LOG_WARNING, "invalid mask for %s/%d", thrd->hostname, thrd->mask);
					thrd->mask = 32;
				}
				error = add_table_entry(thrd, (struct sockaddr *)&in, 1);
			} else if (added == 0 && is_ipaddrv6(thrd->hostname, &in6) == 1) {
				error = add_table_entry(thrd, (struct sockaddr *)&in6, 1);
				added = 1;
			} else if (added == 0) {
				error = host_dns(thrd, tmp);
			}
			if (error == EAGAIN) {
				/* Need to retry again due to some issue with table handling */
				tmp = 1;
			} else
				tmp = 0;
			pthread_rwlock_unlock(&main_lock);
		}

		/* Hack for sleeping a thread */
		pthread_cond_timedwait(&thrd->cond, &thrd->mtx, &ts);
		if (debug >= 6)
			syslog(LOG_WARNING, "\tAwaking from the sleep for hostname %s, table %s", thrd->hostname, TABLENAME(thrd->tablename));
	}
	pthread_mutex_unlock(&thrd->mtx);

	if (debug >= 4)
		syslog(LOG_ERR, "Cleaning up hostname %s", thrd->hostname);
	pthread_mutex_destroy(&thrd->mtx);
	pthread_cond_destroy(&thrd->cond);
	clear_hostname_addresses(thrd);
	if (thrd->hostname != NULL)
		free(thrd->hostname);
	if (thrd->tablename != NULL)
		free(thrd->tablename);
	if (thrd->cmd != NULL)
		free(thrd->cmd);
	free(thrd);

	return (NULL);
}

static int
check_hostname_create(struct thread_data *thr)
{
	pthread_condattr_t condattr;

	if (pthread_mutex_init(&thr->mtx, NULL) != 0)
		return (-1);
	if (pthread_condattr_init(&condattr) != 0 ||
	    pthread_condattr_setclock(&condattr, CLOCK_MONOTONIC) != 0 ||
	    pthread_cond_init(&thr->cond, &condattr) != 0 ||
	    pthread_condattr_destroy(&condattr) != 0) {
		pthread_mutex_destroy(&thr->mtx);
		return (-1);
	}
	if (pthread_create(&thr->thr_pid, &attr, check_hostname, thr) != 0) {
		pthread_mutex_destroy(&thr->mtx);
		pthread_cond_destroy(&thr->cond);
		return (-1);
	}
	pthread_set_name_np(thr->thr_pid, thr->hostname);
	return (0);
}

static void
clear_hostname_addresses(struct thread_data *thr)
{
	struct table *a;

	if (!TAILQ_EMPTY(&thr->static_rnh)) {
		while ((a = TAILQ_FIRST(&thr->static_rnh)) != NULL) {
			TAILQ_REMOVE(&thr->static_rnh, a, entry);
			if (a->addr)
				free(a->addr);
			free(a);
		}
	}

	if (!TAILQ_EMPTY(&thr->rnh)) {
		filterdns_clean_table(thr, 1);

		while ((a = TAILQ_FIRST(&thr->rnh)) != NULL) {
			TAILQ_REMOVE(&thr->rnh, a, entry);
			if (a->addr)
				free(a->addr);
			free(a);
		}
	}
}

static void *
merge_config(void *arg __unused) {
	struct thread_list tmp_thread_list;
	struct thread_data *thr, *tmpthr, *tmpthr2, *tmpthr3;
	int foundexisting, error;

	TAILQ_INIT(&tmp_thread_list);

	for (;;) {
		pthread_mutex_lock(&sig_mtx);
		error = pthread_cond_wait(&sig_condvar, &sig_mtx);
		if (error != 0) {
			syslog(LOG_ERR, "unable to wait on output queue retrying");
			continue;
		}
		pthread_mutex_unlock(&sig_mtx);

		pthread_rwlock_wrlock(&main_lock);

		if (!TAILQ_EMPTY(&thread_list)) {
			while ((thr = TAILQ_FIRST(&thread_list)) != NULL) {
				TAILQ_REMOVE(&thread_list, thr, next);
				TAILQ_INSERT_TAIL(&tmp_thread_list, thr, next);
			}
		}

		if (parse_config(file)) {
			syslog(LOG_ERR, "could not parse new configuration file, exiting...");
			exit(10);
		}

		if (!TAILQ_EMPTY(&thread_list)) {
			TAILQ_FOREACH_SAFE(thr, &thread_list, next, tmpthr2) {
				foundexisting = 0;

				TAILQ_FOREACH_SAFE(tmpthr, &tmp_thread_list, next, tmpthr3) {
					if (thr->type != tmpthr->type)
						continue;
					if (strlen(thr->hostname) != strlen(tmpthr->hostname))
						continue;
					if (strncmp(thr->hostname, tmpthr->hostname, strlen(thr->hostname)))
						continue;

					if (thr->tablename != NULL && (strlen(thr->tablename) != strlen(tmpthr->tablename) || strncmp(thr->tablename, tmpthr->tablename, strlen(thr->tablename))))
						continue;

					TAILQ_REMOVE(&thread_list, thr, next);
					TAILQ_REMOVE(&tmp_thread_list, tmpthr, next);
					TAILQ_INSERT_HEAD(&thread_list, tmpthr, next);
					if (thr->cmd != NULL) {
						if (tmpthr->cmd != NULL) {
							if ((strlen(thr->cmd) != strlen(tmpthr->cmd) || strncmp(thr->cmd, tmpthr->cmd, strlen(thr->cmd)))) {
								free(tmpthr->cmd);
								tmpthr->cmd = strdup(thr->cmd);
							}
						} else if (thr->cmd != NULL)
							tmpthr->cmd = strdup(thr->cmd);
					}
					if (thr->hostname != NULL)
						free(thr->hostname);
					if (thr->tablename != NULL)
						free(thr->tablename);
					if (thr->cmd != NULL)
						free(thr->cmd);
					free(thr);
					tmpthr->exit = 2;
					foundexisting = 1;
					break;
				}

				if (foundexisting == 0) {
					if (debug > 3)
						syslog(LOG_ERR, "Creating a new thread for host %s!", thr->hostname);
					if (check_hostname_create(thr) == -1)
						syslog(LOG_ERR, "Unable to create monitoring thread for host %s! It will not be monitored!", thr->hostname);
				}
			}
		}
		if (debug > 3)
			syslog(LOG_ERR, "Cleaning up previous hostnames");
		clear_config(&tmp_thread_list);
		pthread_rwlock_unlock(&main_lock);

	}
}

static void
handle_signal(int sig)
{
	if (debug >= 3)
		syslog(LOG_WARNING, "Received signal %s(%d).", strsignal(sig), sig);
	switch(sig) {
		case SIGHUP:
			pthread_mutex_lock(&sig_mtx);
			pthread_cond_signal(&sig_condvar);
			pthread_mutex_unlock(&sig_mtx);
			break;
		case SIGTERM:
		case SIGINT:
			pthread_rwlock_wrlock(&main_lock);
			clear_config(&thread_list);
			pthread_rwlock_unlock(&main_lock);
			exit(0);
			break;
		default:
			if (debug >= 3)
				syslog(LOG_WARNING, "unhandled signal");
	}
}

static void
clear_config(struct thread_list *thrlist)
{
	struct thread_data *thr;

	if (TAILQ_EMPTY(thrlist))
		return;

	while ((thr = TAILQ_FIRST(thrlist)) != NULL) {
		if (debug >= 5)
			syslog(LOG_INFO, "Clearing out hostname %s", thr->hostname);
		clear_hostname_addresses(thr);
		TAILQ_REMOVE(thrlist, thr, next);
		thr->exit = 1;
	}
}

static void filterdns_usage(void) {

	fprintf(stderr, "usage: filterdns -f -p pidfile -i interval -c filecfg -d debuglevel\n");
	exit(4);
}

int main(int argc, char *argv[]) {
	struct thread_data *thr;
	int error, ch;
	char *pidfile;
	FILE *pidfd;
	sig_t sig_error;
	int foreground = 0;

	file = NULL;
	pidfile = NULL;

	while ((ch = getopt(argc, argv, "c:d:fi:p:y:v")) != -1) {
		switch (ch) {
		case 'c':
			file = optarg;
			break;
		case 'd':
			debug = atoi(optarg);
			break;
		case 'f':
			foreground = 1;
			break;
		case 'i':
			interval = atoi(optarg);
			if (interval < 1) {
				fprintf(stderr, "Invalid interval %d\n", interval);
				return (3);
			}
			break;
		case 'p':
			pidfile = optarg;
			break;
		case 'y':
			ipfwctx = atoi(optarg);
			break;
		case 'v':
			printf("Version 1.2\n");
			exit(0);
			/* NOTREACHED */
			break;
		default:
			fprintf(stderr, "Wrong option: %c given!\n", ch);
			filterdns_usage();
			return (ch);
			break;
		}
	}

	if (file == NULL) {
		fprintf(stderr, "Configuration file is mandatory!");
		filterdns_usage();
		return (-1);
	}

	if (foreground == 0) {
		(void)freopen("/dev/null", "w", stdout);
		(void)freopen("/dev/null", "w", stdin);
		closefrom(3);
	}

	TAILQ_INIT(&thread_list);
	if (parse_config(file)) {
		syslog(LOG_ERR, "unable to open configuration file");
		return (EX_OSERR);
	}

	dev = open("/dev/pf", O_RDWR);
	if (dev < 0)
		errx(1, "Could not open device.");

	/* go into background */
	if (!foreground && daemon(0, 0) == -1) {
		printf("error in daemon\n");
		exit(1);
	}

	if (!foreground && pidfile) {
		/* write PID to file */
		pidfd = fopen(pidfile, "w");
		if (pidfd) {
			while (flock(fileno(pidfd), LOCK_EX) != 0)
				;
			fprintf(pidfd, "%d\n", getpid());
			flock(fileno(pidfd), LOCK_UN);
			fclose(pidfd);
		} else
			syslog(LOG_WARNING, "could not open pid file");
	}

	/*
	 * Catch SIGHUP in order to reread configuration file.
	 */
	sig_error = signal(SIGHUP, handle_signal);
	if (sig_error == SIG_ERR)
		err(EX_OSERR, "unable to set signal handler");
	sig_error = signal(SIGTERM, handle_signal);
	if (sig_error == SIG_ERR)
		err(EX_OSERR, "unable to set signal handler");
	sig_error = signal(SIGINT, handle_signal);
	if (sig_error == SIG_ERR)
		err(EX_OSERR, "unable to set signal handler");

	pthread_rwlock_init(&main_lock, NULL);

	pthread_attr_init(&attr);
	pthread_attr_setdetachstate(&attr, PTHREAD_CREATE_DETACHED);

	TAILQ_FOREACH(thr, &thread_list, next) {
		if (debug > 3)
			syslog(LOG_ERR, "Creating a new thread for host %s!", thr->hostname);
		if (check_hostname_create(thr) == -1)
			if (debug >= 1)
				syslog(LOG_ERR, "Unable to create monitoring thread for host %s", thr->hostname);
	}

	pthread_mutex_init(&sig_mtx, NULL);
	pthread_cond_init(&sig_condvar, NULL);
	error = pthread_create(&sig_thr, &attr, merge_config, NULL);
	if (error != 0) {
		if (debug >= 1)
			syslog(LOG_ERR, "Unable to create signal thread %s", thr->hostname);
	}
	pthread_set_name_np(sig_thr, "signal-thread");
#if 0
	TAILQ_FOREACH(thr, &thread_list, next)
		pthread_join(thr->thr_pid, NULL);

	clear_config(&thread_list);
#endif

	pthread_exit(NULL);
}
