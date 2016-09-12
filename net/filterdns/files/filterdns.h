/*
 * filterdns.h
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011 Rubicon Communications, LLC (Netgate)
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

#include <sys/types.h>
#include <sys/param.h>
#include <sys/socket.h>
#include <sys/ioctl.h>
#include <sys/refcount.h>
#include <sys/queue.h>

#include <pthread.h>

#define IPFW_TYPE	0
#define PF_TYPE		1
#define CMD_TYPE	2

struct table {
	struct sockaddr	*addr;
	u_int refcnt;
	TAILQ_ENTRY(table) entry;
};
TAILQ_HEAD(table_entry, table);

struct thread_data {
	struct table_entry rnh;
	struct table_entry static_rnh;
	int type;
	char *tablename;
	char *hostname;
	int tablenr;
	int pipe;
	int mask;
	int mask6;
	char *cmd;
	TAILQ_ENTRY(thread_data) next;
	pthread_t thr_pid;
	pthread_cond_t cond;
	pthread_mutex_t mtx;
	int exit;
};
TAILQ_HEAD(thread_list, thread_data) thread_list;

int parse_config(char *);

#endif /* _FILTER_DNS_H_ */
