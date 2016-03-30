MAINTAINER?=	coreteam@pfsense.org
COMMENT?=	filterlog

PROG=           filterlog
PREFIX?=        /usr/local
BINDIR?=        ${PREFIX}/sbin
DATAMODE=       0444
INSTALL?=       /usr/bin/install

OBJS=           print-ip.o print-ip6.o print-ip6opts.o print-icmp.o print-tcp.o print-mobility.c filterlog.o

WARNS=          -Wsystem-headers -Werror -Wall -Wno-format-y2k -W \
                -Wno-unused-parameter -Wstrict-prototypes \
                -Wmissing-prototypes -Wpointer-arith -Wreturn-type \
                -Wcast-qual -Wwrite-strings -Wswitch -Wshadow \
                -Wunused-parameter -Wchar-subscripts -Winline \
                -Wnested-externs -Wredundant-decls -Wno-pointer-sign
LDD=            -lsbuf -lpcap
CFLAGS+= -O2 -pipe ${WARNS}

all: ${PROG}

${PROG}: ${OBJS}
	cc ${CFLAGS} -o ${PROG} ${OBJS} ${LDD}

install: all
	${INSTALL} ${PROG} ${BINDIR}/

clean:
	rm -f ${PROG} ${PROG}.core *.o
