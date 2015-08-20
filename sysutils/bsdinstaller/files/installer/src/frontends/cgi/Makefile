# $Id: Makefile,v 1.18 2005/08/27 02:09:16 cpressey Exp $
# DFUI CGI frontend Makefile.

BASE?= ../..
include $(BASE)/Config

CFLAGS+=	-Wall
CFLAGS+=	-I../../lib/libaura -I../../lib/libdfui
CFLAGS+=	-I/usr/local/include -I/usr/pkg/include
CFLAGS+=	${EXTRA_CFLAGS}
LDFLAGS+=	-L./ -lcgic -L../../lib/libdfui -L../../lib/libaura
LDFLAGS+=	-L/usr/local/lib -L/usr/pkg/lib -ldfui -laura

all: dfuife.cgi libcgic.a cgictest.cgi capture example.cgi

nls: all

libcgic.a: cgic.o cgic.h
	rm -f libcgic.a
	$(AR) rc libcgic.a cgic.o
	$(RANLIB) libcgic.a

cgictest.cgi: cgictest.o libcgic.a
	$(CC) cgictest.c -o cgictest.cgi $(LDFLAGS)

capture: capture.o libcgic.a
	$(CC) capture.o -o capture $(LDFLAGS)

example.cgi: example.o
	$(CC) example.o -o example.cgi $(LDFLAGS)

example.o: example.c
	$(CC) $(CFLAGS) $(WARNS) -c  example.c -o example.o

dfuife_cgi.o: dfuife_cgi.c
	$(CC) $(CFLAGS) $(WARNS) -c dfuife_cgi.c -o dfuife_cgi.o

dfuife.cgi: dfuife_cgi.o libcgic.a
	$(CC) dfuife_cgi.o -o dfuife.cgi $(LDFLAGS)

strip: dfuife.cgi
	$(STRIP) dfuife.cgi

clean:
	rm -f *.o *.a *.cgi capture

distribution: strip
	mkdir -p ${DESTDIR}/www
	cp dfuife.cgi ${DESTDIR}/www
	cp dfly-pg.gif ${DESTDIR}/www
	cp fred-bg.gif ${DESTDIR}/www
	cp fred.png ${DESTDIR}/www
	cp dfuife.css ${DESTDIR}/www
	cp dfuife.js ${DESTDIR}/www
	cp index.html ${DESTDIR}/www

distribution_nls:
	@echo "No NLS to distribute."

