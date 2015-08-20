# BSD Makefile for dfuife_qt.
# $Id: Makefile,v 1.10 2005/08/26 22:44:37 cpressey Exp $

MAKEFILE_QT_EXISTS!=[ -r Makefile.qt ] || echo "NO"
QT_INSTALLED!=[ -r /usr/X11R6/lib/libqt-mt.so ] || echo "NO"
PROG=dfuife_qt

all:
.if ${QT_INSTALLED} == "NO"
	@echo "Qt not installed - skipping..."
.else
.if ${MAKEFILE_QT_EXISTS} == "NO"
	QTDIR=/usr/X11R6 qmake \
		-spec /usr/local/share/qt/mkspecs/freebsd-g++ \
		${PROG}.pro -o Makefile.qt
.endif
	QTDIR=/usr/X11R6 make -f Makefile.qt
.endif

strip: all
.if ${QT_INSTALLED} != "NO"
	strip dfuife_qt
.endif

clean:
.if ${MAKEFILE_QT_EXISTS} != "NO"
	make -f Makefile.qt clean
	rm -f Makefile.qt
.endif
	rm -f ${PROG} *.o

# Create directory layout for binary distribution.

distribution: strip
.if ${QT_INSTALLED} != "NO"
	mkdir -p ${DESTDIR}/sbin
	cp dfuife_qt ${DESTDIR}/sbin/
.endif
# .if defined(WITH_NLS)
#	for _language in ${LANGUAGES}; do \
#		mkdir -p ${DESTDIR}/share/locale/$${_language}/LC_MESSAGES; \
#		cp po/$${_language}.mo \
#		    ${LOCALEDIR}/$${_language}/LC_MESSAGES/${PROG}.mo; \
#	done
# .endif
