# $Id: Makefile 579 2008-08-02 12:39:14Z mtm $

PROG=		ipfw-classifyd
PREFIX?=	/usr/local
BINDIR?=	${PREFIX}/sbin
CONFDIR?=	${PREFIX}/etc/ipfw-classifyd
PROTOCOLDIR?=	${CONFDIR}/protocols
RCDIR?=		${PREFIX}/etc/rc.d
DATAMODE=	0444
INSTALL?=	/usr/bin/install

OBJS=	classifyd.o hashtable.o protocols.o

WARNS=		-Wsystem-headers -Werror -Wall -Wno-format-y2k -W \
		-Wno-unused-parameter -Wstrict-prototypes \
		-Wmissing-prototypes -Wpointer-arith -Wreturn-type \
		-Wcast-qual -Wwrite-strings -Wswitch -Wshadow \
		-Wunused-parameter -Wchar-subscripts -Winline \
		-Wnested-externs -Wredundant-decls -Wno-pointer-sign	
LDD=		-lpthread -lm -lutil
CFLAGS+=	${WARNS}

all: ${PROG}

${PROG}: ${OBJS}
	cc ${CFLAGS} -o ${PROG} ${OBJS} ${LDD}

install: all
	${INSTALL} -d ${DESTDIR}${CONFDIR}
	${INSTALL} -d ${DESTDIR}${PROTOCOLDIR}
	${INSTALL} ${PROG} ${DESTDIR}${BINDIR}

clean:
	rm -f ${PROG} *.o
