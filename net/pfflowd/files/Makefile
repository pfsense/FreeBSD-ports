\WARNFLAGS=\
	-Wall -Waggregate-return -Wcast-align -Wcast-qual \
	-Wmissing-declarations -Wmissing-prototypes -Wno-conversion \
	-Wpointer-arith -Wshadow -Wuninitialized -Wcast-align \
	-Wcast-qual -WformatC=2 -Wformat-nonliteral -Wunused

LIBS=-lpcap -lutil
LDFLAGS=-static

CFLAGS= -O -DNF9 -DIPV6  -DEGRESS -DULL

.if defined(OLD_PFSYNC)
CFLAGS+=-DOLD_PFSYNC
.endif

TARGETS=pfflowd

all: $(TARGETS)

pfflowd: pfflowd.o pidfile.o nf9.o
	$(CC) $(LDFLAGS) -o $@ pfflowd.o pidfile.o nf9.o $(LIBS)

clean:
	rm -f $(TARGETS) *.o core *.core

strip:
	strip $(TARGETS)

