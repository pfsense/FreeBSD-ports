# Makefile for dfuibe_installer.
# $Id: Makefile,v 1.38 2005/08/29 19:19:13 cpressey Exp $

BASE?=../..
include ${BASE}/Config

PACKAGE=dfuibe_installer

PROG=	dfuibe_installer
OBJS=	fn_disk.o fn_configure.o fn_diagnostic.o fn_subpart.o fn_install.o \
	flow.o main.o

MEDIAROOT?=/usr/release/root/

CFLAGS+=	${WARNS}
CFLAGS+=	-I../../lib/libaura -I../../lib/libdfui -I../../lib/libinstaller
CFLAGS+=	${NLS_CFLAGS}
CFLAGS+=	${EXTRA_CFLAGS} 

LDFLAGS+=	-L../../lib/libaura -L../../lib/libdfui -L../../lib/libinstaller
LDFLAGS+=	-laura -ldfui -linstaller
LDFLAGS+=	${NLS_LDFLAGS}

LANGUAGES=	ru

# Default target:

all: ${PROG}

# Main target:

${PROG}: ${OBJS}
	${CC} ${OBJS} ${LDFLAGS} -o ${PROG}

# NLS (translated text) compilations:

nls: all mo

mo:
	for _language in ${LANGUAGES}; do \
		msgfmt -o po/$${_language}.mo po/$${_language}; \
	done

# Translator templates:

pot:
	xgettext -s --no-location --keyword=_ -o po/${PROG}.pot *.c

# Creating stripped-down version:

strip: ${PROG}
	${STRIP} ${PROG}

# Cleaning up after ourselves:

clean:
	rm -rf ${PROG} *.o po/*.mo *.core

# Testing from within the CVS checkout.
# (This is a little gory because the location of the configuration
#  files is hardcoded in libinstaller.)

test:
	su root -c "\
	mkdir -p ${MEDIAROOT}/usr/local/share/dfuibe_installer/ && \
	cp conf/*.conf ${MEDIAROOT}/usr/local/share/dfuibe_installer/ && \
	env LD_LIBRARY_PATH='../../lib/libaura:../../lib/libdfui:../../lib/libinstaller' \
	    ./${PROG} -o ${MEDIAROOT}"

# Create directory layout for binary distribution.

distribution: all
	mkdir -p ${DESTDIR}/sbin
	cp dfuibe_installer ${DESTDIR}/sbin/
	mkdir -p ${DESTDIR}/share/dfuibe_installer
	cp conf/cmdnames.conf ${DESTDIR}/share/dfuibe_installer

distribution_nls: nls
	for _language in ${LANGUAGES}; do \
		mkdir -p ${DESTDIR}/share/locale/$${_language}/LC_MESSAGES; \
		cp po/$${_language}.mo \
		    ${LOCALEDIR}/$${_language}/LC_MESSAGES/${PROG}.mo; \
	done
