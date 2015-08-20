# makefile for POSIX library for Lua

BASE?=../../..
include ${BASE}/Config

# change these to reflect your Lua installation
LUA= ../../../platform/lua
LUAINC= $(LUA)/include
LUALIB= $(LUA)/lib
LUABIN= $(LUA)/bin

COMPAT=../compat-5.1
COMPATOBJ=$(COMPAT)/compat-5.1.o

MYNAME= posix
MYLIB= l$(MYNAME)

# no need to change anything below here
INCS= -I$(LUAINC)
WARN= -pedantic -Wall
CFLAGS+= $(EXTRA_CFLAGS) -fPIC $(INCS) $(WARN) -O2
LDFLAGS+=-L$(LUALIB) -L/usr/local/lib -L/usr/pkg/lib -llua50 -llualib50

OBJS= $(MYLIB).o

T= $(MYLIB).so

all:	$T

strip:	$T
	strip $T

test:	$T
	$(LUABIN)/lua -l$(MYNAME) test.lua

$T:	$(OBJS) $(COMPATOBJ)
	$(CC) -fPIC $(OBJS) $(COMPATOBJ) -shared $(LDFLAGS) -o $@

$(OBJS): modemuncher.c

tree:	$T
	$(LUABIN)/lua -l$(MYNAME) tree.lua .

clean:
	rm -f $(OBJS) $T core core.* a.out

x:
	@echo "$(MYNAME) library:"
	@grep '},' $(MYLIB).c | cut -f2 | tr -d '{",' | sort | column

xx:
	@echo "$(MYNAME) library:"
	@fgrep '/**' $(MYLIB).c | cut -f2 -d/ | tr -d '*' | sort | column

# distribution

distribution: strip
	mkdir -p $(DESTDIR)/lib/lua/5.0
	cp $T $(DESTDIR)/lib/lua/5.0

# eof
