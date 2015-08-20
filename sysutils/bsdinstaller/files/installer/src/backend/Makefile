# Build all backends.
# $Id: Makefile,v 1.14 2005/08/29 20:41:52 cpressey Exp $

BASE=..
include $(BASE)/Config

all:
	(cd lua && ${SKIP_LUA_BACKEND} || ${MAKE} all) && \
	(cd installer && ${SKIP_C_BACKEND} || ${MAKE} all) && \
	cd test && ${MAKE} all

nls:
	(cd lua && ${SKIP_LUA_BACKEND} || ${MAKE} nls) && \
	(cd installer && ${SKIP_C_BACKEND} || ${MAKE} nls) && \
	cd test && ${MAKE} nls

clean:
	(cd lua && ${SKIP_LUA_BACKEND} || ${MAKE} clean) && \
	(cd installer && ${SKIP_C_BACKEND} || ${MAKE} clean) && \
	cd test && ${MAKE} clean

distribution:
	(cd lua && ${SKIP_LUA_BACKEND} || ${MAKE} distribution) && \
	(cd installer && ${SKIP_C_BACKEND} || ${MAKE} distribution)
