# New ports collection makefile for:	php4
# Date created:				Tue Feb 18 11:17:13 CET 2003
# Whom:					Alex Dupre <sysadmin@alexdupre.com>
#
# $FreeBSD$
#

PORTNAME=	clog
PORTVERSION=	1.0.1
PORTREVISION=	1
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# none
EXTRACT_ONLY=	# empty

MAINTAINER=	garga@pfsense.org
COMMENT=	Circular log support for FreeBSD syslogd

WHERE=		sbin
PLIST_FILES=    ${WHERE}/${PORTNAME} include/clog.h

# -O2 is dangerous for clog! It breaks on i386 -O works.
CFLAGS+=	-O

do-extract:
	mkdir -p ${WRKSRC}

do-build:
	${CC} ${CFLAGS} -o ${WRKSRC}/${PORTNAME} ${FILESDIR}/${PORTNAME}.c

do-install:
	${INSTALL_PROGRAM} ${WRKSRC}/${PORTNAME} ${STAGEDIR}${PREFIX}/${WHERE}
	${MKDIR} ${STAGEDIR}/${PREFIX}/include
	${INSTALL_DATA} ${FILESDIR}/${PORTNAME}.h ${STAGEDIR}${PREFIX}/include

.include <bsd.port.mk>
