# Make all libraries.
# $Id: Makefile,v 1.19.2.1 2009/01/03 19:49:46 sullrich Exp $

CFLAGS += -fPIC

BASE=..
include $(BASE)/Config

all:
	(cd libaura && ${MAKE} all) && \
	(cd libdfui && ${MAKE} all) && \
	(cd libinstaller && ${SKIP_C_BACKEND} || ${MAKE} all) && \
	(cd lua && ${SKIP_LUA_BACKEND} || ${MAKE} all)

nls:
	(cd libaura && ${MAKE} all) && \
	(cd libdfui && ${MAKE} all) && \
	(cd libinstaller && ${SKIP_C_BACKEND} || ${MAKE} all) && \
	(cd lua && ${SKIP_LUA_BACKEND} || ${MAKE} nls)

clean:
	(cd libaura && ${MAKE} clean) && \
	(cd libdfui && ${MAKE} clean) && \
	(cd libinstaller && ${SKIP_C_BACKEND} || ${MAKE} clean) && \
	(cd lua && ${SKIP_LUA_BACKEND} || ${MAKE} clean)

distribution:
	(cd libaura && ${MAKE} distribution) && \
	(cd libdfui && ${MAKE} distribution) && \
	(cd libinstaller && ${SKIP_C_BACKEND} || ${MAKE} distribution) && \
	(cd lua && ${SKIP_LUA_BACKEND} || ${MAKE} distribution)
