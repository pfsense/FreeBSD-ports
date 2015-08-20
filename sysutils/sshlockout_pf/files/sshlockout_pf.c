/*
 *  v2.01
 *  SSHLOCKOUT_PF.C 
 *  Originally written by Matthew Dillon
 *  Heavily modified to use PF tables by Scott Ullrich and
 *  extened to keep a database of last 256 bad attempts 
 *  (MAXLOCKOUTS) and block user if they go over (MAXATTEMPTS).
 *
 *  Rewrite from Ermal Luci to be 21st century compatible.
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 *  
 *   1. Redistributions of source code must retain the above copyright notice,
 *      this list of conditions and the following disclaimer.
 *  
 *   2. Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in the
 *      documentation and/or other materials provided with the distribution.
 *  
 *  THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *  INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *  AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *  AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *  OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *  SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *  INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *  CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *  POSSIBILITY OF SUCH DAMAGE.
 *
 *  Use: pipe syslog auth output to this program.  e.g. in /etc/syslog.conf:
 *
 *   auth.info;authpriv.info				/var/log/auth.log
 *   auth.info;authpriv.info				|exec /path/to/sshlockout_pf
 *
 *  Detects failed ssh login and attempts to map out the originating IP
 *  using PF's tables.
 *
 *  Setup instructions:
 *   setup a rule in your pf ruleset (near the top) similar to:
 *   table <sshlockout> persist
 *   block in log quick from <sshlockout> to any label "sshlockout"
 *
 */

#include <sys/types.h>
#include <sys/queue.h>
#include <sys/socket.h>
#include <sys/ioctl.h>

#include <net/if.h>
#include <net/pfvar.h>
#include <netinet/in.h>
#include <arpa/inet.h>

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <stdarg.h>
#include <syslog.h>
#include <time.h>
#include <fcntl.h>
#include <unistd.h>
#include <err.h>

#include <pthread.h>

// Non changable globals
#define VERSION	"3.0"

static int dev = -1;

// Allow overriding
int MAXATTEMPTS = 15;

// Wall of shame (invalid login DB)
struct sshlog 
{
	TAILQ_ENTRY(sshlog)	entry;
	// IP ADDR Octets
	int n1, n2, n3, n4;
	// Invalid login attempts
	int attempts;
	// Last invalid timestamp
	time_t l_ts;
	// First invalid timestamp
	time_t f_ts;
	// GC or not
	int inactive;
};

pthread_rwlock_t db_lock;
#define LOCK_INIT    pthread_rwlock_init(&db_lock, NULL)
#define RLOCK        pthread_rwlock_rdlock(&db_lock)
#define UNLOCK       pthread_rwlock_unlock(&db_lock)
#define WLOCK        pthread_rwlock_wrlock(&db_lock)

TAILQ_HEAD(, sshlog) lockouts;

enum action {
	RELEASE,
	BLOCK,
};

// Function declarations
static void doaction(char *, char *, enum action);
static int check_for_string(char *, char *, char *buf, enum action);
static void *prune_24hour_records(void *);
static void pf_tableentry(char *, int, int, int, int);

static void
pf_tableentry(char *tablename, int n1, int n2, int n3, int n4)
{
	struct pfioc_table io;
	struct pfr_table table;
	struct pfr_addr addr;
	char buf[16] = { 0 };

	bzero(&table, sizeof(table));
	if (strlcpy(table.pfrt_name, tablename,
		sizeof(table.pfrt_name)) >= sizeof(table.pfrt_name)) {
		syslog(LOG_ERR, "could not add address to table %s", tablename);
		return;
	}

	bzero(&addr, sizeof(addr));
	addr.pfra_af = AF_INET;
	addr.pfra_net = 32;
	snprintf(buf, sizeof(buf), "%d.%d.%d.%d", n1, n2, n3, n4);
	addr.pfra_ip4addr.s_addr = inet_addr(buf);

	bzero(&io, sizeof io);
	io.pfrio_table = table;
	io.pfrio_buffer = &addr;
	io.pfrio_esize = sizeof(addr);
	io.pfrio_size = 1;

	if (ioctl(dev, DIOCRADDADDRS, &io))
		syslog(LOG_ERR, "Error adding entry %s to table %s.\n", buf, tablename);
}

// Prune records older than 24 hours
static void *
prune_24hour_records(void *arg __unused) 
{
	struct timespec sleep;
	struct sshlog *sshlog, *tmp;
	time_t ts = 0;

	/* wakeup every 1/2 hour */
	sleep.tv_sec = 60 * 30;
	sleep.tv_nsec = 0;

	for (;;) {
		// Reference time.
		ts = time(NULL);

		WLOCK;
		TAILQ_FOREACH_SAFE(sshlog, &lockouts, entry, tmp) {
			// Check to see if item is older than
			// 24 hours.
			if (difftime(ts, sshlog->f_ts) > 86400 ||
			    sshlog->inactive > 0) {
				TAILQ_REMOVE(&lockouts, sshlog, entry);
				free(sshlog);
			}
		}
		UNLOCK;
		nanosleep(&sleep, 0);
	}
}

// Start of program - main loop
int
main(int argc, char *argv[])
{
	char buf[1024] = { 0 };
	pthread_t GC;
	int attempts;

	if (argc != 2) {
		fprintf(stderr, "Invalid attempts count %d.  Use a numeric value from 1-9999\n", attempts);
		exit(3);
	}
	attempts = atoi(argv[1]);

	// Set MAXATTEMPTS to the first argv argument
	MAXATTEMPTS = attempts;

	// Initialize time conversion information
	tzset();

	// Open up stderr and stdout to the abyss
	(void)freopen("/dev/null", "w", stdout);
	(void)freopen("/dev/null", "w", stderr);
	closefrom(4);

	// Open syslog file
	openlog("sshlockout", LOG_PID|LOG_CONS, LOG_AUTH|LOG_AUTHPRIV);

	// We are starting up
	syslog(LOG_NOTICE, "sshlockout/webConfigurator v%s starting up", VERSION);

	// Init DB
	TAILQ_INIT(&lockouts);

	dev = open("/dev/pf", O_RDWR);
	if (dev < 0)
		errx(1, "Could not open device.");

	pthread_create(&GC, NULL, prune_24hour_records, NULL);

	LOCK_INIT;

	// Loop through reading in syslog stream looking for
	// for specific strings that indicate that a user has
	// attempted login but failed.
	while (fgets(buf, (int)sizeof(buf), stdin) != NULL) 
	{
		printf("%s", buf);
		/* if this is not sshd or webConfigurator related, continue on without processing */
		if (strstr(buf, "sshd") == NULL && strstr(buf, "webConfigurator") == NULL)
			continue;
		// Check for various bad (or good!) strings in stream
		if (check_for_string("Failed password for root from", "sshlockout", buf, BLOCK))
			continue;
		else if (check_for_string("Failed password for admin from", "sshlockout",  buf, BLOCK))
			continue;
		else if (check_for_string("Failed password for invalid user", "sshlockout", buf, BLOCK))
			continue;
		else if (check_for_string("Illegal user", "sshlockout", buf, BLOCK))
			continue;
		else if (check_for_string("Invalid user", "sshlockout", buf, BLOCK))
			continue;
		else if (check_for_string("webConfigurator authentication error for", "webConfiguratorlockout", buf, BLOCK))
			continue;
		else if (check_for_string("authentication error for", "sshlockout", buf, BLOCK))
			continue;
		else if (check_for_string("Successful webConfigurator login for user", "webConfiguratorlockout", buf, RELEASE))
			continue;
		else if (check_for_string("Accepted keyboard-interactive/pam for", "sshlockout", buf, RELEASE))
			continue;
	}

	pthread_join(GC, NULL);

	// stop GC
	pthread_cancel(GC);

	// We are exiting
	syslog(LOG_NOTICE, "sshlockout/webConfigurator v%s exiting", VERSION);

	// That's all folks.
	return(0);
}

static int
check_for_string(char *str, char *lockouttable, char *buf, enum action act)
{
	char *tmpstr = NULL;

	if ((str = strstr(buf, str)) != NULL) 
	{
		if ((tmpstr = strstr(str, " from")) != NULL) {
			if (strlen(tmpstr) > 5)
				doaction(tmpstr + 5, lockouttable, act);
		}
		return (1);
	}

	return (0);
}

static void
doaction(char *str, char *lockouttable, enum action act)
{
	struct sshlog *sshlog;
	// IP address octets
	int n1 = 0, n2 = 0, n3 = 0, n4 = 0;

	// Check passed string and parse out the IP address
	if (sscanf(str, "%d.%d.%d.%d", &n1, &n2, &n3, &n4) != 4)
		return;

	// Check to see if hosts IP is in our lockout table checking
	// how many attempts.   If the attempts are over MAXATTEMPTS then 
	// purge the host from the table and leave shouldblock = true
	RLOCK;
	TAILQ_FOREACH(sshlog, &lockouts, entry) {
		if (sshlog->inactive > 0)
			continue;
		// Try to find the IP in DB
		if (sshlog->n1 == n1 &&
			sshlog->n2 == n2 &&
			sshlog->n3 == n3 &&
			sshlog->n4 == n4) 
		{
			// Found the record, record the attempt
			sshlog->attempts++;
			sshlog->l_ts = time(NULL);
			
			/* Just wanted to remove from history */
			if (act == RELEASE) {
				sshlog->inactive = 1;
				UNLOCK;
				return;
			}

			// Check to see if user is above or == MAXATTEMPTS
			if(sshlog->attempts >= MAXATTEMPTS) {
				break;
			}
		}
	}
	UNLOCK;

	// Entry not found, lets add it to the DB
	if (sshlog == NULL) {
		sshlog = calloc(1, sizeof(*sshlog));
		if (sshlog == NULL) {
			syslog(LOG_ERR, "Could not allocate memory for new enrtry in DB!");
			return;
		}
		sshlog->n1 = n1;
		sshlog->n2 = n2;
		sshlog->n3 = n3;
		sshlog->n4 = n4;
		sshlog->f_ts = time(NULL);
		sshlog->l_ts = -1;
		sshlog->attempts = 1; /* First time */
		/* Put it at the head so we can find it faster. */
		WLOCK;
		TAILQ_INSERT_HEAD(&lockouts, sshlog, entry);
		UNLOCK;

		return; // Its first attempt can grace for now.
	}

	/* Mark it for GC */
	sshlog->inactive = 1; 

	// Notify syslog of the host being blocked (IPADDR)
	syslog(LOG_WARNING, "Locking out %d.%d.%d.%d after %i invalid attempts\n",
		n1, n2, n3, n4, MAXATTEMPTS);

	pf_tableentry(lockouttable, n1, n2, n3, n4);
}
