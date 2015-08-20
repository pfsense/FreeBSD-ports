# Make all Lua modules.
# $Id: Makefile,v 1.13.2.1 2009/01/03 19:49:46 sullrich Exp $

CFLAGS += -fPIC

all:
	cd app		&& ${MAKE} all && cd .. && \
	cd filename	&& ${MAKE} all && cd .. && \
	cd compat-5.1	&& ${MAKE} all && cd .. && \
	cd posix	&& ${MAKE} all && cd .. && \
	cd luasocket	&& ${MAKE} all && cd .. && \
	cd dfui		&& ${MAKE} all && cd .. && \
	cd gettext	&& ${MAKE} all && cd .. && \
	cd pty		&& ${MAKE} all && cd .. && \
	cd lcurses	&& ${MAKE} all

nls:
	cd app		&& ${MAKE} all && cd .. && \
	cd filename	&& ${MAKE} all && cd .. && \
	cd compat-5.1	&& ${MAKE} all && cd .. && \
	cd posix	&& ${MAKE} all && cd .. && \
	cd luasocket	&& ${MAKE} all && cd .. && \
	cd dfui		&& ${MAKE} all && cd .. && \
	cd gettext	&& ${MAKE} nls && cd .. && \
	cd pty		&& ${MAKE} all && cd .. && \
	cd lcurses	&& ${MAKE} all

clean:
	cd app		&& ${MAKE} clean && cd .. && \
	cd filename	&& ${MAKE} clean && cd .. && \
	cd compat-5.1	&& ${MAKE} clean && cd .. && \
	cd posix	&& ${MAKE} clean && cd .. && \
	cd luasocket	&& ${MAKE} clean && cd .. && \
	cd dfui		&& ${MAKE} clean && cd .. && \
	cd gettext	&& ${MAKE} clean && cd .. && \
	cd pty		&& ${MAKE} clean && cd .. && \
	cd lcurses	&& ${MAKE} clean

distribution:
	cd app		&& ${MAKE} distribution && cd .. && \
	cd filename	&& ${MAKE} distribution && cd .. && \
	cd compat-5.1	&& ${MAKE} distribution && cd .. && \
	cd posix	&& ${MAKE} distribution && cd .. && \
	cd luasocket	&& ${MAKE} distribution && cd .. && \
	cd dfui		&& ${MAKE} distribution && cd .. && \
	cd gettext	&& ${MAKE} distribution && cd .. && \
	cd pty		&& ${MAKE} distribution && cd .. && \
	cd lcurses	&& ${MAKE} distribution
