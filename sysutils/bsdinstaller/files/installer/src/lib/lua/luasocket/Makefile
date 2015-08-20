# $Id: Makefile,v 1.8 2005/08/27 07:20:39 cpressey Exp $
# BSD Installer Makefile for luasocket, adapted from makefile.Linux.

BASE?=../../..
include ${BASE}/Config

LV=2.0
MV=1.0

LUA?=../../../platform/lua

COMPAT?=../compat-5.1
COMPATOBJ=$(COMPAT)/compat-5.1.o

LUAC?=luac
BIN2C?=bin2c
DEF = -DLUASOCKET_DEBUG #-Dsocklen_t=int

LOBJS= \
	luasocket.o \
	timeout.o \
	buffer.o \
	io.o \
	auxiliar.o \
	except.o \
	select.o \
	options.o \
	inet.o \
	tcp.o \
	udp.o \
	usocket.o

MOBJS= \
	mime.o

CFLAGS+=$(EXTRA_CFLAGS) -I$(LUA)/include -I$(COMPAT) \
	 $(PROF) $(DBG) $(DEF) -pedantic -W -Wall -fPIC
LDFLAGS+=-L$(LUA)/lib -llua -llualib

LDYN=lsocket.so

MDYN=lmime.so

all: $(LDYN) $(MDYN)

$(LDYN): $(LOBJS) $(COMPATOBJ)
	$(CC) $(LOBJS) $(COMPATOBJ) -shared $(LDFLAGS) -o $(LDYN)

$(MDYN): $(MOBJS) $(COMPATOBJ)
	$(CC) -shared -o $(MDYN) $(MOBJS) $(COMPATOBJ) $(LDFLAGS)

auxiliar.o: auxiliar.c auxiliar.h
buffer.o: buffer.c buffer.h io.h timeout.h
except.o: except.c except.h
inet.o: inet.c inet.h socket.h io.h timeout.h usocket.h
io.o: io.c io.h timeout.h
luasocket.o: luasocket.c luasocket.h auxiliar.h except.h timeout.h \
  buffer.h io.h inet.h socket.h usocket.h tcp.h udp.h select.h
mime.o: mime.c mime.h
options.o: options.c auxiliar.h options.h socket.h io.h timeout.h \
  usocket.h inet.h
select.o: select.c socket.h io.h timeout.h usocket.h select.h
ssl.o: ssl.c ssl.h buffer.h io.h timeout.h socket.h usocket.h tcp.h
tcp.o: tcp.c auxiliar.h socket.h io.h timeout.h usocket.h inet.h \
  options.h tcp.h buffer.h
timeout.o: timeout.c luasocket.h auxiliar.h timeout.h
udp.o: udp.c auxiliar.h socket.h io.h timeout.h usocket.h inet.h \
  options.h udp.h
unix.o: unix.c auxiliar.h socket.h io.h timeout.h usocket.h options.h \
  unix.h buffer.h
usocket.o: usocket.c socket.h io.h  timeout.h usocket.h

$(COMPATOBJ):
	$(CC) $(CFLAGS) -I$(COMPAT) -c $(COMPAT)/compat-5.1.c -o $(COMPATOBJ)

strip: $(LDYN) $(MDYN)
	strip $(LDYN) $(MDYN)

clean:
	rm -f $(LOBJS) $(MOBJS) $(SOBJS)
	rm -f $(MDYN) $(LDYN) $(SDYN)

distribution: strip
	mkdir -p $(DESTDIR)/lib/lua/5.0
	cp $(LDYN) $(MDYN) $(DESTDIR)/lib/lua/5.0
	mkdir -p $(DESTDIR)/share/lua/5.0
	cp lua/*.lua $(DESTDIR)/share/lua/5.0
