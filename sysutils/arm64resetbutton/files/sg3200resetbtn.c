/*
 * Copyright 2020 Rubicon Commnunications, LLC (Netgate)
 */

/*
 * sg3200resetbtn
 *
 * Check if button is pressed and stays pressed for 2 seconds.
 * Flash LED if so and return 99 as status.
 * If button is not pressed return 0
 * If button is released before 2 secons, return 2
 */

#include <sys/param.h>

#include <libgpio.h>
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>

#define	SG3200_BTN_GPIO_UNIT		1
#define	SG3200_BTN_GPIO_PIN		22
#define	SG3200_LED_GPIO_UNIT		2

static int led_pins[3] = { 0, 3, 6 };

static void
flash_led(void)
{
	char strmib[128];
	gpio_handle_t gpio;
	int i, j, maxpins, mib[8], val;
	size_t miblen;

	gpio = gpio_open(SG3200_LED_GPIO_UNIT);
	if (gpio == -1) {
		printf("cannot open /dev/gpioc%d\n", SG3200_LED_GPIO_UNIT);
		exit(-1);
	}
	if (ioctl(gpio, GPIOMAXPIN, &maxpins) < 0) {
		gpio_close(gpio);
		return;
	}
	for (i = 0; i < (maxpins + 1) / 3; i++) {
		memset(strmib, 0, sizeof(strmib));
		snprintf(strmib, sizeof(strmib), "dev.gpio.%d.led.%d.pwm",
		    SG3200_LED_GPIO_UNIT, i);
		miblen = nitems(mib);
		val = 0;
		if (sysctlnametomib(strmib, mib, &miblen) == -1 ||
		    sysctl(mib, miblen, NULL, NULL, &val, sizeof(val)) == -1) {
			gpio_close(gpio);
			return;
		}
	}
	for (i = 0; i <= maxpins; i++) {
		val = 0;
		for (j = 0; j < nitems(led_pins); j++)
			if (i == led_pins[j]) {
				val = 80;
				break;
			}
		if (gpio_pwm_set(gpio, -1, i, GPIO_PWM_DUTY, val) == -1)
			break;
	}
	gpio_close(gpio);
}

int
main(int argc, char *argv[])
{
	int i, pin, unit;
	gpio_handle_t gpio;

	pin = SG3200_BTN_GPIO_PIN;
	unit = SG3200_BTN_GPIO_UNIT;
	gpio = gpio_open(unit);
	if (gpio == -1) {
		printf("cannot open /dev/gpioc%d\n", unit);
		exit(-1);
	}

	if (gpio_pin_get(gpio, pin) != 0) {
		/* Switch is not pressed. */
		gpio_close(gpio);
		exit(0);
	}
	/*
	 * Wait for 2 seconds and make sure that the switch is
	 * pressed all the time.
	 */
	for (i = 0; i < 20; i++) {
		usleep(100000);
		if (gpio_pin_get(gpio, pin) != 0) {
			/* Switch was released too soon. */
			gpio_close(gpio);
			exit(2);
		}
	}

	gpio_close(gpio);

	flash_led();

	exit(99);
}
