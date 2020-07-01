/*
 * dhcpleases.c
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2010 Rubicon Communications, LLC (Netgate)
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

/* Unbound synchronization via unbound-control
	by Alexander Berkes <office@metasoft.at>
*/

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
#define PIDFILE	"/var/run/dhcpleases.pid"

struct isc_lease {
	char *name, *fqdn;
	time_t expires;
	struct in_addr addr;
	LIST_ENTRY(isc_lease) next;
};

LIST_HEAD(isc_leases, isc_lease) leases = LIST_HEAD_INITIALIZER(leases);
static char *HOSTS = NULL;
static char *domain_suffix = NULL;
static int hostssize = 0;
static int foreground = 0;
static int bulk_remove = 0;

static int fexist(char *);
static int fsize(char *);
static int legal_char(char);
static int canonicalise(char *);
static int hostname_isequal(char *, char *);
static int next_token (char *, int, FILE *);
static time_t convert_time(struct tm);
static int load_dhcp(FILE *, char *, char *, time_t);

static int write_status(void);
static int write_unbound_diff(void);
static void cleanup(void);
static void handle_signal(int);

static int exec_and_cb(char *cmd, int (*callback)(FILE *fp));
static int print_cb(FILE *fp);
static int bulk_cb(FILE *fp);
static int del_cb(FILE *fp);
static char* reverse_ip(char* ip);
static int sync_unbound(void);
static void log_kqueue_errno(void);
static void log_kevent_errno(void);

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
legal_char(char c)
{
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
canonicalise(char *s)
{
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
hostname_isequal(char *a, char *b)
{
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
	int c, count = 0, quotes = 0;
	char *cp = token;

	while((c = getc(fp)) != EOF) {
		if (c == '#')
			do {
				c = getc(fp);
			} while (c != '\n' && c != EOF);

		if (c == '"')
			quotes = (quotes == 0 ? 1 : 0);
		if ((c == ' ' && quotes == 0) || c == '\t' || c == '\n' ||
		    c == ';') {
			if (count)
				break;
		} else if ((c != '"') && (count<buffsize-1)) {
			*cp++ = c;
			count++;
		}
	}
	*cp = 0;
#if DEBUG
	printf("|TOEN: %s, %d\n", token, count);
#endif
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
convert_time(struct tm lease_time)
{
	static const int months [11] = {
	    31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334
	};
	time_t time =
	    ((((((365 * (lease_time.tm_year - 1970) + /* Days in years since '70 */
	    (lease_time.tm_year - 1969) / 4 +	/* Leap days since '70 */
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

static void
fix_hostname_spaces(char *hostname)
{
	unsigned int i;

	for (i = 0; i < strlen(hostname); i++) {
		if (*(hostname+i) == ' ') {
			*(hostname+i) = '-';
		}
	}
}

static int
load_dhcp(FILE *fp, char *leasefile, char *domain_sufix, time_t now)
{
	char namebuff[256];
	char *hostname = namebuff, *suffix = NULL;
	char token[MAXTOK], *dot;
	struct in_addr host_address;
	time_t ttd, tts;
	struct isc_lease *lease, *tmp;

	rewind(fp);
	LIST_INIT(&leases);

	while ((next_token(token, MAXTOK, fp))) {
		if (strcmp(token, "lease") != 0) {
			continue;
		}

		*hostname = 0;
		ttd = tts = (time_t)(-1);

		if (!next_token(token, MAXTOK, fp) ||
		   (!inet_pton(AF_INET, token, &host_address))) {
			continue;
		}

		if (next_token(token, MAXTOK, fp) && *token == '{') {
			while (next_token(token, MAXTOK, fp) && *token != '}') {
#if DEBUG
				printf("token: %s\n", token);
#endif
				if ((strcmp(token, "client-hostname") == 0) ||
				    (strcmp(token, "hostname") == 0)) {
					if (!next_token(hostname, MAXDNAME,
					    fp)) {
						continue;
					}

					if (*hostname == '}') {
						*hostname = 0;
					} else if (!canonicalise(hostname)) {
						if (foreground)
							printf(
							    "bad name(%s) in %s\n",
							    hostname,
							    leasefile);
						else
							syslog(LOG_ERR,
							    "bad name in %s",
							    leasefile);
						*hostname = 0;
					} else
						fix_hostname_spaces(hostname);
				} else if ((strcmp(token, "ends") == 0) ||
				    (strcmp(token, "starts") == 0)) {
					struct tm lease_time;
					int is_ends = (strcmp(token, "ends") ==
					    0);
					/* skip weekday */
					if (next_token(token, MAXTOK, fp) &&
					    /* Get date from lease file */
					    next_token(token, MAXTOK, fp) &&
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
							ttd = convert_time(
							    lease_time);
						else
							tts = convert_time(
							    lease_time);
					}
				}
			}
		}

		/* missing info? */
		if (!*hostname)
			continue;
		if (ttd == (time_t)(-1))
			ttd = (time_t)0;

		/* We use 0 as infinite in ttd */
		if ((tts != -1) && (ttd == tts - 1))
			ttd = (time_t)0;

		if ((dot = strchr(hostname, '.'))) {
			if (!domain_suffix ||
			    hostname_isequal(dot+1, domain_suffix)) {
				if (foreground)
					printf(
					    "Other suffix in DHCP lease for %s",
					    hostname);
				else
					syslog(LOG_WARNING,
					    "Other suffix in DHCP lease for %s",
					    hostname);

				suffix = (dot + 1);
				*dot = 0;
			} else
				suffix = domain_suffix;
		} else
			suffix = domain_suffix;

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
			lease->fqdn = NULL;
			lease->name = NULL;
			LIST_INSERT_HEAD(&leases, lease, next);
		} else {
			if (lease->fqdn != NULL)
				free(lease->fqdn);
			if (lease->name != NULL)
				free(lease->name);
		}

		if (foreground)
			printf("Found hostname: %s.%s\n", hostname, suffix);

		if (asprintf(&lease->name, "%s", hostname) < 0) {
			LIST_REMOVE(lease, next);
			if (lease->name != NULL)
				free(lease->name);
			if (lease->fqdn != NULL)
				free(lease->fqdn);
			free(lease);
		}
		if (asprintf(&lease->fqdn, "%s.%s", hostname, suffix) < 0) {
			LIST_REMOVE(lease, next);
			if (lease->name != NULL)
				free(lease->name);
			if (lease->fqdn != NULL)
				free(lease->fqdn);
			free(lease);
		}
	}

	/* prune expired leases */
	LIST_FOREACH_SAFE(lease, &leases, next, tmp) {
		if (lease->expires != (time_t)0 &&
		    difftime(now, lease->expires) > 0) {
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
write_status()
{
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
		if (foreground)
			printf("%s changed size from original!", HOSTS);
		else
			syslog(LOG_WARNING, "%s changed size from original!",
			    HOSTS);
		hostssize = tmpsize;
	}
	ftruncate(fd, hostssize);
	if (lseek(fd, 0, SEEK_END) < 0) {
		close(fd);
		return 2;
	}
	/* write the tmp hosts file */
	/* put a blank line just to be on safe side */
	dprintf(fd, "\n# dhcpleases automatically entered\n");
	LIST_FOREACH(lease, &leases, next) {
		if (foreground)
			printf(
			    "%s\t%s %s\t\t# dynamic entry from dhcpd.leases\n",
			    inet_ntoa(lease->addr),
			    lease->fqdn ? lease->fqdn  : "empty",
			    lease->name ? lease->name : "empty");
		else
			dprintf(fd,
			    "%s\t%s %s\t\t# dynamic entry from dhcpd.leases\n",
			    inet_ntoa(lease->addr),
			    lease->fqdn ? lease->fqdn  : "empty",
			    lease->name ? lease->name : "empty");
	}
	close(fd);

	return (0);
}

static char* 
reverse_ip(char* ip)
{
	in_addr_t addr;
	size_t size = sizeof(char) * INET_ADDRSTRLEN;
	char* reversed_ip = malloc(size);
	/* Get the textual address into binary format */
	inet_pton(AF_INET, ip, &addr);

	/* Reverse the bytes in the binary address */
	addr =
		((addr & 0xff000000) >> 24) |
		((addr & 0x00ff0000) >>  8) |
		((addr & 0x0000ff00) <<  8) |
		((addr & 0x000000ff) << 24);

	/* And lastly get a textual representation back again */
	inet_ntop(AF_INET, &addr, reversed_ip, size);
	return (reversed_ip);
}

static int 
write_unbound_diff()
{
	struct isc_lease *lease;
	FILE* fp;

	fp = popen("sort > /tmp/dhcpleases.sort && \
					/usr/local/sbin/unbound-control -c /var/unbound/unbound.conf view_list_local_data dhcpleases | \
					sed '/^$/d' | \
					awk '{print $1\" \"$4\" \"$5}' | \
					sort | \
					diff /tmp/dhcpleases.sort - > /tmp/dhcpleases.diff && rm /tmp/dhcpleases.sort", "w");
	
	if (fp == NULL) {
   	syslog(LOG_ERR, "Failed to write unbound dhcpleases diff: %m\n");
    	return (1);
  	}

	LIST_FOREACH(lease, &leases, next) {
		if (!lease->fqdn)
			continue;
		
		char *addr = inet_ntoa(lease->addr);
		char* reversed_ip = reverse_ip(addr);

		if (foreground) {
			printf("%s. A %s\n", lease->fqdn, addr);
			printf("%s.in-addr.arpa. PTR %s.\n", reversed_ip, lease->fqdn);
		} else {
			fprintf(fp, "%s. A %s\n", lease->fqdn, addr);
			fprintf(fp, "%s.in-addr.arpa. PTR %s.\n", reversed_ip, lease->fqdn);
		}

		free(reversed_ip);
	}

	pclose(fp);

	return (0);
}

static void
truncate_hosts()
{
	int fd;
	size_t tmpsize;
	struct stat tmp;

	fd = open(HOSTS, O_RDWR | O_CREAT | O_FSYNC);
	if (fd < 0)
		return;
	if (fstat(fd, &tmp) < 0)
		tmpsize = hostssize;
	else
		tmpsize = tmp.st_size;
	if (tmpsize < hostssize) {
		if (foreground)
			printf("%s changed size from original!", HOSTS);
		else
			syslog(LOG_WARNING, "%s changed size from original!",
			    HOSTS);
		hostssize = tmpsize;
	}
	ftruncate(fd, hostssize);
	close(fd);
}

static void
cleanup()
{
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
handle_signal(int sig)
{
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
			truncate_hosts();
			unlink(PIDFILE);
			cleanup();
			exit(0);
			break;
		default:
			syslog(LOG_WARNING, "unhandled signal");
	}
}

static int 
exec_and_cb(char *cmd, int (*callback)(FILE *fp))
{
	FILE *fp = popen(cmd, "r");

	if (fp == NULL) {
   	syslog(LOG_ERR, "Failed to run command: %s Reason: %m\n", cmd);
    	return (1);
  	}

	callback(fp);
	pclose(fp);
	return (0);
}

static int 
print_cb(FILE *fp)
{
	char buf[256];

	while (fgets(buf, sizeof(buf), fp) != NULL) {
		if(foreground) printf("%s", buf);
	}

	return (0);
}

static int 
bulk_cb(FILE *fp)
{
	char buf[256];

	while (fgets(buf, sizeof(buf), fp) != NULL) {
		syslog(LOG_ERR, "%s", buf);
	}

	return (0);
}

static int 
delete_cb(FILE *fp)
{
	char buf[512];
	int deleted = 0;
	char *del_cmd = malloc(512);

	while (fgets(buf, sizeof(buf), fp) != NULL) {	
		snprintf(del_cmd, 512, "/usr/local/sbin/unbound-control -c /var/unbound/unbound.conf view_local_data_remove dhcpleases %s", buf);
		if(exec_and_cb(del_cmd, print_cb) == 0){
			deleted++;
		}
	}

	free(del_cmd);
	syslog(LOG_ERR, "deleted %d datas\n", deleted);
	return (0);
}

static int
sync_unbound()
{
	int error = 0;
	syslog(LOG_ERR, "start sync with unbound");
	
	// remove obsolete leases
	if(!bulk_remove){
		error = exec_and_cb("grep \">\" /tmp/dhcpleases.diff | \
						awk '{print $2}'", 
						delete_cb) != 0;
	}
	else {
		error = exec_and_cb("grep \">\" /tmp/dhcpleases.diff | \
						awk '{print $2}' | \
						/usr/local/sbin/unbound-control -c /var/unbound/unbound.conf \
							view_local_datas_remove dhcpleases", 
						bulk_cb) != 0;
	}

	if(!error &&
		// add missing leases
		exec_and_cb("grep \"<\" /tmp/dhcpleases.diff | \
						awk '{print $2\" \"$3\" \"$4}' | \
						/usr/local/sbin/unbound-control -c /var/unbound/unbound.conf \
							view_local_datas dhcpleases", 
						bulk_cb) == 0){
		syslog(LOG_ERR, "sync done.");
		return (0);
	}
	else {
		syslog(LOG_ERR, "sync failed.");
		return (1);
	}
}

static void
log_kqueue_errno(){
	char *msg;

	switch(errno){
		case ENOMEM:
			msg = "The kernel failed to allocate enough memory for \
					the kernel queue.";
		case EMFILE: 
			msg = "The per-process descriptor table is full.";
		case ENFILE:
			msg = "The system file table is full.";
		default:
			msg = "unknown";
	}

	syslog(LOG_ERR, "kqueue error: %s", msg);
}

static void
log_kevent_errno(){
	char *msg;

	switch(errno){
		case EACCES:
			msg = "The process does not have permission to register a filter.";
		break;
		case EFAULT:
			msg = "There was an error reading or writing the kevent structure.";
		break;
		case EBADF:
			msg = "The specified descriptor is invalid.";
		break;
		case EINTR:
			msg = "A signal was delivered before the timeout expired and \
					before any events were placed on the kqueue for return.";
		break;
		case EINVAL:
			msg = "The specified time limit or filter is invalid.";
		break;
		case ENOENT:
			msg = "The event could not be found to be modified or deleted.";
		break;
		case ENOMEM:
			msg = "No memory was available to register the event or, \
					in the special case of a timer, the maximum number \
					of timers has been exceeded.  This maximum is configurable \
					via the kern.kq_calloutmax sysctl.";
		break;
		case ESRCH:
			msg = "The specified process to attach to does not exist.";
		break;
		default:
			msg = "unknown";
	}

	syslog(LOG_ERR, "kevent error: %s", msg);
}

void
usage(char * name){
	char *usage = 
"\n\
Usage:	%s [Options]\n\n\
Options:\n\
-c <command>               run <command> on lease changes instead of usual behaviour\n\
-d <domain suffix>	   domain suffix appended to hostnames (default: local)\n\
-f	                   run in foreground\n\
-h <hosts file>            system hosts file (usually /etc/hosts) (mandatory if started in background)\n\
-l <dhcp leases file>      dhcp leases file (mandatory)\n\
-u <unbound pid nr.>       unbound pid number (mandatory if started in background)\n\
-b                         use bulk delete operations to delete dns entries\n\
                           for unbound-control versions supporting command: view_local_datas_remove\n\n";
	asprintf(&usage, usage, name);
	printf("%s", usage);
}

int
main(int argc, char **argv)
{
	char *command, *domain_sufix, *leasefile;
	FILE *fp;
	struct kevent *ke = malloc(sizeof(struct kevent) * 2); /* monitored/triggered events */
	struct sigaction sa;
	time_t	now;
	int kq, leasefd, pidf, ch;
	int unbound_pid;
	char *ptr;

	command = NULL;
	domain_sufix = NULL;
	leasefile = NULL;
	unbound_pid = 0;
	
	while ((ch = getopt(argc, argv, "c:d:fh:l:u:b")) != -1) {
		switch (ch) {
		case 'c':
			command = optarg;
			break;
		case 'd':
			domain_suffix = optarg;
			break;
		case 'f':
			foreground = 1;
			break;
		case 'h':
			HOSTS = optarg;
			break;
		case 'l':
			leasefile = optarg;
			break;
		case 'u':
			unbound_pid = strtol(optarg, &ptr, 10);
			if(unbound_pid <= 0 || strlen(ptr) != 0){
				syslog(LOG_ERR, "Wrong unbound pid: %s", optarg);
				printf("Wrong unbound pid: %s\n", optarg);
				exit(1);
			}
			break;
		case 'b':
			bulk_remove = 1;
			break;
		default:
			usage(argv[0]);
			exit(2);
		}
	}
	argc -= optind;
	argv += optind;

	if (leasefile == NULL) {
		syslog(LOG_ERR, "Lease file is mandatory as parameter.");
		printf("Lease file is mandatory as parameter.\n");
		usage(argv[0]);
		exit(1);
	}
	if (!fexist(leasefile)) {
		syslog(LOG_ERR,
		    "Lease file needs to exist before starting dhcpleases.");
		printf(
		    "Lease file needs to exist before starting dhcpleases.\n");
		exit(1);
	}
	if (domain_suffix == NULL) {
		syslog(LOG_ERR,
		    "A domain suffix is not passed as argument using 'local' as suffix.");
		printf(
		    "A domain suffix is not passed as argument using 'local' as suffix.\n");
		domain_suffix = "local";
	}

	if (unbound_pid <= 0 && !foreground) {
		syslog(LOG_ERR, "Unbound pid argument not passed. It is mandatory if run in background.");
		printf("Unbound pid argument not passed. It is mandatory if run in background.\n");
		usage(argv[0]);
		exit(1);
	}

	if (!foreground) {
		if (HOSTS == NULL) {
			syslog(LOG_ERR,
			    "You need to specify the hosts file path. It is mandatory if run in background.");
			printf("You need to specify the hosts file path. It is mandatory if run in background.\n");
			usage(argv[0]);
			exit(8);
		}
		if (!fexist(HOSTS)) {
			syslog(LOG_ERR, "Hosts file %s does not exist!", HOSTS);
			printf(
			    "Hosts file passed as parameter does not exist.\n");
			exit(8);
		}

		if ((hostssize = fsize(HOSTS)) < 0) {
			syslog(LOG_ERR, "Error while getting %s file size.",
			    HOSTS);
			printf("Error while getting /etc/hosts file size.\n");
			exit(6);
		}

		closelog();
		closefrom(3);

		if (daemon(0, 0) < 0) {
			perror("Could not daemonize.");
			syslog(LOG_ERR, "Could not daemonize.");
			exit(4);
		}

		pidf = open(PIDFILE, O_RDWR | O_CREAT | O_FSYNC);
		if (pidf < 0)
			syslog(LOG_ERR, "Could not write pid file, %m.");
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
			syslog(LOG_ERR, "Unable to set signal handler, %m.");
			exit(9);
		}
		if (sigaction(SIGTERM, &sa, NULL) < 0) {
			syslog(LOG_ERR, "Unable to set signal handler, %m.");
			exit(10);
		}

		/* Create a new kernel event queue */
		if ((kq = kqueue()) == -1){
			log_kqueue_errno();
			exit(1);
		}
	}

reopen:
	leasefd = open(leasefile, O_RDONLY);
	if (leasefd < 0) {
		perror("Could not get descriptor.");
		syslog(LOG_ERR, "Could not get descriptor.");
		exit(6);
	}

	fp = fdopen(leasefd, "r");
	if (fp == NULL) {
		perror("Could not open leases file.");
		syslog(LOG_ERR, "Could not open leases file.");
		exit(5);
	}

	now = time(NULL);
	if (command == NULL) {
		load_dhcp(fp, leasefile, domain_sufix, now);
		write_status();

		if (unbound_pid){
			write_unbound_diff();
			
			if(!foreground){
				sync_unbound();
			}
		}

		cleanup();
	}

	if (!foreground) {
		/* Initialise kevent structure */
		int ec = 0;

		if(unbound_pid){
			EV_SET(
				ke, 
				unbound_pid, 
				EVFILT_PROC, 
				EV_ADD, 
				NOTE_EXIT, 
				0, 
				NULL
			);
			ec++;
		}

		EV_SET(
			ke+ec, 
			leasefd, 
			EVFILT_VNODE,
		   EV_ADD | EV_CLEAR | EV_ENABLE,
			NOTE_WRITE | NOTE_EXTEND | NOTE_DELETE | NOTE_RENAME | NOTE_LINK,
		   0, 
			NULL
		);
		ec++;		
		
		/* Register for the event(s) */
    	if(kevent(kq, ke, ec, NULL, 0, NULL) < 0)
        log_kevent_errno();

		/* Loop forever */
		for (;;) {
			memset(ke, 0x00, sizeof(struct kevent));
			if(kevent(kq, NULL, 0, ke, 1, NULL) < 0)
            log_kevent_errno();
			
			if (ke->flags & EV_ERROR) {
				syslog(LOG_ERR, "EV_ERROR: %s\n",
						strerror(ke->data));
				break;
			}

			if(ke->filter == EVFILT_VNODE) {	
				if ((ke->fflags & NOTE_DELETE) ||
				    (ke->fflags & NOTE_RENAME)) {
					break;
				}
				now = time(NULL);
				if (command != NULL)
					system(command);
				else {
					load_dhcp(fp, leasefile, domain_sufix,
					    now);

					write_status();

					if(unbound_pid) {
						write_unbound_diff();
						sync_unbound();
					}

					cleanup();
				}
			}
			else if(ke->filter == EVFILT_PROC){
				syslog(LOG_ERR, "Exiting, because unbound process exited.");
				exit(11);
			}
		}

		fclose(fp);
		close(leasefd);
		goto reopen;
	}

	fclose(fp);
	unlink(PIDFILE);
	return (0);
}
