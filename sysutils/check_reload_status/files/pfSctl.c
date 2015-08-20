/*
        Copyright (C) 2010 Ermal Luçi
        All rights reserved.

        Redistribution and use in source and binary forms, with or without
        modification, are permitted provided that the following conditions are met:

        1. Redistributions of source code must retain the above copyright notice,
           this list of conditions and the following disclaimer.

        2. Redistributions in binary form must reproduce the above copyright
           notice, this list of conditions and the following disclaimer in the
           documentation and/or other materials provided with the distribution.

        THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
        INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
        AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
        AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
        OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
        SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
        INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
        CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
        ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
        POSSIBILITY OF SUCH DAMAGE.

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
