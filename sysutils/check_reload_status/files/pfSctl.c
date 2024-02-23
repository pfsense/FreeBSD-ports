/*
 * pfSctl.c
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

#include <sys/types.h>
#include <sys/socket.h>
#include <sys/un.h>

#include <syslog.h>
#include <signal.h>
#include <err.h>
#include <errno.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <strings.h>
#include <unistd.h>
#include <time.h>

#include "common.h"

static int op = 0; 

static void
handle_signal(int sig)
{
	switch(sig) {
        case SIGALRM:
		syslog(LOG_ERR, "could not finish %s in a reasonable time. Action of event might not be completed.", op == 1 ? "write" : op == 2 ? "read" : op == 0 ? "connect" : "action" );
                break;
        }
	exit(0);
}

static void
handle_signal_act(int sig, siginfo_t *unused1 __unused, void *unused2 __unused)
{
	handle_signal(sig);
}

int
main(int argc, char **argv)
{
	struct sockaddr_un sun;
	struct sigaction sa;
	char buf[2048];
	char *cmd[6], *path;
	socklen_t len;
	int fd, n, ch;
	int ncmds = 0, nsock = 0, error = 0, i;

	tzset();

	path = NULL;
	while ((ch = getopt(argc, argv, "c:s:")) != -1) {
		switch (ch) {
		case 'c':
			if (ncmds > 5)
				err(-3, "Wrong parameters passed for command.");
			cmd[ncmds] = strdup(optarg);
			ncmds++;
			break;
		case 's':
			if (nsock > 0)
				err(-3, "Wrong parameters passed for socket.");
			path = optarg;
			nsock++;
			break;
		default:
			err(-1, "cmdclient 'command string'");
			break;
		}
	}
	argc -= optind;
	argv += optind;

	/*
         * Catch SIGHUP in order to reread configuration file.
         */
        sa.sa_handler = handle_signal;
	sa.sa_sigaction = handle_signal_act;
        sa.sa_flags = SA_SIGINFO|SA_RESTART;
        sigemptyset(&sa.sa_mask);
        error = sigaction(SIGALRM, &sa, NULL);
        if (error == -1)
                err(-1, "unable to set signal handler");

	fd = socket(PF_UNIX, SOCK_STREAM, 0);
	if (fd < 0)
		err(-2, "could not create socket.");
	
	bzero(&sun, sizeof(sun));
	sun.sun_family = AF_LOCAL;
	strlcpy(sun.sun_path, path == NULL ? PATH : path, sizeof(sun.sun_path));
	len = sizeof(sun);

	op = 0; /* Read */
	alarm(3); /* Wait 3 seconds to complete a connect. More than enough?! */
	if (connect(fd, (struct sockaddr *)&sun, len) < 0)
		errx(errno, "Could not connect to server.");

	for (i = 0; i < ncmds; i++) {
		alarm(0); /* Just to be safe */
		op = 1; /* Write */
		alarm(3); /* Wait 3 seconds to complete a write. More than enough?! */
		if (write(fd, cmd[i], strlen(cmd[i])) < 0)
			errx(errno, "Could not send command to server.");

		alarm(0); /* Just to be safe */
		op = 2; /* Read */
		bzero(buf, sizeof(buf));
		alarm(3); /* Wait 3 seconds to complete a read. More than enough?! */
		n = read(fd, buf, sizeof(buf));
		if (n < 0)
			warnc(errno, "Reading from socket");
		else if (n > 0)
			printf("%s", buf);
		
	}
	close(fd);

	return (0);
}
