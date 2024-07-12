/*
 * FW7541resetbtn.c
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

/*******************************************************************************

  btn_tst.c: main application for Lanner platform Software Button program

  Lanner Platform Miscellaneous Utility
  Copyright(c) 2010 Lanner Electronics Inc.
  All rights reserved.

  Redistribution and use in source and binary forms, with or without
  modification, are permitted provided that the following conditions
  are met:
  1. Redistributions of source code must retain the above copyright
     notice, this list of conditions and the following disclaimer,
     without modification.
  2. Redistributions in binary form must reproduce at minimum a disclaimer
     similar to the "NO WARRANTY" disclaimer below ("Disclaimer") and any
     redistribution must be conditioned upon including a substantially
     similar Disclaimer requirement for further binary redistribution.
  3. Neither the names of the above-listed copyright holders nor the names
     of any contributors may be used to endorse or promote products derived
     from this software without specific prior written permission.

  Alternatively, this software may be distributed under the terms of the
  GNU General Public License ("GPL") version 2 as published by the Free
  Software Foundation.

  NO WARRANTY
  THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
  ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
  LIMITED TO, THE IMPLIED WARRANTIES OF NONINFRINGEMENT, MERCHANTIBILITY
  AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL
  THE COPYRIGHT HOLDERS OR CONTRIBUTORS BE LIABLE FOR SPECIAL, EXEMPLARY,
  OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
  SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
  INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER
  IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF
  THE POSSIBILITY OF SUCH DAMAGES.

*******************************************************************************/

#include <stdio.h>
#include <stdlib.h>
#include <fcntl.h>
#include <sys/types.h>
#include <sys/cdefs.h>
#include <machine/cpufunc.h>
#include <unistd.h>
#include <signal.h>
#include <sys/time.h>

/*
 * Platform Depend GPIOs for Status LEDs
 */

/*
 *------------------------------------------------------------------------------
 * MB-7541 Version V1.0
 *
 * The IO interface for Status LED is connected to Winbond SIO 83627 GPIO 31 32
 * Refer to Winbond 83627 datasheet for details.
 * The truth table is defined as below:
 *      GPIO31  GPIO32  LED_STATUS
 *      0       0       OFF
 *      0       1       Red
 *      1       0       GREEN
 *      1       1       OFF
 *------------------------------------------------------------------------------
*/

/*
 * Device Depend Definition : Winbond 83627 SIO
*/
#define INDEX_PORT      0x2E
#define DATA_PORT       0x2F


#define GPIO31_BIT              (1 << 1)
#define GPIO32_BIT              (1 << 2)
#define GPIO_GPIO31_GPIO32_MASK (GPIO31_BIT | GPIO32_BIT)
#define LED_OFF                 0
#define LED_RED               GPIO32_BIT
#define LED_GREEN             	GPIO31_BIT

/*
 * Platform Depend GPIOs for Reset Button
 */

/*
 *------------------------------------------------------------------------------
 * MB-7541 Version V 0.1
 *
 * The IO interface for Reset Button is connected to Intel ICH8-M GPIO 20.
 * Refer to Intel ICH8-M datasheet for details.
 * The high/low definition is as below:
 *      GPIO20  Button Status
 *     --------------------------
 *      1       Button Pressed
 *      0       Button Released
 *------------------------------------------------------------------------------
*/

/*
 * Device Depend Definition : Intel ICH8-M chipset
*/
#define SB_GPIO_PORT_BASE       0x500
#define SB_GPIO_PORT_USE_SEL    0x2
#define SB_GPIO_PORT_IO_SEL     0x6
#define SB_GPIO_PORT_OFFSET     0xE
#define SB_GPIO_PORT_REG        SB_GPIO_PORT_BASE+SB_GPIO_PORT_OFFSET

#define GPIO20_BIT              (1 << 4)

#define STATUS_BITMASK  GPIO20_BIT
#define STATUS_PRESSED  0
#define STATUS_RELEASED GPIO20_BIT

/*
 * Return value:
 * 	0: BUTTON Pressed
 * 	1: BUTTON Released
 */ 	
static unsigned char
get_btn_status(void)
{
        return  ( inb(SB_GPIO_PORT_REG) & STATUS_BITMASK);
}

static void
enter_w83627_config(void)
{
        outb(INDEX_PORT, 0x87); // Must Do It Twice
        outb(INDEX_PORT, 0x87);
        return;
}

static void
exit_w83627_config(void)
{
        outb(INDEX_PORT, 0xAA);
        return;
}

static unsigned char
read_w83627_reg(int LDN, int reg)
{
        unsigned char tmp = 0;

        enter_w83627_config();
        outb(INDEX_PORT, 0x07); // LDN Register
        outb(DATA_PORT, LDN); // Select LDNx
        outb(INDEX_PORT, reg); // Select Register
        tmp = inb( DATA_PORT); // Read Register
        exit_w83627_config();
        return tmp;
}


static void
write_w83627_reg(int LDN, int reg, int value)
{
        enter_w83627_config();
        outb(INDEX_PORT, 0x07); // LDN Register
        outb(DATA_PORT, LDN); // Select LDNx
        outb(INDEX_PORT, reg); // Select Register
        outb(DATA_PORT, value); // Write Register
        exit_w83627_config();
        return;
}

static void
led_on()
{
	unsigned char tmp;

        tmp=read_w83627_reg(0x09, 0xF1);
        tmp &= ~(GPIO_GPIO31_GPIO32_MASK);
        tmp |= LED_GREEN;
        write_w83627_reg(0x09, 0xF1, tmp);

        return;
}

static void
led_off()
{
	unsigned char tmp;

        tmp = read_w83627_reg(0x09, 0xF1);
        tmp &= ~(GPIO_GPIO31_GPIO32_MASK);
        tmp |= LED_OFF;
        write_w83627_reg(0x09, 0xF1, tmp);

        return;
}

static void
sled_gpio_init(void)
{
        unsigned char tmp;

        /* set GP31/32 as Output function */
   	tmp = read_w83627_reg(0x09, 0x30);
   	tmp |= 0x02;
   	write_w83627_reg(0x09, 0x30, tmp);
        /* set GP31/32 as Output function */
        tmp=read_w83627_reg(0x09, 0xF0);
        tmp &= ~(GPIO_GPIO31_GPIO32_MASK);
        write_w83627_reg(0x09, 0xF0, tmp);

        return;
}

static void
btn_gpio_init(void)
{
        unsigned char tmp;
        /* platform initial code */

        /* Reset button initial function */

        /* select GPIO function */
        tmp= inb(SB_GPIO_PORT_BASE+SB_GPIO_PORT_USE_SEL);
        tmp |= GPIO20_BIT;
        outb(SB_GPIO_PORT_BASE+SB_GPIO_PORT_USE_SEL, tmp);

        /* set to input mode */
        tmp= inb(SB_GPIO_PORT_BASE+SB_GPIO_PORT_IO_SEL);
        tmp |= GPIO20_BIT;
        outb(SB_GPIO_PORT_BASE+SB_GPIO_PORT_IO_SEL, tmp);
	
	return;
}

int
main(int argc, char *argv[])
{
	int iofd, i;

	iofd = open("/dev/io", O_RDONLY);
	if (iofd == -1) {
		perror("cannot open /dev/io");
		exit(1);
	}

	btn_gpio_init();

	/* check whether the switch S1 is pressed */
	if (get_btn_status() != STATUS_PRESSED) {
		/* nothing to do */
		exit(0);
	}

	/* wait for 2 seconds and make sure that the switch is
	   pressed all the time */
	for (i = 0; i < 20; i++) {
		usleep(100000);
		if (get_btn_status() != STATUS_PRESSED) {
			/* switch was released too soon */
			exit(2);
		}
	}

	sled_gpio_init();

	/* blink all three LEDs five times to indicate reset */
	for (i = 0; i < 5; i++) {
		led_on();
		usleep(300000);
		led_off();
		usleep(300000);
	}

	/* restore normal LED state */
	led_off();

	close(iofd);

	/* return special code 99 to indicate factory defaults should be loaded */
	exit(99);

	/* NOTREACHED */
	return 0;
}
