/*
 * RCC-VE.c
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
