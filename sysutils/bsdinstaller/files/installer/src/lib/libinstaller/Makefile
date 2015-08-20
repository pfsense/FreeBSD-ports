# Makefile for libinstaller.
# $Id: Makefile,v 1.35 2005/08/26 22:44:37 cpressey Exp $

BASE?=../..
include ${BASE}/Config

LIB=	installer
LIB_VER=5

OBJS=	commands.o confed.o diskutil.o functions.o \
	mount.o package.o survey.o uiutil.o

CFLAGS+=	${WARNS} -fPIC -I../libaura -I../libdfui
CFLAGS+=	-I/usr/local/include -I/usr/pkg/include
CFLAGS+=	${EXTRA_CFLAGS}
LDFLAGS+=	-L../libaura -L../libdfui 
LDFLAGS+=	-L/usr/local/lib -L/usr/pkg/lib
LDFLAGS+=	-laura -ldfui

# Default target:

all: ${SONAME} ${ANAME}

# Main targets, shared and static:

${SONAME}: ${OBJS}
	${CC} ${OBJS} -shared ${LDFLAGS} -o ${SONAME}
	ln -sf ${SONAME} ${SOLINK}

${ANAME}: ${OBJS}
	${AR} rc ${ANAME} ${OBJS}
	${RANLIB} ${ANAME}

strip: ${SONAME}
	strip ${SONAME}

clean:
	rm -rf *.o *.so *.so.* *.So *.a

distribution: all
	mkdir -p ${DESTDIR}/lib
	cp ${SONAME} ${DESTDIR}/lib/
	ln -sf ${SONAME} ${DESTDIR}/lib/${SOLINK}
	cp ${ANAME} ${DESTDIR}/lib/
