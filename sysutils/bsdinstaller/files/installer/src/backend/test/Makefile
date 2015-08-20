# Makefile for dfuibe_test.
# $Id: Makefile,v 1.25 2005/08/29 19:19:13 cpressey Exp $

BASE?=../..
include ${BASE}/Config

PACKAGE=dfuibe_test

PROG=	dfuibe_test
OBJS=	be_test.o

CFLAGS+=	${WARNS}
CFLAGS+=	-I../../lib/libaura -I../../lib/libdfui
CFLAGS+=	-I/usr/local/include -I/usr/pkg/include
CFLAGS+=	${NLS_CFLAGS}
CFLAGS+=	${EXTRA_CFLAGS}

LDFLAGS+=	-L../../lib/libaura -L../../lib/libdfui -L/usr/local/lib/
LDFLAGS+=	-L/usr/pkg/lib -ldfui -laura
LDFLAGS+=	${NLS_LDFLAGS}

LANGUAGES=	ru

# Default target.

all: ${PROG}

# Main target.

${PROG}: ${OBJS}
	${CC} ${OBJS} ${LDFLAGS} -o ${PROG}

nls: all mo

mo:
	for _language in ${LANGUAGES}; do \
		msgfmt -o po/$${_language}.mo po/$${_language}; \
	done

pot:
	xgettext -s --no-location --keyword=_ -o po/${PROG}.pot *.c

strip: all
	strip ${PROG}

clean:
	rm -rf ${PROG} *.o po/*.mo *.core

# In-CVS-tree testing

test:
	env LD_LIBRARY_PATH="../../lib/libaura:../../lib/libdfui" ./${PROG}

distribution: strip
	mkdir -p ${DESTDIR}/bin
	cp ${PROG} ${DESTDIR}/bin

distribution_nls: nls
	for _language in ${LANGUAGES}; do \
		${INSTALL} -c -o root -g wheel -m 444 po/$${_language}.mo \
		    ${LOCALEDIR}/$${_language}/LC_MESSAGES/${PACKAGE}.mo; \
	done
