/*
 * tables.c
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2009-2022 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

#include <sys/ioctl.h>
#include <sys/socket.h>
#include <sys/types.h>

#include <arpa/inet.h>
#include <net/if.h>
#include <net/pfvar.h>
#include <netinet/in.h>
#include <netinet/ip_fw.h>

#include <errno.h>
#include <fcntl.h>
#include <stdio.h>
#include <string.h>
#include <syslog.h>

#include "filterdns.h"
#include "tables.h"

#define	TABLE_DEL		1
#define	TABLE_ADD		2
#define	ACTION_IS_ADD(_a)	((_a) == TABLE_ADD)

static int ipfw_tableentry(struct action *, struct sockaddr *, int);
static int pf_tableentry(struct action *, struct sockaddr *, int);

static int
table_entry(struct action *act, struct _addr_entry *addr, int action)
{
	int error;

	switch (act->type) {
	case IPFW_TYPE:
		error = ipfw_tableentry(act, addr->addr, action);
		break;
	case PF_TYPE:
		error = pf_tableentry(act, addr->addr, action);
		break;
	default:
		error = -1;
	}

	return (error);
}

static int
table_add(struct action *act, struct _addr_entry *addr)
{
	return (table_entry(act, addr, TABLE_ADD));
}

static int
table_del(struct action *act, struct _addr_entry *addr)
{
	return (table_entry(act, addr, TABLE_DEL));
}

int
table_update(struct action *act)
{
	char buffer[INET6_ADDRSTRLEN];
	int add, del, error;
	struct _addr_entry *ent, *enttmp;

	TAILQ_FOREACH(ent, &act->tbl_rnh, entry) {
		if (ent->flags & ADDR_STATIC)
			continue;
		ent->flags |= ADDR_OLD;
	}

	/* Copy... */
	TAILQ_FOREACH(ent, &act->rnh, entry) {
		addr_add(&act->tbl_rnh, NULL, ent->addr,
		    (ent->flags & ADDR_STATIC));
	}

	error = 0;
	TAILQ_FOREACH_SAFE(ent, &act->tbl_rnh, entry,
	    enttmp) {
		add = del = 0;
		if (ent->flags & ADDR_NEW) {
			error = table_add(act, ent);
			ent->flags &= ~ADDR_NEW;
			add++;
		}
		if (ent->flags & ADDR_OLD) {
			error = table_del(act, ent);
			addr_del(&act->tbl_rnh, NULL, ent);
			del++;
		}
		if (debug >= 4 && (add > 0 || del > 0)) {
			memset(buffer, 0, sizeof(buffer));
			if (ent->addr->sa_family == AF_INET)
				inet_ntop(ent->addr->sa_family,
				    &satosin(ent->addr)->sin_addr.s_addr,
				    buffer, sizeof(buffer));
			else if (ent->addr->sa_family == AF_INET6)
				inet_ntop(ent->addr->sa_family,
				    &satosin6(ent->addr)->sin6_addr.s6_addr,
				    buffer, sizeof(buffer));
			if (add > 0 && error == EEXIST)
				syslog(LOG_WARNING,
				    "\t\t%s %s address, table: %s anchor: %s host: %s address: %s",
				    "Already exist",
				    action_to_string(act->type), act->tablename, act->anchor,
				    act->hostname, buffer);
			else if (error != 0)
				syslog(LOG_WARNING,
				    "\t\t%s %s address, table: %s anchor: %s host: %s address: %s error: %d",
				    ((add > 0) ? "FAILED to add" : "FAILED to remove"),
				    action_to_string(act->type), act->tablename, act->anchor,
				    act->hostname, buffer, error);
			else
				syslog(LOG_WARNING,
				    "\t\t%s %s address, table: %s anchor: %s host: %s address: %s",
				    ((add > 0) ? "Added" : "Removed"),
				    action_to_string(act->type), act->tablename, act->anchor,
				    act->hostname, buffer);
		}
	}

	return (error);
}

int
table_cleanup(struct action *act)
{
	int error;
	struct _addr_entry *ent, *enttmp;

	error = 0;
	TAILQ_FOREACH_SAFE(ent, &act->tbl_rnh, entry, enttmp) {
		error = table_del(act, ent);
		addr_del(&act->tbl_rnh, NULL, ent);
	}

	return (error);
}

static int
table_do_create(int s, ipfw_obj_header *oh, int pipe)
{
	char tbuf[sizeof(ipfw_obj_header) + sizeof(ipfw_xtable_info)];
	ipfw_xtable_info xi;
	int error;

	memset(&xi, 0, sizeof(xi));
	xi.type = IPFW_TABLE_ADDR;
	if (pipe == 0)
		xi.vmask = IPFW_VTYPE_LEGACY;
	else
		xi.vmask = IPFW_VTYPE_PIPE;
	strlcpy(xi.tablename, oh->ntlv.name, sizeof(xi.tablename));
	memcpy(tbuf, oh, sizeof(*oh));
	memcpy(tbuf + sizeof(*oh), &xi, sizeof(xi));
	oh = (ipfw_obj_header *)tbuf;
	oh->opheader.opcode = IP_FW_TABLE_XCREATE;
	oh->opheader.version = 0;

	error = setsockopt(s, IPPROTO_IP, IP_FW3, &oh->opheader, sizeof(tbuf));

	return (error);
}

static int
table_get_info(int s, ipfw_obj_header *oh, ipfw_xtable_info *i, int pipe)
{
	char tbuf[sizeof(ipfw_obj_header) + sizeof(ipfw_xtable_info)];
	int error, retry;
	ipfw_obj_header *o;
	socklen_t sz;

	for (retry = 2; retry > 0; retry--) {
		memset(tbuf, 0, sizeof(tbuf));
		memcpy(tbuf, oh, sizeof(*oh));
		o = (ipfw_obj_header *)tbuf;
		o->opheader.opcode = IP_FW_TABLE_XINFO;
		o->opheader.version = 0;

		sz = sizeof(tbuf);
		error = getsockopt(s, IPPROTO_IP, IP_FW3, &o->opheader, &sz);
		if (error == -1 && errno == 3) {
			errno = 0;
			/* Try to create the table if it does not exist. */
			if (table_do_create(s, oh, pipe) == 0)
				continue;
		}
		break;
	}
	if (error != 0)
		return (error);
	if (sz < sizeof(tbuf))
		return (EINVAL);

	*i = *(ipfw_xtable_info *)(o + 1);

	return (0);
}

static int
ipfw_tableentry(struct action *act, struct sockaddr *addr, int action)
{
	char xbuf[sizeof(ipfw_obj_header) + sizeof(ipfw_obj_ctlv) +
	    sizeof(ipfw_obj_tentry)];
	int error, retry;
	ipfw_obj_ctlv *ctlv;
	ipfw_obj_header *oh;
	ipfw_obj_ntlv *ntlv;
	ipfw_obj_tentry *tent;
	ipfw_table_value *v;
	ipfw_xtable_info xi;
	socklen_t size;
	static int s = -1;

	retry = 3;
	while (retry-- > 0) {

		error = 0;

		/* XXX - the socket will remain open between calls. */
		if (s == -1)
			s = socket(AF_INET, SOCK_RAW, IPPROTO_RAW);
		if (s < 0) {
			error = errno;
			continue;
		}

		memset(xbuf, 0, sizeof(xbuf));
		oh = (ipfw_obj_header *)xbuf;
		if (ACTION_IS_ADD(action))
			oh->opheader.opcode = IP_FW_TABLE_XADD;
		else
			oh->opheader.opcode = IP_FW_TABLE_XDEL;
		oh->opheader.version = 1;

		ntlv = &oh->ntlv;
		ntlv->head.type = IPFW_TLV_TBL_NAME;
		ntlv->head.length = sizeof(ipfw_obj_ntlv);
		ntlv->idx = 1;
		ntlv->set = 0;
		strlcpy(ntlv->name, act->tablename, sizeof(ntlv->name));
		oh->idx = 1;

		if (table_get_info(s, oh, &xi, act->pipe) != 0) {
			error = ENOENT;
			break;
		}
		if (xi.type != IPFW_TABLE_ADDR) {
			error = EINVAL;
			break;
		}
		ntlv->type = xi.type;

		size = sizeof(ipfw_obj_ctlv) + sizeof(ipfw_obj_tentry);
		ctlv = (ipfw_obj_ctlv *)(oh + 1);
		ctlv->count = 1;
		ctlv->head.length = size;

		tent = (ipfw_obj_tentry *)(ctlv + 1);
		tent->head.length = sizeof(ipfw_obj_tentry);
		tent->idx = oh->idx;

		if (addr->sa_family == AF_INET) {
			tent->subtype = AF_INET;
			tent->masklen = 32;
			if (act->host != NULL && act->host->mask != -1)
				tent->masklen = act->host->mask;
			memcpy(&tent->k.addr, &satosin(addr)->sin_addr,
			    sizeof(struct in_addr));
		} else if (addr->sa_family == AF_INET6) {
			tent->subtype = AF_INET6;
			tent->masklen = 128;
			if (act->host != NULL && act->host->mask6 != -1)
				tent->masklen = act->host->mask6;
			memcpy(&tent->k.addr6, &satosin6(addr)->sin6_addr,
			    sizeof(struct in6_addr));
		} else {
			error = EINVAL;
			break;
		}

		if (act->pipe != 0) {
			v = &tent->v.value;
			v->pipe = act->pipe;
		}

		size += sizeof(ipfw_obj_header);
		error = setsockopt(s, IPPROTO_IP, IP_FW3, &oh->opheader, size);

		/* Entry already exist. */
		if (ACTION_IS_ADD(action) && error == -1 && errno == EEXIST) {
			error = EEXIST;
			break;
		}

		/* Operation succeeded. */
		if (error == 0)
			break;
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
		h->__u6_addr.__u6_addr32[i] =
		    h->__u6_addr.__u6_addr32[i] & m.addr32[i];
}

static int
pf_table_create(int dev, const char *anchor, const char *tablename)
{
	struct pfioc_table io;
	struct pfr_table table;

	memset(&table, 0, sizeof(table));
	if (strlcpy(table.pfrt_name, tablename,
	    sizeof(table.pfrt_name)) >= sizeof(table.pfrt_name)) {
		if (debug >= 1)
			syslog(LOG_WARNING, "%s: could not set table name: %s",
			    __func__, tablename);
		return (-1);
	}
	if (anchor != NULL) {
		if (strlcpy(table.pfrt_anchor, anchor,
		    sizeof(table.pfrt_anchor)) >= sizeof(table.pfrt_anchor)) {
			if (debug >= 1)
				syslog(LOG_WARNING, "%s: could not set anchor: %s",
				    __func__, anchor);
			return (-1);
		}
	}

	table.pfrt_flags |= PFR_TFLAG_PERSIST;

	memset(&io, 0, sizeof(io));
	io.pfrio_buffer = &table;
	io.pfrio_esize = sizeof(table);
	io.pfrio_size = 1;
	if (ioctl(dev, DIOCRADDTABLES, &io))
		return (-1);

	return (0);
}

static int
pf_tableentry(struct action *act, struct sockaddr *addr, int action)
{
	struct pfioc_table io;
	struct pfr_table table;
	struct pfr_addr pfaddr;
	int error, retry;
	static int dev = -1;
	unsigned long iocmd;

	if (action != TABLE_ADD && action != TABLE_DEL)
		return (-1);

	memset(&table, 0, sizeof(table));
	if (strlcpy(table.pfrt_name, act->tablename,
	    sizeof(table.pfrt_name)) >= sizeof(table.pfrt_name)) {
		if (debug >= 1)
			syslog(LOG_WARNING, "%s: could not set table name: %s",
			    __func__, act->tablename);
		return (-1);
	}
	if (act->anchor != NULL) {
		if (strlcpy(table.pfrt_anchor, act->anchor,
			sizeof(table.pfrt_anchor)) >= sizeof(table.pfrt_anchor)) {
			if (debug >= 1)
				syslog(LOG_WARNING, "%s: could not set anchor: %s",
					__func__, act->anchor);
			return (-1);
		}
	}

	bzero(&pfaddr, sizeof(pfaddr));
	if (addr->sa_family == AF_INET) {
		pfaddr.pfra_af = addr->sa_family;
		pfaddr.pfra_net = 32;
		if (act->host != NULL && act->host->mask != -1)
			pfaddr.pfra_net = act->host->mask;
		pfaddr.pfra_ip4addr = satosin(addr)->sin_addr;
	} else if (addr->sa_family == AF_INET6) {
		pfaddr.pfra_af = addr->sa_family;
		memcpy(&pfaddr.pfra_ip6addr, &satosin6(addr)->sin6_addr,
		    sizeof(pfaddr.pfra_ip6addr));
		pfaddr.pfra_net = 128;
		if (act->host != NULL && act->host->mask6 != -1)
			pfaddr.pfra_net = act->host->mask6;
		set_ipmask(&pfaddr.pfra_ip6addr, pfaddr.pfra_net);
	} else
		return (-1);

	retry = 3;
	while (retry-- > 0) {

		/* XXX - the fd will remain open between calls. */
		if (dev == -1)
		        dev = open("/dev/pf", O_RDWR);
		if (dev < 0) {
			error = errno;
			continue;
		}

		if (ACTION_IS_ADD(action)) {
			/* Create the Table if it does not exist. */
			error = pf_table_create(dev, act->anchor, act->tablename);
			if (error != 0)
				continue;
		}

		bzero(&io, sizeof io);
		io.pfrio_table = table;
		io.pfrio_buffer = &pfaddr;
		io.pfrio_esize = sizeof(pfaddr);
		io.pfrio_size = 1;

		if (ACTION_IS_ADD(action))
			iocmd = DIOCRADDADDRS;
		else
			iocmd = DIOCRDELADDRS;
		if (ioctl(dev, iocmd, &io) == -1) {
			error = errno;
			continue;
		}
		break;
	}

	return (error);
}
