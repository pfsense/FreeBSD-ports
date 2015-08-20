# makefile for lcurses library for Lua
# $Id: Makefile,v 1.9 2005/08/26 22:44:38 cpressey Exp $

BASE?=../../..
include ${BASE}/Config

# dist location
DISTDIR=$(HOME)/dist
TMP=/tmp

# change these to reflect your Lua installation
LUA=../../../platform/lua
LUAINC= $(LUA)/include
LUALIB= $(LUA)/lib
LUABIN= $(LUA)/bin
#LUABIN= /mingw/bin

COMPAT=../compat-5.1
COMPATOBJ=$(COMPAT)/compat-5.1.o

# no need to change anything below here
SHFLAGS= -fPIC
CFLAGS+= $(EXTRA_CFLAGS) $(INCS) $(DEFS) $(WARN) $(SHFLAGS) -O2
DEFS=
WARN= -Wall -ansi #-ansi -pedantic -Wall
INCS= -I$(LUAINC) -I/usr/pkg/include
LDFLAGS+= -L$(LUALIB) -L/usr/local/lib -L/usr/pkg/lib -lpanel -lcurses -llualib50 -llua50

MYNAME= curses
MYLIB= l$(MYNAME)

OBJS= $(MYLIB).o

T= $(MYLIB).so

VER=0.1-devel
TARFILE = $(DISTDIR)/$(MYLIB)-$(VER).tar.gz
TARFILES = \
	README Makefile \
	lcurses.c lpanel.c \
	lcurses.html \
	requireso.lua curses.lua curses.panel.lua \
	test.lua \
	cui.lua cui.ctrls.lua testcui.lua \
	firework.lua interp.lua

all: $T

lua: lcurses.c lua.c
	$(CC) -I. -DDEBUG -g -o lua lua.c lcurses.c -L. -llualib -llua -lpanel -lcurses -lm -ldl

cui: $T
	$(LUABIN)/lua -l$(MYNAME) -l$(MYNAME).panel testcui.lua

test:	$T
	env LD_LIBRARY_PATH='$(LUA)/lib' \
	    LUA_PATH='../?/?.lua;?.lua' \
	    LUA_CPATH='./?.so' \
	    $(LUA)/bin/lua -lcompat-5.1 test.lua

$T:	$(OBJS) $(COMPATOBJ)
	$(CC) $(SHFLAGS) -o $@ -shared $(OBJS) $(COMPATOBJ) $(LDFLAGS)

$(COMPATOBJ):
	$(CC) $(CFLAGS) -I$(COMPAT) -c $(COMPAT)/compat-5.1.c -o $(COMPATOBJ)

lcurses.o: lcurses.c lpanel.c

clean:
	rm -f $(OBJS) $T core core.* *.core a.out

dist:
	@echo 'Exporting...'
	@cvs export -r HEAD -d $(TMP)/$(MYLIB)-$(VER) $(MYLIB)
	@echo 'Compressing...'
	@tar -zcf $(TARFILE) -C $(TMP) $(MYLIB)-$(VER)
	@rm -fr $(TMP)/$(MYLIB)-$(VER)
	@lsum $(TARFILE) $(DISTDIR)/md5sums.txt
	@echo 'Done.'

strip: all
	${STRIP} $T

distribution: strip
	@echo "XXX Not yet implemented."
