# Make everything.
# $Id: Makefile,v 1.11 2005/08/29 20:41:52 cpressey Exp $

BASE=.
include $(BASE)/Config

# Order matters here.

all:
	(cd platform && ${MAKE} all) && \
	(cd lib && ${MAKE} all) && \
	(cd backend && ${MAKE} all) && \
	cd frontends && ${MAKE} all

nls:
	(cd platform && ${MAKE} all) && \
	(cd lib && ${MAKE} nls) && \
	(cd backend && ${MAKE} nls) && \
	cd frontends && ${MAKE} nls

clean:
	(cd platform && ${MAKE} clean) && \
	(cd lib && ${MAKE} clean) && \
	(cd backend && ${MAKE} clean) && \
	cd frontends && ${MAKE} clean

distribution:
	(cd platform && ${MAKE} distribution) && \
	(cd lib && ${MAKE} distribution) && \
	(cd backend && ${MAKE} distribution) && \
	cd frontends && ${MAKE} distribution
