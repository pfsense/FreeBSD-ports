MAINTAINER?=	coreteam@pfsense.org
COMMENT?=	filterdns

PROG=           filterdns
PREFIX?=        /usr/local
BINDIR?=        ${PREFIX}/sbin
DATAMODE=       0444
INSTALL?=       /usr/bin/install

OBJS=           filterdns.o parse.o

WARNS=          -Wsystem-headers -Werror -Wall -Wno-format-y2k -W \
                -Wno-unused-parameter -Wstrict-prototypes \
                -Wmissing-prototypes -Wpointer-arith -Wreturn-type \
                -Wcast-qual -Wwrite-strings -Wswitch -Wshadow \
                -Wunused-parameter -Wchar-subscripts -Winline \
                -Wnested-externs -Wredundant-decls -Wno-pointer-sign
LDD=            -lpthread
CFLAGS+= -O2 -pipe ${WARNS}

all: ${PROG}

${PROG}: ${OBJS}
	cc ${CFLAGS} -o ${PROG} ${OBJS} ${LDD}

install: all
	${INSTALL} ${PROG} ${BINDIR}/

clean:
	rm -f ${PROG} ${PROG}.core *.o y.tab.h
