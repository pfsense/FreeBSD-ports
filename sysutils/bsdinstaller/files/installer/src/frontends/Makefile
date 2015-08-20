# Make all frontends.
# $Id: Makefile,v 1.16 2005/08/29 20:41:52 cpressey Exp $

BASE=..
include $(BASE)/Config

all:
	(cd test && ${MAKE} all) && \
	(cd ncurses && ${SKIP_NCURSES_FRONTEND} || ${MAKE} all) && \
	(cd cgi && ${SKIP_CGI_FRONTEND} || ${MAKE} all) && \
	(cd qt && ${SKIP_QT_FRONTEND} || ${MAKE} all)

nls:
	(cd test && ${MAKE} all) && \
	(cd ncurses && ${SKIP_NCURSES_FRONTEND} || ${MAKE} nls) && \
	(cd cgi && ${SKIP_CGI_FRONTEND} || ${MAKE} nls) && \
	(cd qt && ${SKIP_QT_FRONTEND} || ${MAKE} all)

clean:
	(cd test && ${MAKE} clean) && \
	(cd ncurses && ${SKIP_NCURSES_FRONTEND} || ${MAKE} clean) && \
	(cd cgi && ${SKIP_CGI_FRONTEND} || ${MAKE} clean) && \
	(cd qt && ${SKIP_QT_FRONTEND} || ${MAKE} clean)

distribution:
	(cd ncurses && ${SKIP_NCURSES_FRONTEND} || ${MAKE} distribution) && \
	(cd cgi && ${SKIP_CGI_FRONTEND} || ${MAKE} distribution) && \
	(cd qt && ${SKIP_QT_FRONTEND} || ${MAKE} distribution)
