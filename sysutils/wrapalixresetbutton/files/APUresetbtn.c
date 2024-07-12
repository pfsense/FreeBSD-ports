/*
 * APUresetbtn.c
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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
