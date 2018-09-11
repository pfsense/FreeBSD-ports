/* -*- c-basic-offset: 4 -*- */

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <fcntl.h>
#include <sys/ioctl.h>

#include <dev/sg5100/sg5100_ioctl.h>

#define SG5100_DEVICE "/dev/sg5100"

enum led_state {
	LED_OFF,
	LED_RED,
	LED_RED_FLASHING,
	LED_GREEN,
	LED_GREEN_FLASHING,
	LED_RED_GREEN_ALTERNATING
};

void 
usage(void)
{
	fprintf(stderr, "usage: ledtool ledstate\n");
	fprintf(stderr,
	    " 0 = off\n"
	    " 1 = red on\n"
	    " 2 = red flashing\n"
	    " 3 = green\n"
	    " 4 = green flashing\n"
	    " 5 = red/green alternating\n");
	exit(1);
}

int 
main(int argc, char *argv[])
{
	int devfd;
	int value, ret;
	int mode;

	if (argc != 2) {
		usage();
	}
	mode = (int)strtol(argv[1], NULL, 0);
	if ((mode < LED_OFF) || (mode > LED_RED_GREEN_ALTERNATING)) {
		usage();
	}
	devfd = open(SG5100_DEVICE, O_RDONLY);
	if (devfd == -1) {
		printf("Can't open " SG5100_DEVICE "\n");
		return -1;
	}

	switch (mode) {
	case LED_OFF:
		value = LED_SET_STATUS_OFF;
		break;

	case LED_RED:
		value = LED_SET_STATUS_RED;
		break;

	case LED_RED_FLASHING:
		value = LED_SET_STATUS_RED_FLASHING;
		break;

	case LED_GREEN:
		value = LED_SET_STATUS_GREEN;
		break;

	case LED_GREEN_FLASHING:
		value = LED_SET_STATUS_GREEN_FLASHING;
		break;

	case LED_RED_GREEN_ALTERNATING:
		value = LED_SET_STATUS_RED_GREEN_FLASHING;
		break;

	default:
		printf("bad mode?\n");
		return 1;
	}

	ret = ioctl(devfd, IOCTL_LED_SET_STATUS, &value);

	close(devfd);

	return 0;
}
