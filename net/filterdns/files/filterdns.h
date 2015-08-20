/*
 * Copyright (C) 2011 Ermal Luçi
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
