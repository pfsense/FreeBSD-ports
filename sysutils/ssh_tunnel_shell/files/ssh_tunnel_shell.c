/*-
 * Copyright (c) 2004 The FreeBSD Project.
 * Copyright (c) 2010-2024 Rubicon Communications, LLC (Netgate)
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

#include <fcntl.h>
#include <termios.h>
#include <stdio.h>
#include <string.h>
#include <syslog.h>
#include <unistd.h>

#define	STDINFD		0

int
main(__unused int argc, __unused char *argv[])
{
	const char *user, *tt;
	struct termios t;

	if (isatty(STDINFD) == 0)
		return (1);
	if ((tt = ttyname(STDINFD)) == NULL)
		tt = "UNKNOWN";
	if ((user = getlogin()) == NULL)
		user = "UNKNOWN";
	openlog("sshtunnelsh", LOG_CONS, LOG_AUTH);
	syslog(LOG_CRIT, "Login by %s on %s", user, tt);
	closelog();

	/* Disable the terminal ECHO. */
	memset(&t, 0, sizeof(t));
	if (tcgetattr(STDINFD, &t) == -1)
		return (1);
	t.c_lflag &= ~(ECHOKE | ECHOE | ECHOK | ECHO | ECHONL |
	    ECHOPRT | ECHOCTL | ICANON);
	if (tcsetattr(STDINFD, TCSANOW, &t) == -1)
		return (1);

	/* Check for the peer errors. */
	for (;;)
		if (getchar() == -1)
			break;

	return (1);
}
