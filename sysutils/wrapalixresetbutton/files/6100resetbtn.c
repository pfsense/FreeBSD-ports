/*
 * Copyright 2020 Rubicon Commnunications, LLC (Netgate)
 */

/*
 * 6100resetbtn
 *
 * Check if button is pressed and stays pressed for 2 seconds.
 * Flash LED if so and return 99 as status.
 * If button is not pressed return 0
 * If button is released before 2 secons, return 2
 */

#include <sys/param.h>

#include <fcntl.h>
#include <libgpio.h>
#include <stdio.h>
#include <stdlib.h>
#include <strings.h>
#include <unistd.h>

#define	NG6100_BTN_GPIO_UNIT		0
#define	NG6100_LONG_BTN_PIN		12
#define	NG6100_SHORT_BTN_PIN		13
#define	NG6100_FIRST_LED_PIN		0
#define	NG6100_LAST_LED_PIN		11


static void
flash_led(gpio_handle_t gpio)
{
	char *leds[] = { "red1", "red2", "red3" };
	char filepath[64];
	int fd, i, pin;

	for (pin = NG6100_FIRST_LED_PIN; pin <= NG6100_LAST_LED_PIN; pin++)
		gpio_pin_set(gpio, pin, 1);

	for (i = 0; i < nitems(leds); i++) {
		snprintf(filepath, sizeof(filepath), "/dev/led/%s", leds[i]);
		fd = open(filepath, O_RDWR);
		if (fd == -1)
			continue;
		write(fd, "f1\n", 3);
		close(fd);
	}
}

int
main(int argc, char *argv[])
{
	bool confirm;
	int i;
	gpio_handle_t gpio;

	confirm = false;
	if (argc > 1 && strcasecmp(argv[1], "confirm") == 0)
		confirm = true;

	gpio = gpio_open(NG6100_BTN_GPIO_UNIT);
	if (gpio == -1) {
		printf("cannot open /dev/gpioc%d\n", NG6100_BTN_GPIO_UNIT);
		exit(-1);
	}

	if (!confirm) {
		if (gpio_pin_get(gpio, NG6100_SHORT_BTN_PIN) == 0) {
			/* Switch is not pressed. */
			gpio_close(gpio);
			exit(0);
		}
		/* Reset GPIO pin state. */
		gpio_pin_set(gpio, NG6100_SHORT_BTN_PIN, 1);
		gpio_pin_set(gpio, NG6100_LONG_BTN_PIN, 1);

		/* Short press detected, ask for confirmation. */
		exit (55);
	}
	/*
	 * Wait for 20 seconds for the long press.
	 * If another short press is detected, abort.
	 */
	for (i = 0; i < 20; i++) {
		sleep(1);
		if (gpio_pin_get(gpio, NG6100_SHORT_BTN_PIN) == 1) {
			/* Button released too early - cancel. */
			gpio_pin_set(gpio, NG6100_SHORT_BTN_PIN, 1);
			break;
		}
		if (gpio_pin_get(gpio, NG6100_LONG_BTN_PIN) == 1) {
			/* Long press detected. */
			gpio_pin_set(gpio, NG6100_LONG_BTN_PIN, 1);
			flash_led(gpio);
			gpio_close(gpio);
			exit(99);
		}
	}

	gpio_close(gpio);

	exit(2);
}
