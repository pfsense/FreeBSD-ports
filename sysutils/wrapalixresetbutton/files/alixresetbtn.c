/*
	$Id$
	part of m0n0wall (http://m0n0.ch/wall)
	
	Copyright (C) 2007 Manuel Kasper <mk@neon1.net>.
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
#include <stdio.h>
#include <stdlib.h>
#include <fcntl.h>
#include <sys/types.h>
#include <sys/cdefs.h>
#include <machine/cpufunc.h>
#include <unistd.h>
#include <signal.h>
#include <sys/time.h>

/* GPIO ports for LEDs */
u_int32_t led_ports[3] = {0x6100, 0x6180, 0x6180};
int led_bits[3] = {6, 9, 11};
u_int32_t switch_port = 0x61b0;
int switch_bit = 8;

void led_on(int i) {
	outl(led_ports[i], 1 << (led_bits[i] + 16));
}

void led_off(int i) {
	outl(led_ports[i], 1 << led_bits[i]);
}

char is_switch_pressed() {
	return ((inl(switch_port) & (1 << switch_bit)) == 0);
}

int main(int argc, char *argv[]) {
	
	int iofd, i;
	
	iofd = open("/dev/io", O_RDONLY);
	if (iofd == -1) {
		perror("cannot open /dev/io");
		exit(1);
	}
	
	/* check whether the switch S1 is pressed */
	if (!is_switch_pressed()) {
		/* nothing to do */
		exit(0);
	}
	
	/* wait for 2 seconds and make sure that the switch is
	   pressed all the time */
	for (i = 0; i < 20; i++) {
		usleep(100000);
		if (!is_switch_pressed()) {
			/* switch was released too soon */
			exit(2);
		}
	}
	
	/* blink all three LEDs five times to indicate reset */
	for (i = 0; i < 5; i++) {
		led_on(0); led_on(1); led_on(2);
		usleep(300000);
		led_off(0); led_off(1); led_off(2);
		usleep(300000);
	}
	
	/* restore normal LED state */
	led_on(0);
	
	close(iofd);

	/* return special code 99 to indicate factory defaults should be loaded */
	exit(99);
	
	return 0;
}
