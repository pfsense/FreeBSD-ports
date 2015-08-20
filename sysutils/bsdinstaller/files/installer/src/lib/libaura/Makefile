# Makefile for libaura.
# $Id: Makefile,v 1.20 2005/08/26 22:44:37 cpressey Exp $

BASE?=../..
include ${BASE}/Config

LIB=	aura
LIB_VER=3

OBJS=	mem.o popen.o fspred.o \
	dict.o buffer.o

CFLAGS+=	${WARNS} -fPIC
CFLAGS+=	${EXTRA_CFLAGS}
LDFLAGS+=

all: ${SONAME} ${ANAME}

${SONAME}: ${OBJS}
	${CC} ${OBJS} -shared ${LDFLAGS} -o ${SONAME}
	ln -sf ${SONAME} ${SOLINK}

${ANAME}: ${OBJS}
	${AR} rc ${ANAME} ${OBJS}
	${RANLIB} ${ANAME}

strip: ${SONAME}
	strip ${SONAME}

clean:
	rm -rf *.o *.so *.so.? *.So *.a

distribution: all
	mkdir -p ${DESTDIR}/lib
	cp ${SONAME} ${DESTDIR}/lib/
	ln -sf ${SONAME} ${DESTDIR}/lib/${SOLINK}
	cp ${ANAME} ${DESTDIR}/lib/
