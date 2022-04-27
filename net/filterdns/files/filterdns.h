/*
 * filterdns.h
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011-2022 Rubicon Communications, LLC (Netgate)
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

#ifndef _FILTER_DNS_H_
#define _FILTER_DNS_H_

#include <sys/queue.h>

#include <pthread.h>

#define	_BUF_SIZE	4096
#define	satosin(sa)	((struct sockaddr_in *)(sa))
#define	satosin6(sa)	((struct sockaddr_in6 *)(sa))

#define	IPFW_TYPE	0
#define	PF_TYPE		1
#define	CMD_TYPE	2

#define	ADDR_NEW	1
#define	ADDR_OLD	2
#define	ADDR_STATIC	4

#define	THR_STARTING	1
#define	THR_RUNNING	2
#define	THR_DYING	4

#define	ACT_FORCE	1

struct _addr_entry {
	TAILQ_ENTRY(_addr_entry) entry;
	struct sockaddr	*addr;
	uint32_t flags;
};
TAILQ_HEAD(addr_list, _addr_entry);

struct thread_host {
	TAILQ_ENTRY(thread_host) next;
	struct addr_list rnh;
	char *hostname;
	int mask;
	int mask6;
	pthread_t thr_pid;
	pthread_cond_t cond;
	pthread_mutex_t mtx;
	uint32_t refcnt;
	uint32_t state;
	TAILQ_HEAD(actions, action) actions;
};

struct action {
	TAILQ_ENTRY(action) next_list;
	TAILQ_ENTRY(action) next_actions;
	struct thread_host *host;
	struct addr_list tbl_rnh;
	struct addr_list rnh;
	int flags;
	int type;
	char *tablename;
	char *anchor;
	int pipe;
	char *cmd;
	char *hostname;
	uint32_t state;
	pthread_t thr_pid;
	pthread_cond_t cond;
	pthread_mutex_t mtx;
};

int parse_config(char *);
const char *action_to_string(int);
struct action *action_add(int, const char *, const char *, const char *,
    int, const char *, int *);
struct thread_host *host_add(struct action *);
struct _addr_entry *addr_add(struct addr_list *, const char *,
    struct sockaddr *, uint32_t);
void addr_del(struct addr_list *, const char *, struct _addr_entry *);

extern int debug;

#endif /* _FILTER_DNS_H_ */
