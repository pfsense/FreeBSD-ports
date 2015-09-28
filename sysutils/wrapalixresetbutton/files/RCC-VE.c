/*
	Copyright (C) 2015 Ermal LUÇI
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
#include <strings.h>
#include <fcntl.h>
#include <sys/types.h>
#include <sys/cdefs.h>
#include <machine/cpufunc.h>
#include <unistd.h>
#include <signal.h>
#include <sys/time.h>
#include <sys/pciio.h>

#define GPIOBASE 0x0500
#define GPIO_SC_USE_SEL (GPIOBASE + 0x00)
#define GPIO_SC_IO_SEL (GPIOBASE + 0x04)
#define GPIO_SC_GP_LVL (GPIOBASE + 0x08)

int switch_gpio = 11;
int led_gpio = 15;

static int gpio_read(int reg, int gpio) {
	int c;

	c = inl(reg);
	if (c & (1 << gpio))
		return 1;
	else
		return 0;
}

static void gpio_write(int reg, int gpio, int on) {
	int c;
        
        c = inl(reg);
        if (on)
                c |= (1 << gpio);
        else
                c &= ~(1 << gpio);
        outl(reg, c);
}

static void led_on() {
        gpio_write(GPIO_SC_GP_LVL, led_gpio, 1);
}
        
static void led_off() {
        gpio_write(GPIO_SC_GP_LVL, led_gpio, 0);
}

static char is_switch_pressed() {
	return (gpio_read(GPIO_SC_GP_LVL, switch_gpio) == 0);
}

int main(int argc, char *argv[]) {
	int iofd, i;
	
	iofd = open("/dev/io", O_RDONLY);
	if (iofd == -1) {
		perror("cannot open /dev/io");
		exit(1);
	}

#ifdef DEBUG
	printf("GPIO_SC_USE_SEL: %x\n", inl(GPIO_SC_USE_SEL));
	printf("GPIO_SC_IO_SEL: %x\n", inl(GPIO_SC_IO_SEL));
	printf("SC_GP_LVL: %x\n", inl(GPIO_SC_GP_LVL));
#endif

	/* Check if the pin is configured for GPIO */
	if (!gpio_read(GPIO_SC_USE_SEL, led_gpio)) {
		gpio_write(GPIO_SC_USE_SEL, led_gpio, 1);
		gpio_write(GPIO_SC_IO_SEL, led_gpio, 0);
	}
	if (!gpio_read(GPIO_SC_USE_SEL, switch_gpio)) {
		/* Enable it for GPIO */
		gpio_write(GPIO_SC_USE_SEL, switch_gpio, 1);
		gpio_write(GPIO_SC_IO_SEL, switch_gpio, 1);
#ifdef DEBUG
		printf("GPIO_SC_USE_SEL: %x\n", inl(GPIO_SC_USE_SEL));
		printf("GPIO_SC_IO_SEL: %x\n", inl(GPIO_SC_IO_SEL));
#endif
	}

	/* check whether the switch S1 is pressed */
	if (!is_switch_pressed()) {
#ifdef DEBUG
		printf("GPIO_SC_USE_SEL: %x\n", inl(GPIO_SC_USE_SEL));
		printf("GPIO_SC_IO_SEL: %x\n", inl(GPIO_SC_IO_SEL));
#endif
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
                led_on();
                usleep(300000);
                led_off();
                usleep(300000);
        }

	led_off();

#ifdef DEBUG
	printf("GPIO_SC_USE_SEL: %x\n", inl(GPIO_SC_USE_SEL));
	printf("GPIO_SC_IO_SEL: %x\n", inl(GPIO_SC_IO_SEL));
#endif
	
	close(iofd);

	/* return special code 99 to indicate factory defaults should be loaded */
	exit(99);
	
	return 0;
}
