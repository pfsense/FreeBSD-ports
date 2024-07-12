/*
 * dhcpleases6.c
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2010-2024 Rubicon Communications, LLC (Netgate)
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


/*
 * The parsing code is taken from dnsmasq isc.c file and modified to work
 * in this code.
 */
/* dnsmasq is Copyright (c) 2000-2007 Simon Kelley

   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; version 2 dated June, 1991, or
   (at your option) version 3 dated 29 June, 2007.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
/* Code in this file is based on contributions by John Volpe. */

#include <sys/types.h>
#include <sys/socket.h>
#include <sys/event.h>
#include <sys/time.h>
#include <sys/stat.h>
#include <sys/queue.h>

#include <netinet/in.h>
#include <arpa/nameser.h>
#include <arpa/inet.h>

#include <syslog.h>
#include <stdarg.h>
#include <time.h>
#include <signal.h>

#define _WITH_DPRINTF
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <fcntl.h>
#include <unistd.h>

#include <errno.h>

#define MAXTOK 64
#define PIDFILE	"/var/run/dhcpleases6.pid"

struct isc_lease {
	char *name, *fqdn;
	time_t expires;
	struct in_addr addr;
	LIST_ENTRY(isc_lease) next;
};

LIST_HEAD(isc_leases, isc_lease) leases =
	LIST_HEAD_INITIALIZER(leases);
static char *leasefile = NULL;
static char *HOSTS = NULL;
static FILE *fp = NULL;
static char *domain_suffix = NULL;
static char *command = NULL;
static int hostssize = 0;

/* Check if file exists */
static int
fexist(char * filename)
{
	struct stat buf;

	if (( stat (filename, &buf)) < 0)
		return (0);

	if (! S_ISREG(buf.st_mode))
		return (0);

	return(1);
}

static int
fsize(char * filename)
{
	struct stat buf;

	if (( stat (filename, &buf)) < 0)
		return (-1);

	if (! S_ISREG(buf.st_mode))
		return (-1);

	return(buf.st_size);
}

/*
 * check for legal char a-z A-Z 0-9 -
 * (also / , used for RFC2317 and _ used in windows queries
 * and space, for DNS-SD stuff)
 */
static int
legal_char(char c) {
	if ((c >= 'A' && c <= 'Z') ||
	    (c >= 'a' && c <= 'z') ||
	    (c >= '0' && c <= '9') ||
	    c == '-' || c == '/' || c == '_' || c == ' ')
		return (1);
	return (0);
}

/*
 * check for legal chars and remove trailing .
 * also fail empty string and label > 63 chars
 */
static int
canonicalise(char *s) {
	size_t dotgap = 0, l = strlen(s);
	char c;
	int nowhite = 0;

	if (l == 0 || l > MAXDNAME)
		return (0);

	if (s[l-1] == '.') {
		if (l == 1)
			return (0);
		s[l-1] = 0;
	}

	while ((c = *s)) {
		if (c == '.')
			dotgap = 0;
		else if (!legal_char(c) || (++dotgap > MAXLABEL))
			return (0);
		else if (c != ' ')
			nowhite = 1;
		s++;
	}

	return (nowhite);
}

/* don't use strcasecmp and friends here - they may be messed up by LOCALE */
static int
hostname_isequal(char *a, char *b) {
	unsigned int c1, c2;

	do {
		c1 = (unsigned char) *a++;
		c2 = (unsigned char) *b++;

		if (c1 >= 'A' && c1 <= 'Z')
			c1 += 'a' - 'A';
		if (c2 >= 'A' && c2 <= 'Z')
			c2 += 'a' - 'A';

		if (c1 != c2)
			return 0;
	} while (c1);

	return (1);
}

static int
next_token (char *token, int buffsize, FILE * fp)
{
	int c, count = 0;
	char *cp = token;

	while((c = getc(fp)) != EOF) {
		if (c == '#')
			do {
				c = getc(fp);
			} while (c != '\n' && c != EOF);

		if (c == ' ' || c == '\t' || c == '\n' || c == ';') {
			if (count)
				break;
		} else if ((c != '"') && (count<buffsize-1)) {
			*cp++ = c;
			count++;
		}
	}

	*cp = 0;
	return count ? 1 : 0;
}

/*
 * There doesn't seem to be a universally available library function
 * which converts broken-down _GMT_ time to seconds-in-epoch.
 * The following was borrowed from ISC dhcpd sources, where
 * it is noted that it might not be entirely accurate for odd seconds.
 * Since we're trying to get the same answer as dhcpd, that's just
 * fine here.
 */
static time_t
convert_time(struct tm lease_time) {
	static const int months [11] = { 31, 59, 90, 120, 151, 181,
						212, 243, 273, 304, 334 };
	time_t time = ((((((365 * (lease_time.tm_year - 1970) + /* Days in years since '70 */
			    (lease_time.tm_year - 1969) / 4 +   /* Leap days since '70 */
			    (lease_time.tm_mon > 1		/* Days in months this year */
				? months [lease_time.tm_mon - 2]
				: 0) +
			    (lease_time.tm_mon > 2 &&		/* Leap day this year */
			    !((lease_time.tm_year - 1972) & 3)) +
			    lease_time.tm_mday - 1) * 24) +	/* Day of month */
			    lease_time.tm_hour) * 60) +
			    lease_time.tm_min) * 60) + lease_time.tm_sec;

	return (time);
}

static int
load_dhcp(time_t now) {
	char namebuff[256];
	char *hostname = namebuff;
	char token[MAXTOK], *dot;
	struct in_addr host_address;
	time_t ttd, tts;
	struct isc_lease *lease, *tmp;

	rewind(fp);
	LIST_INIT(&leases);

	while ((next_token(token, MAXTOK, fp))) {
		if (strcmp(token, "lease") == 0) {
			hostname[0] = '\0';
			ttd = tts = (time_t)(-1);
			if (next_token(token, MAXTOK, fp) &&
			    (inet_pton(AF_INET, token, &host_address))) {
				if (next_token(token, MAXTOK, fp) && *token == '{') {
					while (next_token(token, MAXTOK, fp) && *token != '}') {
						if ((strcmp(token, "client-hostname") == 0) ||
						    (strcmp(token, "hostname") == 0)) {
							if (next_token(hostname, MAXDNAME, fp))
								if (!canonicalise(hostname)) {
									*hostname = 0;
									syslog(LOG_ERR, "bad name in %s", leasefile);
								}
						} else if ((strcmp(token, "ends") == 0) ||
							    (strcmp(token, "starts") == 0)) {
								struct tm lease_time;
								int is_ends = (strcmp(token, "ends") == 0);
								if (next_token(token, MAXTOK, fp) &&  /* skip weekday */
								    next_token(token, MAXTOK, fp) &&  /* Get date from lease file */
								    sscanf (token, "%d/%d/%d",
									&lease_time.tm_year,
									&lease_time.tm_mon,
									&lease_time.tm_mday) == 3 &&
								    next_token(token, MAXTOK, fp) &&
								    sscanf (token, "%d:%d:%d:",
									&lease_time.tm_hour,
									&lease_time.tm_min,
									&lease_time.tm_sec) == 3) {
									if (is_ends)
										ttd = convert_time(lease_time);
									else
										tts = convert_time(lease_time);
								}
						}
					}
				/* missing info? */
				if (!*hostname)
					continue;
				if (ttd == (time_t)(-1))
					continue;

				/* We use 0 as infinite in ttd */
				if ((tts != -1) && (ttd == tts - 1))
					ttd = (time_t)0;
				else if (difftime(now, ttd) > 0)
					continue;

				if ((dot = strchr(hostname, '.'))) {
					if (!domain_suffix || hostname_isequal(dot+1, domain_suffix)) {
						syslog(LOG_WARNING,
							"Ignoring DHCP lease for %s because it has an illegal domain part",
							hostname);
						continue;
					}
					*dot = 0;
				}

				LIST_FOREACH(lease, &leases, next) {
					if (hostname_isequal(lease->name, hostname)) {
						lease->expires = ttd;
						lease->addr = host_address;
						break;
					}
				}

				if (!lease) {
					if ((lease = malloc(sizeof(struct isc_lease))) == NULL)
						continue;
					lease->expires = ttd;
					lease->addr = host_address;
					lease->fqdn =  NULL;
					LIST_INSERT_HEAD(&leases, lease, next);
				} else {
					if (lease->name != NULL)
						free(lease->name);
					if (lease->fqdn != NULL)
						free(lease->fqdn);
				}

				if (!(lease->name = malloc(strlen(hostname)+1)))
					free(lease);
				strcpy(lease->name, hostname);
				if ((lease->fqdn = malloc(strlen(hostname) + strlen(domain_suffix) + 2)) != NULL) {
					strcpy(lease->fqdn, hostname);
					strcat(lease->fqdn, ".");
					strcat(lease->fqdn, domain_suffix);
				} else {
					LIST_REMOVE(lease, next);
					free(lease->name);
					free(lease);
				}
				}
			}
		}
	}


	/* prune expired leases */
	LIST_FOREACH_SAFE(lease, &leases, next, tmp) {
		if (lease->expires != (time_t)0 && difftime(now, lease->expires) > 0) {
			if (lease->name)
				free(lease->name);
			if (lease->fqdn)
				free(lease->fqdn);
			LIST_REMOVE(lease, next);
			free(lease);
		}
	}

	return (0);
}

static int
write_status() {
	struct isc_lease *lease;
	struct stat tmp;
	size_t tmpsize;
	int fd;

	fd = open(HOSTS, O_RDWR | O_CREAT | O_FSYNC);
	if (fd < 0)
		return 1;
	if (fstat(fd, &tmp) < 0)
		tmpsize = hostssize;
	else
		tmpsize = tmp.st_size;
	if (tmpsize < hostssize) {
		syslog(LOG_WARNING, "%s changed size from original!", HOSTS);
		hostssize = tmpsize;
	}
	ftruncate(fd, hostssize);
	if (lseek(fd, 0, SEEK_END) < 0) {
		close(fd);
		return 2;
	}
	/* write the tmp hosts file */
	dprintf(fd, "\n# dhcpleases automatically entered\n"); /* put a blank line just to be on safe side */
	LIST_FOREACH(lease, &leases, next) {
		dprintf(fd, "%s\t%s %s\t\t# dynamic entry from dhcpd.leases\n", inet_ntoa(lease->addr),
			lease->fqdn ? lease->fqdn  : "empty", lease->name ? lease->name : "empty");
	}
	close(fd);

	return (0);
}

static void
cleanup() {
	struct isc_lease *lease, *tmp;

	LIST_FOREACH_SAFE(lease, &leases, next, tmp) {
		if (lease->fqdn)
			free(lease->fqdn);
		if (lease->name)
			free(lease->name);
		LIST_REMOVE(lease, next);
		free(lease);
	}

	return;
}

static void
handle_signal(int sig) {
	int size;

	switch(sig) {
		case SIGHUP:
			size = fsize(HOSTS);
			if (hostssize < 0)
				break; /* XXX: exit?! */
			else
				hostssize = size;
			break;
		case SIGTERM:
			unlink(PIDFILE);
			cleanup();
			exit(0);
			break;
		default:
			syslog(LOG_WARNING, "unhandled signal");
	}
}

int
main(int argc, char **argv) {
	struct kevent evlist;    /* events we want to monitor */
	struct kevent chlist;    /* events that were triggered */
	struct sigaction sa;
	time_t	now;
	int kq, nev, leasefd = 0, pidf, ch;

	if (argc != 5) {
	}

	while ((ch = getopt(argc, argv, "c:l:")) != -1) {
		switch (ch) {
		case 'c':
			command = optarg;
			break;
		case 'l':
			leasefile = optarg;
			break;
		default:
			perror("Wrong number of arguments given."); /* XXX: usage */
			exit(2);
			/* NOTREACHED */
		}
	}
	argc -= optind;
	argv += optind;

	if (leasefile == NULL) {
		syslog(LOG_ERR, "lease file is mandatory as parameter");
		perror("lease file is mandatory as parameter");
		exit(1);
	}
	if (!fexist(leasefile)) {
		syslog(LOG_ERR, "lease file needs to exist before starting dhcpleases");
		perror("lease file needs to exist before starting dhcpleases");
		exit(1);
	}

	closefrom(3);

	if (daemon(0, 0) < 0) {
		syslog(LOG_ERR, "Could not daemonize");
		perror("Could not daemonize");
		exit(4);
	}

reopen:
	leasefd = open(leasefile, O_RDONLY);
	if (leasefd < 0) {
		syslog(LOG_ERR, "Could not get descriptor");
		perror("Could not get descriptor");
		exit(6);
	}

	fp = fdopen(leasefd, "r");
	if (fp == NULL) {
		syslog(LOG_ERR, "could not open leases file");
		perror("could not open leases file");
		exit(5);
	}

	pidf = open(PIDFILE, O_RDWR | O_CREAT | O_FSYNC);
	if (pidf < 0)
		syslog(LOG_ERR, "could not write pid file, %m");
	else {
		ftruncate(pidf, 0);
		dprintf(pidf, "%u\n", getpid());
		close(pidf);
	}

	/*
	 * Catch SIGHUP in order to reread configuration file.
	 */
	sa.sa_handler = handle_signal;
	sa.sa_flags = SA_SIGINFO|SA_RESTART;
	sigemptyset(&sa.sa_mask);
	if (sigaction(SIGHUP, &sa, NULL) < 0) {
		syslog(LOG_ERR, "unable to set signal handler, %m");
		exit(9);
	}
	if (sigaction(SIGTERM, &sa, NULL) < 0) {
		syslog(LOG_ERR, "unable to set signal handler, %m");
		exit(10);
	}

	/* Create a new kernel event queue */
	if ((kq = kqueue()) == -1)
		exit(1);

	now = time(NULL);
	if (command == NULL) {
		load_dhcp(now);

		write_status();
		//syslog(LOG_INFO, "written temp hosts file after modification event.");

		cleanup();
		//syslog(LOG_INFO, "Cleaned up.");
	} else {
		system(command);
	}

	/* Initialise kevent structure */
	EV_SET(&chlist, leasefd, EVFILT_VNODE, EV_ADD | EV_CLEAR | EV_ENABLE | EV_ONESHOT,
		NOTE_WRITE | NOTE_ATTRIB | NOTE_DELETE | NOTE_RENAME | NOTE_LINK, 0, NULL);
	/* Loop forever */
	for (;;) {
		nev = kevent(kq, &chlist, 1, &evlist, 1, NULL);
		if (nev == -1)
			perror("kevent()");
		else if (nev > 0) {
			if (evlist.flags & EV_ERROR) {
				syslog(LOG_ERR, "EV_ERROR: %s\n", strerror(evlist.data));
				break;
			}
			if ((evlist.fflags & NOTE_DELETE) || (evlist.fflags & NOTE_RENAME)) {
				close(leasefd);
				goto reopen;
			}
			now = time(NULL);
			if (command != NULL)
				system(command);
			else {
				load_dhcp(now);

				write_status();
				//syslog(LOG_INFO, "written temp hosts file after modification event.");

				cleanup();
				//syslog(LOG_INFO, "Cleaned up.");
			}
		}
	}

	fclose(fp);
	unlink(PIDFILE);
	return (0);
}
