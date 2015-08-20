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

#define GCBBASE 0x9000
#define PMRADDR (GCBBASE + 0x30)
#define GPIOBASE 0xF400
#define GPIO_SCSR (GPIOBASE + 0x20)
#define GPIO_SCAR (GPIOBASE + 0x24)

/* GPIOs for LEDs */
int led_gpio[3] = {2, 3, 18};
int switch_gpio = 40;

int gpio_read(int gpio) {

	u_int32_t offset = 0x04;
	u_int32_t c;

	if (gpio >= 32) {
		offset = 0x14;
		gpio -= 32;
	}
	
	c = inl(GPIOBASE + offset);
	if (c & (1 << gpio))
		return 1;
	else
		return 0;
}

void gpio_write(int gpio, int on) {

	u_int32_t offset = 0x00;
	u_int32_t c;

	if (gpio >= 32) {
		offset = 0x10;
		gpio -= 32;
	}
	
	c = inl(GPIOBASE + offset);
	if (on)
		c |= (1 << gpio);
	else
		c &= ~(1 << gpio);
	outl(GPIOBASE + offset, c);
}


void led_on(int i) {
	gpio_write(led_gpio[i], 0);
}

void led_off(int i) {
	gpio_write(led_gpio[i], 1);
}

char is_switch_pressed() {
	return (gpio_read(switch_gpio) == 0);
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
