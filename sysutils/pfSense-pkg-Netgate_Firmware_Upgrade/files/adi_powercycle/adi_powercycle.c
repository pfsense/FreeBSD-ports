/* ex: set tabstop=4 shiftwidth=4 softtabstop=4: */

#include	<sys/param.h>

#include	<stdio.h>
#include	<stdlib.h>
#ifdef __FreeBSD__
#include	<unistd.h>
#include	<fcntl.h>
#include	<sys/types.h>
#include	<sys/ioctl.h>
#include	<dev/io/iodev.h>
#include	<machine/iodev.h>
#include	<machine/cpufunc.h>
#else
#include	<sys/io.h>
#endif

#ifdef __FreeBSD__
typedef	u_int	u32;
typedef struct iodev_pio_req opts_t;
#else
#define IODEV_PIO_READ 0
#define IODEV_PIO_WRITE 1

typedef	unsigned int	u32;

typedef struct options
{
	u32	port;	// IO port number
	int	width;	// 1 - 8-bits, 2 - 16-bits, 4 - 32-bits
	int	access;	// 1 - write, 0 - read
	u32	val;	// Write data.
} opts_t;
#endif

void
io_write (opts_t *opts)
{
#ifdef __FreeBSD__
	int fd = -1;

	if ((fd = open("/dev/io", O_WRONLY)) == -1)
	{
		perror (NULL);
		fprintf (stderr, "Error opening /dev/io\n");
		exit (1);
	}

	outl (opts->port, opts->val);

	close(fd);
#else
	if (iopl(3))
	{
		perror (NULL);
		fprintf (stderr, "Error iopl\n");
		exit (1);
	}

	if (ioperm (opts.port, 2, 1))
	{
		perror (NULL);
		fprintf (stderr, "Error ioperm\n");
		exit (1);
	}

	outl (opts->val, opts->port);

	ioperm (opts.port, 2, 0);
#endif
}

int
main (int argc, char **argv)
{
	opts_t opts;

	opts.val = 0;
	opts.width = 4;
	opts.access = IODEV_PIO_WRITE;

	/* set sus gpio 2 level to low */
	opts.port = 0x588;
	io_write (&opts);

	/* change sus gpio2 to output */
	opts.port = 0x584;
	io_write (&opts);

	exit (0);
}
