/*
 * filterdns.c
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2009-2025 Rubicon Communications, LLC (Netgate)
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

#include <sys/types.h>

#include <arpa/inet.h>
#include <net/if.h>
#include <netinet/in.h>

#include <err.h>
#include <errno.h>
#include <fcntl.h>
#include <netdb.h>
#include <pthread_np.h>
#include <semaphore.h>
#include <signal.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sysexits.h>
#include <syslog.h>
#include <unistd.h>

#include "filterdns.h"
#include "tables.h"

int debug = 0;
static int interval = 30;
static char *file = NULL;
static sem_t merge_sem;
static uint32_t merge_state;
static pthread_attr_t g_attr;
static pthread_rwlock_t main_lock;

TAILQ_HEAD(thread_list, thread_host) thread_list;
TAILQ_HEAD(action_list, action) action_list;

static void host_del(struct action *);
static void action_del(struct action *, struct action_list *);
static void addr_cleanup(struct addr_list *, const char *);

const char *
action_to_string(int type)
{
	switch (type) {
	case PF_TYPE:
		return ("pf");
		/* NOTREACHED */
		break;
	case IPFW_TYPE:
		return ("ipfw");
		/* NOTREACHED */
		break;
	case CMD_TYPE:
		return ("cmd");
		/* NOTREACHED */
		break;
	}

	return ("invalid");
}

static void *
check_action(void *arg)
{
	int error = 0, update = 0;
	struct action *act;
	struct _addr_entry *ent, *enttmp;

	pthread_rwlock_rdlock(&main_lock);
	act = (struct action *)arg;
	act->state = THR_RUNNING;
	for (;;) {
		pthread_rwlock_unlock(&main_lock);
		sem_wait(&act->sem);
		if (act == NULL || act->host == NULL) {
			break;
		}
		pthread_rwlock_rdlock(&main_lock);
		if (debug >= 6) {
			LOG(LOG_WARNING,
			    "\tAwaking from the sleep for type: %s %s%s%s%s%s%shostname: %s",
			    action_to_string(act->type),
			    (act->tablename != NULL ? "table: " : ""),
			    (act->tablename != NULL ? act->tablename : ""),
			    (act->tablename != NULL ? " " : ""),
			    (act->anchor != NULL ? "anchor: " : ""),
			    (act->anchor != NULL ? act->anchor : ""),
			    (act->anchor != NULL ? " " : ""),
			    (act->hostname != NULL) ? act->hostname: "");
		}
		if (act->flags & ACT_FORCE)
			act->flags &= ~ACT_FORCE;
		TAILQ_FOREACH(ent, &act->rnh, entry) {
			if (ent->flags & ADDR_STATIC) {
				continue;
			}
			ent->flags |= ADDR_OLD;
		}

		/* Copy... */
		TAILQ_FOREACH(ent, &act->host->rnh, entry) {
			addr_add(&act->rnh, NULL, ent->addr, (ent->flags & ADDR_STATIC));
		}

		update = 0;
		TAILQ_FOREACH_SAFE(ent, &act->rnh, entry, enttmp) {
			if (ent->flags & ADDR_NEW) {
				ent->flags &= ~ADDR_NEW;
				update++;
			}
			if (ent->flags & ADDR_OLD) {
				addr_del(&act->rnh, NULL, ent);
				update++;
			}
		}
		if (update > 0 && act->tablename != NULL) {
			error = table_update(act);
			if (debug >= 4)
				LOG(LOG_WARNING,
				    "\tUpdated %s table %s anchor %s host: %s error: %d",
				    action_to_string(act->type),
				    act->tablename, act->anchor, act->hostname,
				    error);
		}

		if (act->cmd != NULL) {
			error = system(act->cmd);
			if (debug >= 2)
				LOG(LOG_WARNING,
				    "\tRan command '%s' with exit status %d because a dns change on hostname %s was detected.",
				    act->cmd, error, act->hostname);
		}
	}
	pthread_rwlock_unlock(&main_lock);
	act->state = THR_DYING;
	sem_post(&act->exit_sem);
	act->state = THR_STOPPED;
	return (NULL);
}

static int
action_create(struct action *act, pthread_attr_t *attr)
{

	if (act->state != 0)
		return (-1);
	act->state = THR_STARTING;
	act->flags = ACT_FORCE;
	if (debug > 3)
		LOG(LOG_INFO,
		    "Creating a new thread for action type: %s %s%s%s%s%s%shostname: %s",
		    action_to_string(act->type),
		    (act->tablename != NULL ? "table: " : ""),
		    (act->tablename != NULL ? act->tablename : ""),
		    (act->tablename != NULL ? " " : ""),
		    (act->anchor != NULL ? "anchor: " : ""),
		    (act->anchor != NULL ? act->anchor : ""),
		    (act->anchor != NULL ? " " : ""),
		    act->hostname);

	if (sem_init(&act->sem, 0, 0) != 0) {
		return (-1);
	}
	if (sem_init(&act->exit_sem, 0, 0) != 0) {
		sem_destroy(&act->sem);
		return (-1);
	}
	if (pthread_create(&act->thr_pid, attr, check_action, act) != 0) {
		sem_destroy(&act->sem);
		sem_destroy(&act->exit_sem);
		return (-1);
	}
#if 0
	pthread_set_name_np(act->thr_pid, act->hostname);
#endif
	return (0);
}

struct action *
action_add(int type, const char *hostname, const char *tablename,
    const char *anchor, int pipe, const char *cmd, int *eexist)
{
	char *buf, tmp[16];
	struct action *search, *act;

	TAILQ_FOREACH(search, &action_list, next_list) {
		if (search->type != type || search->pipe != pipe)
			continue;
		if ((search->tablename != NULL && tablename == NULL) ||
		    (search->tablename == NULL && tablename != NULL))
			continue;
		if (search->tablename != NULL && tablename != NULL &&
		    (strlen(search->tablename) != strlen(tablename) ||
		    strcmp(search->tablename, tablename) != 0))
			continue;
		if ((search->anchor != NULL && anchor == NULL) ||
		    (search->anchor == NULL && anchor != NULL))
			continue;
		if (search->anchor != NULL && anchor != NULL &&
		    (strlen(search->anchor) != strlen(anchor) ||
		    strcmp(search->anchor, anchor) != 0))
			continue;
		if ((search->cmd != NULL && cmd == NULL) ||
		    (search->cmd == NULL && cmd != NULL))
			continue;
		if (search->cmd != NULL && cmd != NULL &&
		    (strlen(search->cmd) != strlen(cmd) ||
		    strcmp(search->cmd, cmd) != 0))
			continue;
		if ((search->host != NULL && hostname == NULL) ||
		    (search->host == NULL && hostname != NULL))
			continue;
		if (search->hostname != NULL && hostname != NULL &&
		    (strlen(search->hostname) != strlen(hostname) ||
		    strcmp(search->hostname, hostname) != 0))
			continue;
		*eexist = 1;
		return (search);
	}

	*eexist = 0;
	if (hostname == NULL)
		return (NULL);
	act = calloc(1, sizeof(*act));
	act->type = type;
	act->pipe = pipe;
	TAILQ_INIT(&act->rnh);
	TAILQ_INIT(&act->tbl_rnh);
	act->hostname = strdup(hostname);
	if (cmd != NULL)
		act->cmd = strdup(cmd);
	if (tablename != NULL)
		act->tablename = strdup(tablename);
	if (anchor != NULL)
		act->anchor = strdup(anchor);
	TAILQ_INSERT_TAIL(&action_list, act, next_list);

	buf = calloc(1, _BUF_SIZE);
	strlcpy(buf, "\tAdding Action: ", _BUF_SIZE);
	strlcat(buf, action_to_string(type), _BUF_SIZE);
	if (tablename != NULL) {
		strlcat(buf, " table: ", _BUF_SIZE);
		strlcat(buf, tablename, _BUF_SIZE);
	}
	if (anchor != NULL) {
		strlcat(buf, " anchor: ", _BUF_SIZE);
		strlcat(buf, anchor, _BUF_SIZE);
	}
	if (type == IPFW_TYPE && pipe > 0) {
		strlcat(buf, " pipe: ", _BUF_SIZE);
		memset(tmp, 0, sizeof(tmp));
		snprintf(tmp, sizeof(tmp), "%d", pipe);
		strlcat(buf, tmp, _BUF_SIZE);
	}
	if (cmd != NULL) {
		strlcat(buf, " cmd: ", _BUF_SIZE);
		strlcat(buf, cmd, _BUF_SIZE);
	}
	if (hostname != NULL) {
		strlcat(buf, " host: ", _BUF_SIZE);
		strlcat(buf, hostname, _BUF_SIZE);
	}
	LOG(LOG_WARNING, "%s", buf);
	free(buf);

	return (act);
}

static void
action_del(struct action *act, struct action_list *actlist)
{
	if (debug >= 4)
		LOG(LOG_INFO,
		    "Cleaning up action type: %s %s%s%s%s%s%shostname: %s",
		    action_to_string(act->type),
		    (act->tablename != NULL ? "table: " : ""),
		    (act->tablename != NULL ? act->tablename : ""),
		    (act->tablename != NULL ? " " : ""),
		    (act->anchor != NULL ? "anchor: " : ""),
		    (act->anchor != NULL ? act->anchor : ""),
		    (act->anchor != NULL ? " " : ""),
		    (act->hostname != NULL ? act->hostname : ""));
	host_del(act);
	TAILQ_REMOVE(actlist, act, next_list);
	addr_cleanup(&act->rnh, NULL);
	table_cleanup(act);

	if (act->hostname != NULL)
		free(act->hostname);
	if (act->tablename != NULL)
		free(act->tablename);
	if (act->anchor != NULL)
		free(act->anchor);
	if (act->cmd != NULL)
		free(act->cmd);
	sem_destroy(&act->sem);
	sem_destroy(&act->exit_sem);

	free(act);
}

struct thread_host *
host_add(struct action *act)
{
	char *p, *q;
	size_t len;
	struct thread_host *search, *thr;

	if (act == NULL || act->hostname == NULL)
		return (NULL);

	TAILQ_FOREACH(search, &thread_list, next) {
		len = 0;
		if ((p = strchr(act->hostname, '/')) != NULL)
			len = p - act->hostname;
		if (len > 0 && len <= strlen(act->hostname)) {
			if (strncasecmp(search->hostname, act->hostname, len) != 0)
				continue;
		} else if (strcasecmp(search->hostname, act->hostname) != 0)
			continue;
		search->refcnt++;
		TAILQ_INSERT_TAIL(&search->actions, act, next_actions);
		act->host = search;
		return (search);
	}

	thr = calloc(1, sizeof(*thr));
	if (thr == NULL)
		return (NULL);

	thr->mask = -1;
	thr->mask6 = -1;
	thr->hostname = strdup(act->hostname);
	if ((p = strrchr(thr->hostname, '/')) != NULL) {
		thr->mask = strtol(p + 1, &q, 0);
		thr->mask6 = thr->mask;
		if (!q || *q || thr->mask > 128 || q == (p + 1)) {
			LOG(LOG_WARNING,
			    "invalid netmask '%s' for hostname %s\n", p,
			    thr->hostname);
			free(thr);
			return (NULL);
		}
		*p = '\0';
	}

	thr->refcnt = 1;
	TAILQ_INIT(&thr->rnh);
	TAILQ_INIT(&thr->actions);
	TAILQ_INSERT_TAIL(&thread_list, thr, next);
	TAILQ_INSERT_TAIL(&thr->actions, act, next_actions);
	act->host = thr;

	if (thr->mask >= 0) {
		LOG(LOG_WARNING, "\t\tAdding host %s/%d", thr->hostname,
		    thr->mask);
	} else {
		LOG(LOG_WARNING, "\t\tAdding host %s", thr->hostname);
	}
	return (thr);
}

static void
host_del(struct action *act)
{
	struct thread_host *thr;

	thr = act->host;
	if (thr != NULL) {
		thr->refcnt--;
		TAILQ_REMOVE(&thr->actions, act, next_actions);
		act->host = NULL; /* should cause any associated thread to exit */

		if (act->state > THR_DYING) {
			sem_post(&act->sem);
			sem_wait(&act->exit_sem);
		}
		sem_post(&thr->sem);
	}
}

struct _addr_entry *
addr_add(struct addr_list *head, const char *hostname, struct sockaddr *addr,
    uint32_t flags)
{
	struct _addr_entry *ent, *tmp;
	char buffer[INET6_ADDRSTRLEN];

	TAILQ_FOREACH(tmp, head, entry) {
		if (tmp->addr->sa_family != addr->sa_family)
			continue;
		if (tmp->addr->sa_len != addr->sa_len ||
		    memcmp(addr, tmp->addr, addr->sa_len) != 0)
			continue;
		tmp->flags &= ~ADDR_OLD;
		return (tmp);
	}

	ent = calloc(1, sizeof(*ent));
	ent->flags = ADDR_NEW;
	if (flags & ADDR_STATIC)
		ent->flags |= ADDR_STATIC;
	ent->addr = calloc(1, addr->sa_len);
	memcpy(ent->addr, addr, addr->sa_len);
	TAILQ_INSERT_HEAD(head, ent, entry);

	if (debug >= 3 && hostname != NULL) {
		memset(buffer, 0, sizeof(buffer));
		if (addr->sa_family == AF_INET)
			inet_ntop(addr->sa_family, &satosin(addr)->sin_addr.s_addr,
			    buffer, sizeof(buffer));
		else if (addr->sa_family == AF_INET6)
			inet_ntop(addr->sa_family, &satosin6(addr)->sin6_addr.s6_addr,
			    buffer, sizeof(buffer));
		LOG(LOG_NOTICE, "\t\t\tadding address %s for host %s",
		    buffer, hostname);
	}

	return (ent);
}

void
addr_del(struct addr_list *head, const char *hostname, struct _addr_entry *addr)
{
	char buffer[INET6_ADDRSTRLEN];

	if (debug >= 3 && hostname != NULL) {
		memset(buffer, 0, sizeof(buffer));
		if (addr->addr->sa_family == AF_INET)
			inet_ntop(addr->addr->sa_family,
			    &satosin(addr->addr)->sin_addr.s_addr,
			    buffer, sizeof(buffer));
		else if (addr->addr->sa_family == AF_INET6)
			inet_ntop(addr->addr->sa_family,
			    &satosin6(addr->addr)->sin6_addr.s6_addr,
			    buffer, sizeof(buffer));
		LOG(LOG_NOTICE, "\t\t\tremoving address %s from host %s",
		    buffer, hostname);
	}
	TAILQ_REMOVE(head, addr, entry);
	free(addr->addr);
	free(addr);
}

static void
addr_cleanup(struct addr_list *head, const char *hostname)
{
	struct _addr_entry *ent, *enttmp;

	TAILQ_FOREACH_SAFE(ent, head, entry, enttmp)
		addr_del(head, hostname, ent);
}

static int
host_dns(struct thread_host *thr)
{
	struct addrinfo hints, *res0, *res;
	char buffer[INET6_ADDRSTRLEN];
	int error = 0;

	memset(&hints, 0, sizeof(hints));
	hints.ai_family = AF_UNSPEC;
	hints.ai_socktype = SOCK_DGRAM;
	res0 = NULL;
	error = getaddrinfo(thr->hostname, NULL, &hints, &res0);
	if (error) {
		LOG(LOG_WARNING,
		    "failed to resolve host %s will retry later again.",
		    thr->hostname);
		if (res0 != NULL)
			freeaddrinfo(res0);
		return (-1);
	}

	for (res = res0; res; res = res->ai_next) {
		if (res->ai_addr == NULL) {
			if (debug >=4)
				LOG(LOG_WARNING,
				    "Skipping empty address for hostname %s",
				    thr->hostname);
			continue;
		}
		if (res->ai_family == AF_INET) {
			if (debug > 9)
				LOG(LOG_WARNING,
				    "\t\tfound address %s for host %s",
				    inet_ntop(res->ai_family,
				    res->ai_addr->sa_data + 2, buffer,
				    sizeof buffer), thr->hostname);
		} else if (res->ai_family == AF_INET6) {
			if (debug > 9)
				LOG(LOG_WARNING,
				    "\t\tfound address %s for host %s",
				    inet_ntop(res->ai_family,
					res->ai_addr->sa_data + 6, buffer,
					sizeof buffer), thr->hostname);
		}
		addr_add(&thr->rnh, thr->hostname, res->ai_addr, 0);
	}
	freeaddrinfo(res0);

	return (0);
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
		memcpy(&sin6->sin6_addr,
		    &((struct sockaddr_in6 *)res->ai_addr)->sin6_addr,
		    sizeof(struct in6_addr));
		freeaddrinfo(res);
		result = 1;
	}

	return (result);
}

static void *
check_hostname(void *arg)
{
	struct _addr_entry *ent, *enttmp;
	struct thread_host *thr;
	struct timespec ts;
	struct sockaddr_in in;
	struct sockaddr_in6 in6;
	struct action *act;
	int update;
	int ret;

 	thr = (struct thread_host *)arg;
	if (!thr->hostname)
		return (NULL);

	/* Detect if an IP address was passed in. */
	if (inet_pton(AF_INET, thr->hostname, &in.sin_addr) == 1) {
		in.sin_family = AF_INET;
		in.sin_len = sizeof(in);
		if (thr->mask == -1)
			thr->mask = 32;
		if (thr->mask > 32) {
			LOG(LOG_WARNING,
			    "invalid mask for %s/%d",
			    thr->hostname, thr->mask);
			thr->mask = 32;
		}
		addr_add(&thr->rnh, thr->hostname, (struct sockaddr *)&in,
		    ADDR_STATIC);
	} else if (is_ipaddrv6(thr->hostname, &in6) == 1)
		addr_add(&thr->rnh, thr->hostname, (struct sockaddr *)&in6,
		    ADDR_STATIC);

	thr->state = THR_RUNNING;
	for (;;) {
		clock_gettime(CLOCK_MONOTONIC, &ts);
		ts.tv_sec += interval;
		ts.tv_nsec = 0;

		/* It is safe to ignore the locking here. */
		if (thr->refcnt == 0)
			goto out;

		/*
		 * Avoid deadlocks, retry later when we cannot acquire the main
		 * lock.
		 */
		if (pthread_rwlock_tryrdlock(&main_lock) != 0)
			goto again;

		TAILQ_FOREACH(ent, &thr->rnh, entry) {
			if (ent->flags & ADDR_STATIC)
				continue;
			ent->flags |= ADDR_OLD;
		}

		if (thr->mask == -1)
			(void)host_dns(thr);

		update = 0;
		TAILQ_FOREACH_SAFE(ent, &thr->rnh, entry, enttmp) {
			if (ent->flags & ADDR_NEW) {
				ent->flags &= ~ADDR_NEW;
				update++;
			}
			if (ent->flags & ADDR_OLD) {
				addr_del(&thr->rnh, thr->hostname, ent);
				update++;
			}
		}

		if (update > 0 && debug >= 4)
			LOG(LOG_WARNING, "Change detected on host: %s",
			    thr->hostname);
		TAILQ_FOREACH(act, &thr->actions, next_actions) {
			if (update == 0 && (act->flags & ACT_FORCE) == 0)
				continue;
			sem_post(&act->sem);
		}

		pthread_rwlock_unlock(&main_lock);

again:
		/*
		 * Receiving a signal can interrupt sem_clockwait_np(), So we
		 * loop on error until we get ETIMEDOUT, or drop out if our
		 * semaphore becomes invalid.
		 */
		ret = sem_clockwait_np(&thr->sem, CLOCK_MONOTONIC,
		    TIMER_ABSTIME, &ts, NULL);
		while (ret < 0) {
			if (errno == ETIMEDOUT) {
				break;
			} else if (errno == EINVAL) {
				goto out;
			}
			ret = sem_clockwait_np(&thr->sem, CLOCK_MONOTONIC,
			    TIMER_ABSTIME, &ts, NULL);
		}
		if (debug >= 6) {
			LOG(LOG_WARNING,
			    "\tAwaking from the sleep for hostname %s (%d)",
			    thr->hostname, thr->refcnt);
		}

	}
out:
	thr->state = THR_DYING;

	if (debug >= 4)
		LOG(LOG_INFO, "Cleaning up hostname %s", thr->hostname);
	sem_destroy(&thr->sem);
	TAILQ_REMOVE(&thread_list, thr, next);
	addr_cleanup(&thr->rnh, thr->hostname);
	if (thr->hostname != NULL)
		free(thr->hostname);
	thr->state = THR_STOPPED;
	return (NULL);
}

static int
check_hostname_create(struct thread_host *thr, pthread_attr_t *attr)
{
	if (thr->state != 0)
		return (-1);
	thr->state = THR_STARTING;
	if (debug > 3)
		LOG(LOG_INFO, "Creating a new thread for host %s",
		    thr->hostname);
	if (sem_init(&thr->sem, 0, 0) != 0) {
		return (-1);
	}
	if (pthread_create(&thr->thr_pid, attr, check_hostname, thr) != 0) {
		sem_destroy(&thr->sem);
		return (-1);
	}
	pthread_set_name_np(thr->thr_pid, thr->hostname);

	return (0);
}

static int
clear_config(struct action_list *actlist)
{
	struct action *act;
	int count;

	count = 0;
	if (TAILQ_EMPTY(actlist))
		return (count);
	while ((act = TAILQ_FIRST(actlist)) != NULL) {
		action_del(act, actlist);
		count++;
	}

	return (count);
}

static void *
merge_config(void *arg __unused) {
	struct action_list tmp_action_list, new_action_list;
	struct action *act, *acttmp, *acttmp2, *tmpact;
	struct thread_host *thr;
	int new;
	int count, count1, count2;

	TAILQ_INIT(&tmp_action_list);
	TAILQ_INIT(&new_action_list);
	merge_state = THR_RUNNING;
	for (;;) {
		if (sem_wait(&merge_sem) != 0) {
			LOG(LOG_ERR,
			    "unable to wait on output queue retrying");
			continue;
		}

		if (merge_state == THR_DYING) {
			break;
		}

		LOG(LOG_INFO, "%s: configuration reload", __func__);
		pthread_rwlock_wrlock(&main_lock);
	 	if (!TAILQ_EMPTY(&action_list)) {
			count = 0;
			while ((act = TAILQ_FIRST(&action_list)) != NULL) {
				TAILQ_REMOVE(&action_list, act, next_list);
				TAILQ_INSERT_TAIL(&tmp_action_list, act, next_list);
				count++;
			}
			if (debug > 3)
				LOG(LOG_INFO, "Copied %d actions to old\n",
				    count);
		}

		if (parse_config(file)) {
			LOG(LOG_ERR,
			    "could not parse new configuration file, exiting..."
			    );
			exit(10);
		}
		if (!TAILQ_EMPTY(&action_list)) {
			count = 0;
			while ((act = TAILQ_FIRST(&action_list)) != NULL) {
				TAILQ_REMOVE(&action_list, act, next_list);
				TAILQ_INSERT_TAIL(&new_action_list, act, next_list);
				count++;
			}
			if (debug > 3)
				LOG(LOG_INFO, "Copied %d actions to new\n",
				    count);
		}

		count1 = count2 = 0;
		TAILQ_FOREACH_SAFE(act, &new_action_list, next_list, acttmp) {
			new = 1;
			TAILQ_FOREACH_SAFE(tmpact, &tmp_action_list, next_list,
			    acttmp2) {
				if (tmpact->type != act->type)
					continue;
				if (strlen(tmpact->hostname) != strlen(act->hostname) ||
				    strcmp(tmpact->hostname, act->hostname) != 0)
					continue;
				if ((tmpact->tablename == NULL && act->tablename != NULL) ||
				    (tmpact->tablename != NULL && act->tablename == NULL))
					continue;
				if (tmpact->tablename != NULL && act->tablename != NULL &&
				    (strlen(tmpact->tablename) != strlen(act->tablename) ||
				     strcmp(tmpact->tablename, act->tablename) != 0))
					continue;
				if ((tmpact->anchor == NULL && act->anchor != NULL) ||
				    (tmpact->anchor != NULL && act->anchor == NULL))
					continue;
				if (tmpact->anchor != NULL && act->anchor != NULL &&
				    (strlen(tmpact->anchor) != strlen(act->anchor) ||
				     strcmp(tmpact->anchor, act->anchor) != 0))
					continue;

				/* Remove the new copy and use the existing entry. */
				TAILQ_REMOVE(&tmp_action_list, tmpact, next_list);
				TAILQ_INSERT_HEAD(&action_list, tmpact, next_list);
				action_del(act, &new_action_list);
				new = 0;
				count1++;
				break;
			}
			if (new == 1) {
				TAILQ_REMOVE(&new_action_list, act, next_list);
				TAILQ_INSERT_HEAD(&action_list, act, next_list);
				count2++;
			}
		}
		if (debug > 3) {
			LOG(LOG_INFO,
			    "Loaded actions: %d old and %d new = %d total",
			    count1, count2, count1 + count2);
			LOG(LOG_INFO, "Cleaning up previous actions");
		}
		count = clear_config(&tmp_action_list);
		if (count > 0 && debug > 3)
			LOG(LOG_INFO, "Stopped %d old actions\n", count);

		if (!TAILQ_EMPTY(&new_action_list) ||
		    !TAILQ_EMPTY(&tmp_action_list))
			errx(6, "assert: temporary lists are not empty.");

		TAILQ_FOREACH(act, &action_list, next_list) {
			if (act->state != 0)
				continue;
			if (action_create(act, &g_attr) != 0)
				errx(2, "could not start action thread.");
		}
		TAILQ_FOREACH(thr, &thread_list, next) {
			if (thr->state != 0)
				continue;
			if (check_hostname_create(thr, &g_attr) == -1)
				errx(2, "could not start host thread for %s",
				    thr->hostname);
		}
		/* Make check_hostname() run. */
		TAILQ_FOREACH(thr, &thread_list, next) {
			/* Don't post for threads that aren't running */
			if (thr->state != THR_RUNNING && thr->state != THR_STARTING)
				continue;
			sem_post(&thr->sem);
		}
		pthread_rwlock_unlock(&main_lock);
	}

	pthread_rwlock_wrlock(&main_lock);
	clear_config(&action_list);
	pthread_rwlock_unlock(&main_lock);
	LOG(LOG_INFO, "Waiting 2 seconds for threads to finish");
	sleep(2);
	exit(0);
}

static void
handle_signal(int sig)
{
	if (debug >= 3)
		LOG(LOG_WARNING, "Received signal %s(%d).", strsignal(sig),
		    sig);
	switch (sig) {
	case SIGTERM: /* fallthrough */
	case SIGINT:
		merge_state = THR_DYING;
	case SIGHUP: /* fallthrough */
		sem_post(&merge_sem);
		break;
	default:
		if (debug >= 3)
			LOG(LOG_WARNING, "unhandled signal");
	}
}

static void
filterdns_usage(void)
{
	fprintf(stderr, "usage: filterdns -f -p pidfile -i interval -c filecfg -d debuglevel\n");
	exit(4);
}

int
main(int argc, char *argv[])
{
	FILE *pidfd = NULL;
	char *pidfile = NULL;
	int ch = 0, foreground = 0;
	pthread_t merge_thr;
	sig_t sig_error = 0;
	struct action *act = NULL;
	struct thread_host *thr = NULL;
	uid_t uid = 0;

	/*
	 * Check if filterdns is running as root.  root access is needed later
	 * when dealing with the firewall tables.
	 */
	if ((uid = getuid()) != 0)
		errx(1, "filterdns can only run as root (uid=%d).", uid);

	file = NULL;
	while ((ch = getopt(argc, argv, "c:d:fi:p:v")) != -1) {
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
				fprintf(stderr, "Invalid interval %d\n",
				    interval);
				exit(3);
			}
			break;
		case 'p':
			pidfile = optarg;
			break;
		case 'v':
			printf("Version %%VERSION%%\n");
			exit(0);
			/* NOTREACHED */
			break;
		default:
			filterdns_usage();
			/* NOTREACHED */
			break;
		}
	}

	if (file == NULL) {
		fprintf(stderr, "Configuration file is mandatory!\n");
		filterdns_usage();
		/* NOTREACHED */
		exit(1);
	}

	closefrom(3);
	if (foreground == 0) {
		(void)freopen("/dev/null", "w", stdout);
		(void)freopen("/dev/null", "w", stdin);
	}

	TAILQ_INIT(&action_list);
	TAILQ_INIT(&thread_list);
	if (parse_config(file)) {
		LOG(LOG_ERR, "unable to open configuration file");
		errx(1, "cannot open the configuration file.");
	}

	/* Go to background. */
	if (!foreground && daemon(0, 0) == -1)
		err(1, "daemon: ");

	if (foreground == 0 && pidfile) {
		/* write PID to file */
		pidfd = fopen(pidfile, "w");
		if (pidfd) {
			while (flock(fileno(pidfd), LOCK_EX) != 0)
				;
			fprintf(pidfd, "%d\n", getpid());
			flock(fileno(pidfd), LOCK_UN);
			fclose(pidfd);
		} else {
			LOG(LOG_WARNING, "could not open pid file");
			err(2, "could not open pid file: ");
		}
	}

	/* Catch SIGHUP in order to reload configuration file. */
	sig_error = signal(SIGHUP, handle_signal);
	if (sig_error == SIG_ERR)
		err(EX_OSERR, "unable to set signal handler");
	sig_error = signal(SIGTERM, handle_signal);
	if (sig_error == SIG_ERR)
		err(EX_OSERR, "unable to set signal handler");
	sig_error = signal(SIGINT, handle_signal);
	if (sig_error == SIG_ERR)
		err(EX_OSERR, "unable to set signal handler");

	pthread_attr_init(&g_attr);
	pthread_attr_setdetachstate(&g_attr, PTHREAD_CREATE_DETACHED);

	TAILQ_FOREACH(act, &action_list, next_list) {
		if (action_create(act, &g_attr) != 0)
			errx(2, "could not start action thread.");
	}
	TAILQ_FOREACH(thr, &thread_list, next) {
		if (check_hostname_create(thr, &g_attr) == -1)
			errx(2, "could not start host thread for %s",
			    thr->hostname);
	}

	/* Config reload, exit. */
	sem_init(&merge_sem, 0, 0);
	merge_state = THR_STARTING;
	if (pthread_create(&merge_thr, &g_attr, merge_config, NULL) != 0) {
		if (debug >= 1)
			LOG(LOG_ERR, "Unable to create configuration  merge thread %s",
			    thr->hostname);
	}
	pthread_set_name_np(merge_thr, "merge-thread");

	pthread_exit(NULL);
}
