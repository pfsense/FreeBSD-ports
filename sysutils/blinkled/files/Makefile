MAINTAINER?=	jimp@pfsense.org
COMMENT?=	blinkled

PROG=		blinkled
PREFIX?=	/usr/local
BINDIR?=	${PREFIX}/sbin
DATAMODE=	0444
INSTALL?=	/usr/bin/install

OBJS=		blinkled.o
LDD=		-lkvm

all: ${PROG}

${PROG}: ${OBJS}
	 cc ${CFLAGS} -o ${PROG} ${OBJS} ${LDD}

install: all
	 ${INSTALL} ${PROG} ${BINDIR}/

clean:
	 rm -f ${PROG} ${PROG}.core *.o y.tab.h
