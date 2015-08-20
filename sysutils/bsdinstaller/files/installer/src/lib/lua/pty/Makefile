# $Id: Makefile,v 1.11 2005/08/26 22:44:38 cpressey Exp $

BASE?=../../..
include ${BASE}/Config

LUA?=../../../platform/lua

COMPAT?=../compat-5.1
COMPATOBJ=${COMPAT}/compat-5.1.o

CFLAGS+=	${WARNS} -fPIC ${EXTRA_CFLAGS}
CFLAGS+=	-I../ -I${LUA}/include -I/usr/local/include -I/usr/pkg/include
LDFLAGS+=	-L${LUA}/lib -L/usr/local/lib -L/usr/pkg/lib
LDFLAGS+=	-lutil -lm -llua50 -llualib50

LIB=	lpty.so
OBJS=	pty.o

all: ${LIB}

strip: ${LIB}
	strip ${LIB}

${LIB}: ${OBJS} ${COMPATOBJ}
	${CC} ${OBJS} ${COMPATOBJ} -shared ${LDFLAGS} -o ${LIB}

${COMPATOBJ}:
	${CC} ${CFLAGS} -I${COMPAT} -c ${COMPAT}/compat-5.1.c -o ${COMPATOBJ}

pty.o: pty.c
	${CC} ${CFLAGS} -c pty.c -o pty.o

clean:
	rm -rf *.so *.o

distribution: ${LIB}
	mkdir -p ${DESTDIR}/lib/lua/5.0/
	cp ${LIB} ${DESTDIR}/lib/lua/5.0/
