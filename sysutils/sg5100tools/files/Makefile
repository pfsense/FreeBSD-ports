
PROGS = sg5100led sg5100resetbtn

CFLAGS += -Wall -pedantic

all: $(PROGS)

clean:
	rm -f $(PROGS)

install:
	${INSTALL} ${PROGS} ${DESTDIR}${PREFIX}/sbin
