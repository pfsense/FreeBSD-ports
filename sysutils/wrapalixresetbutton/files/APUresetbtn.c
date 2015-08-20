/*
	Copyright (C) 2014 Ermal Luci
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

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <fcntl.h>
#include <unistd.h>

int
main(int argc, char *argv[])
{
	int iofd, i;
	u_char ch = 0;

	iofd = open("/dev/gpioapu", O_RDWR);
	if (iofd == -1) {
		perror("cannot open /dev/gpioapu");
		exit(1);
	}

	/* check whether the switch S1 is pressed */
	read(iofd, &ch, sizeof(ch));
 	if (ch == '1') {
		/* nothing to do */
		exit(0);
	}

	/* wait for 2 seconds and make sure that the switch is
	   pressed all the time */
	for (i = 0; i < 20; i++) {
		usleep(100000);
		read(iofd, &ch, sizeof(ch));
		if (ch == '1') {
			/* switch was released too soon */
			exit(2);
		}
	}

	/* blink all three LEDs five times to indicate reset */
	for (i = 0; i < 5; i++) {
		write(iofd, "111", strlen("111"));
		usleep(300000);
		write(iofd, "000", strlen("000"));
		usleep(300000);
	}

	/* restore normal LED state */
	write(iofd, "100", strlen("100"));

	close(iofd);

	/* return special code 99 to indicate factory defaults should be loaded */
	exit(99);

	/* NOTREACHED */
	return 0;
}
