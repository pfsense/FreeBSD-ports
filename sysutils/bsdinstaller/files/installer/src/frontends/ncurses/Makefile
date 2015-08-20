# Makefile for dfuife_curses.
# $Id: Makefile,v 1.28 2005/08/29 19:19:13 cpressey Exp $

BASE?=../..
include ${BASE}/Config

PACKAGE=dfuife_curses

OBJS=	curses_util.o \
	curses_form.o curses_widget.o curses_bar.o \
	curses_xlat.o \
	main.o

CFLAGS+=	${WARNS}
CFLAGS+=	-I../../lib/libaura -I../../lib/libdfui
CFLAGS+=	-I/usr/local/include -I/usr/pkg/include
CFLAGS+=	${NLS_CFLAGS}
CFLAGS+=	${EXTRA_CFLAGS}

LDFLAGS+=	-L../../lib/libaura -L../../lib/libdfui -ldfui -laura
LDFLAGS+=	-L/usr/local/lib -L/usr/pkg/lib -lpanel -lncurses
LDFLAGS+=	${NLS_LDFLAGS}

LANGUAGES=	ru

# Default target.

PROG=	dfuife_curses

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

strip: ${PROG}
	strip ${PROG}

clean:
	rm -f aura dfui
	rm -rf ${PROG} *.o po/*.mo *.core

# In-CVS-tree testing.

LIBPATH?=../../lib/libaura:../../lib/libdfui:/usr/pkg/lib
DEBUGGER?=gdb

test:
	env LD_LIBRARY_PATH="${LIBPATH}" ./${PROG}

debug:
	env LD_LIBRARY_PATH="${LIBPATH}" ${DEBUGGER} ./${PROG}

# Create directory layout for binary distribution.

distribution: all
	mkdir -p ${DESTDIR}/sbin
	cp dfuife_curses ${DESTDIR}/sbin/
	mkdir -p ${DESTDIR}/share/dfuife_curses
	cp fred.txt ${DESTDIR}/share/dfuife_curses/

distribution_nls: nls
	for _language in ${LANGUAGES}; do \
		mkdir -p ${DESTDIR}/share/locale/$${_language}/LC_MESSAGES; \
		cp po/$${_language}.mo \
		    ${LOCALEDIR}/$${_language}/LC_MESSAGES/${PROG}.mo; \
	done
