/*
	minicron.c
	part of m0n0wall (http://m0n0.ch/wall)
	
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
#include <sys/wait.h>

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <string.h>
#include <syslog.h>
#include <signal.h>


static pid_t pid = 0;
static int quit = 0;

/* usage: minicron interval pidfile cmd */
static void
killchild(int signal) {
	quit = 1;
	if (pid) {
		kill(pid, SIGTERM);
		//syslog(LOG_ERR, "Killing   child %d", signal);
		pid = 0;
	}
}

int main(int argc, char *argv[]) {
	
	int interval, status, sig;
	size_t len;
	FILE *pidfd;
	char *command, *signame;
	
	if (argc < 4) {
		syslog(LOG_ERR, "Wrong number of arguments passed");
		exit(1);
	}
	
	interval = atoi(argv[1]);
	if (interval == 0)
		exit(1);
	
	/* unset loads of CGI environment variables */
	unsetenv("CONTENT_TYPE"); unsetenv("GATEWAY_INTERFACE");
	unsetenv("REMOTE_USER"); unsetenv("REMOTE_ADDR");
	unsetenv("AUTH_TYPE"); unsetenv("SCRIPT_FILENAME");
	unsetenv("CONTENT_LENGTH"); unsetenv("HTTP_USER_AGENT");
	unsetenv("HTTP_HOST"); unsetenv("SERVER_SOFTWARE");
	unsetenv("HTTP_REFERER"); unsetenv("SERVER_PROTOCOL");
	unsetenv("REQUEST_METHOD"); unsetenv("SERVER_PORT");
	unsetenv("SCRIPT_NAME"); unsetenv("SERVER_NAME");

	closefrom(3);

	/* go into background */
	if (daemon(0, 0) == -1) {
		syslog(LOG_ERR, "Failed to daemonize");
		exit(1);
	}

	/* write PID to file */
	pidfd = fopen(argv[2], "w");
	if (pidfd) {
		fprintf(pidfd, "%d\n", getpid());
		fclose(pidfd);
	} else
		syslog(LOG_ERR, "Failed to write pidfile");

	while (!quit) {
		signal(SIGTERM, SIG_DFL);
		pid = fork();

		switch (pid) {
		case 0:
			len = strlen(argv[3]);
			if (argc > 4) {
				len += strlen(argv[4]) + 2;
				command = calloc(1, len * sizeof(char));
				snprintf(command, len, "%s %s", argv[3], argv[4]);
			} else {
				command = calloc(1, (len + 2) * sizeof(char));
				snprintf(command, len + 2, "%s %s", argv[3], argv[4]);
			}

			setproctitle("helper %s", command);

			while (1) {
				sleep(interval);
				
				system(command);
			}
			free(command);

			return (0);
			/* NOTREACHED */
			break;
		case -1:
			syslog(LOG_ERR, "Something went wrong during fork call");
			break;
		default:
			break;
		}

		signal(SIGTERM, killchild);

		if (wait(&status) > 0) {
			if (WIFEXITED(status))
				syslog(LOG_ERR, "(%s) terminated with exit code (%d)", argv[3], WEXITSTATUS(status));
			else if (WIFSIGNALED(status)) {
				sig = WTERMSIG(status);
				signame = strsignal(sig) ? strsignal(sig) : "unknown";
				syslog(LOG_ERR, "(%s) terminated by signal %d (%s)", argv[3], sig, signame);
			}
			//syslog(LOG_INFO, "Restarting command %s(%d)", argv[3], quit);
		}
	}

	if (pid) {
		kill(pid, SIGTERM);
		//syslog(LOG_ERR, "1Killing   child");
		pid = 0;
	}
}
