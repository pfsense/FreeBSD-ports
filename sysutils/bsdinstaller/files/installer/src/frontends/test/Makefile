# Makefile for dfuife_test
# $Id: Makefile,v 1.14 2005/08/26 22:44:37 cpressey Exp $

BASE?=../..
include ${BASE}/Config

PACKAGE=dfuife_test

PROG=	dfuife_test
OBJS=	fe_test.o

CFLAGS+=	${EXTRA_CFLAGS} ${WARNS} -I../../lib/libdfui
LDFLAGS+=	-L../../lib/libdfui -L../../lib/libaura -ldfui -laura

all: ${PROG}

strip: ${PROG}
	strip ${PROG}

${PROG}: ${OBJS}
	${CC} ${OBJS} ${LDFLAGS} -o ${PROG}

clean:
	rm -rf ${PROG} *.o

distribution: strip
	mkdir -p ${DESTDIR}/bin
	cp ${PROG} ${DESTDIR}/bin
