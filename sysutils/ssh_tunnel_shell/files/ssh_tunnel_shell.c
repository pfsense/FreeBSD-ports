/*-
 * Copyright (c) 2004 The FreeBSD Project.
 * Copyright (c) 2010 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED.  IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
 * OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
 * SUCH DAMAGE.
 */

#include <sys/types.h>
#include <sys/uio.h>
#include <sys/time.h>
#include <sys/stat.h>

#include <stdio.h>
#include <syslog.h>
#include <unistd.h>
#include <strings.h>
#include <fcntl.h>

#define MESSAGE	"This login only supports SSH tunneling.\n"
#define PATH	"/var/etc/ssh_tunnel_message"
#define MAXLINE	2048

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

int
main(__unused int argc, __unused char *argv[])
{
	struct timeval tv, tv1;
	const char *user, *tt;
	char buf[MAXLINE];
	char c;
	int fd, msent, nbytes;

	if ((tt = ttyname(0)) == NULL)
		tt = "UNKNOWN";
	if ((user = getlogin()) == NULL)
		user = "UNKNOWN";
	openlog("sshtunnelsh", LOG_CONS, LOG_AUTH);
	syslog(LOG_CRIT, "Login by %s on %s", user, tt);
	closelog();

	msent = 0;
	if (fexist(PATH)) {
		fd = open(PATH, O_RDONLY);
		if (fd > 0) {
			do {
				bzero(buf, MAXLINE);
				nbytes = read(fd, buf, MAXLINE - 1);
				if (nbytes < 0)
					break;
				else if (nbytes > 0) {
					buf[nbytes] = '\0';
					printf("%s", buf);
					msent++;
				}
			} while (nbytes > 0);
			close(fd);
		}
	}
	if (msent == 0)
		printf("%s", MESSAGE);
	while (gettimeofday(&tv, NULL) < 0)
		;

	for (;;) {
		c = getchar();

		while (gettimeofday(&tv1, NULL) < 0)
			;
		printf("You are logged in for %d hours %d minute(s)\n",
			(tv1.tv_sec - tv.tv_sec)/3600, (tv1.tv_sec - tv.tv_sec)/60);
	}
	return 1;
}
