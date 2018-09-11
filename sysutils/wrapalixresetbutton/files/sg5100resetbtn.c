/*
 * Copyright 2018 Rubicon Commnunications LLC
 */

/*
 * sg5100resetbtn
 *
 * check if button is pressed and stays pressed for 2 seconds.
 * flash LED if so and return 99 as status.
 * if button is not pressed return 0
 * if button is released before 2 secons, return 2
 */

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>

#include <fcntl.h>
#include <dev/sg5100/sg5100_ioctl.h>
#include <sys/ioctl.h>

#define SG5100_DEVICE "/dev/sg5100"

int
main(int argc, char *argv[])
{
	int devfd;
	int value;
	int ret;
	int i;

	devfd = open(SG5100_DEVICE, O_RDONLY);
	if (devfd == -1) {
		printf("Can't open " SG5100_DEVICE "\n");
		return -1;
	}
	ret = ioctl(devfd, IOCTL_GET_BUTTON_STATUS, &value);
	if (value != 0) {
		/* switch is not pressed */
		exit(0);
	}
	/*
	 * wait for 2 seconds and make sure that the switch is
	 * pressed all the time
	 */
	for (i = 0; i < 20; i++) {
		usleep(100000);
		ret = ioctl(devfd, IOCTL_GET_BUTTON_STATUS, &value);
		if (value != 0) {
			/* switch was released too soon */
			exit(2);
		}
	}

	/* signal reset on LED for 1 second */
	value = LED_SET_STATUS_RED_GREEN_FLASHING;
	ret = ioctl(devfd, IOCTL_LED_SET_STATUS, &value);
	usleep(1000000);
	value = LED_SET_STATUS_OFF;
	ret = ioctl(devfd, IOCTL_LED_SET_STATUS, &value);

	close(devfd);
	exit(99);

	return 0;
}
