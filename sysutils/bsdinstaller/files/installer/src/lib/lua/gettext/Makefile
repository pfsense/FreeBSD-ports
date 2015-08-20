# Makefile for luagettext.
# $Id: Makefile,v 1.14 2005/08/29 19:19:13 cpressey Exp $

BASE?=../../..
include ${BASE}/Config

LUA?=../../../platform/lua

COMPAT?=../compat-5.1
COMPATOBJ=$(COMPAT)/compat-5.1.o

LIB=	lgettext.so
OBJS=	lua_gettext.o

CFLAGS+=	${WARNS} -fPIC
CFLAGS+=	-I${LUA}/include -I${COMPAT}
CFLAGS+=	${NLS_CFLAGS}
CFLAGS+=	${EXTRA_CFLAGS}

LDFLAGS+=	-L${LUA}/lib
LDFLAGS+=	-L/usr/local/lib/ -L/usr/pkg/lib
LDFLAGS+=	-lm -llua50 -llualib50
LDFLAGS+=	${NLS_LDFLAGS}

all:
	@echo "Nothing to build (not making NLS.)"

nls: ${LIB}

${LIB}: ${OBJS} ${COMPATOBJ}
	${CC} ${OBJS} ${COMPATOBJ} -shared ${LDFLAGS} -o lgettext.so

${COMPATOBJ}:
	${CC} ${CFLAGS} -I${COMPAT} -c ${COMPAT}/compat-5.1.c -o ${COMPATOBJ}

strip: ${LIB}
	${STRIP} ${LIB}

clean:
	rm -f *.o *.so

distribution:
	@echo "Nothing to distribute (not distributing NLS.)"

distribution_nls: strip
	mkdir -p ${DESTDIR}/lib/lua/5.0
	cp ${LIB} ${DESTDIR}/lib/lua/5.0
