# Make all platforms(/engines(/frameworks.))
# $Id: Makefile,v 1.8 2005/08/29 20:41:52 cpressey Exp $

BASE=..
include $(BASE)/Config

all:
	(cd lua && ${SKIP_LUA_BACKEND} || ${MAKE} all)

clean:
	(cd lua && ${SKIP_LUA_BACKEND} || ${MAKE} clean)

distribution:
	(cd lua && ${SKIP_LUA_BACKEND} || ${MAKE} distribution)
