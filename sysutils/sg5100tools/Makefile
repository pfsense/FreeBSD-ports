# $FreeBSD$

PORTNAME=	sg5100tools
DISTVERSION=	0.0.1
CATEGORIES=	sysutils
MASTER_SITES=	#
DISTFILES=	#

MAINTAINER=	dcornejo@netgate.com
COMMENT=	Command line tools for SG-5100 hardware

.include <bsd.port.options.mk>

do-extract:
	${MKDIR} ${WRKSRC}
	${CP} -r files/* ${WRKSRC}

.include <bsd.port.mk>
