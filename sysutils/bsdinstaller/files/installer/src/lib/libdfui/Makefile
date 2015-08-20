# Makefile for libdfui.
# $Id: Makefile,v 1.28 2005/08/26 22:44:37 cpressey Exp $

BASE?=../..
include ${BASE}/Config

LIB=	dfui
LIB_VER=4

OBJS=	encode.o decode.o conn_tcp.o \
	connection.o conn_caps.o conn_npipe.o \
	form.o \
	dump.o system.o lang.o

CFLAGS+=	${EXTRA_CFLAGS} ${WARNS} -fPIC -I../libaura
CFLAGS+=	-I/usr/src/lib -I/usr/src/lib/libcaps/${MACHINE_ARCH} -DSMP
CFLAGS+=	-I/usr/local/include -I/usr/pkg/include
LDFLAGS+=	-L../libaura
LDFLAGS+=	-L/usr/local/lib -L/usr/pkg/lib
LDFLAGS+=	-laura

# Default target:

all: ${SONAME} ${ANAME}

# Main targets, shared and static:

${SONAME}: ${OBJS}
	${CC} ${OBJS} -shared ${LDFLAGS} -o ${SONAME}
	${LN} -sf ${SONAME} ${SOLINK}

${ANAME}: ${OBJS}
	${AR} rc ${ANAME} ${OBJS}
	${RANLIB} ${ANAME}

strip: ${SONAME}
	${STRIP ${SONAME}

clean:
	rm -rf *.o *.so *.so.* *.So *.a

distribution: all
	mkdir -p ${DESTDIR}/lib
	cp ${SONAME} ${DESTDIR}/lib/
	ln -sf ${SONAME} ${DESTDIR}/lib/${SOLINK}
	cp ${ANAME} ${DESTDIR}/lib/
