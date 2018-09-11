/* -*- c-basic-offset: 4 -*- */

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif
#include "php.h"
#include "php_sg5100.h"

#include <fcntl.h>
#include <sys/ioctl.h>

#include <dev/sg5100/sg5100_ioctl.h>

#define LED_OFF 0
#define LED_RED 1
#define LED_RED_FLASHING 2
#define LED_GREEN 3
#define LED_GREEN_FLASHING 4
#define LED_RED_GREEN_ALTERNATING 5

static int set_mode (long mode);
static int get_switch_state (void);

static zend_function_entry sg5100_functions[] = {
    PHP_FE(sg5100_led, NULL)
    PHP_FE(sg5100_switch, NULL)
    {NULL, NULL, NULL}
};

zend_module_entry sg5100_module_entry = {
#if ZEND_MODULE_API_NO >= 20010901
    STANDARD_MODULE_HEADER,
#endif
    PHP_SG5100_WORLD_EXTNAME,
    sg5100_functions,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
#if ZEND_MODULE_API_NO >= 20010901
    PHP_SG5100_WORLD_VERSION,
#endif
    STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_SG5100
ZEND_GET_MODULE(sg5100)
#endif

/* status LED control */
    
PHP_FUNCTION(sg5100_led)
{
    long mode;
    int ret;

    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "l", &mode) != SUCCESS) {
	return;
    }

    if ((mode < LED_OFF) || (mode > LED_RED_GREEN_ALTERNATING)) {
	RETURN_FALSE;
    }

    ret = set_mode (mode);

    RETURN_TRUE;
}

static int set_mode (long mode)
{
    int devfd;
    int value;
    int ret;

    devfd = open ("/dev/sg5100", O_RDONLY);
    if (devfd == -1) {
        return 0;
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
        return 0;
    }

    ret = ioctl (devfd, IOCTL_LED_SET_STATUS, &value);

    close (devfd);
    
    return 1;
}

PHP_FUNCTION(sg5100_switch)
{
    int ret;

    ret = get_switch_state();
    if (ret != 0) {
	RETURN_STRING("on");
    }

    RETURN_STRING("off");
    //RETURN_FALSE;
}

static int get_switch_state (void)
{
    int devfd;
    int value;
    int ret;

    devfd = open ("/dev/sg5100", O_RDONLY);
    if (devfd == -1) {
        return 0;
    }

    ret = ioctl (devfd, IOCTL_GET_BUTTON_STATUS, &value);

    close (devfd);

    if (value == 0x00) {
	return 1;
    }
    
    return 0;
}

