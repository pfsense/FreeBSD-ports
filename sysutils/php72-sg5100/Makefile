# $FreeBSD$

PORTNAME=	sg5100
DISTVERSION=	0.0.1
CATEGORIES=	devel
MASTER_SITES=	#
DISTFILES=	#

MAINTAINER=	dcornejo@netgate.com
COMMENT=	Support for SG-5100 LED and SW switch devices

IGNORE_WITH_PHP= 56 70 71

USES=		php:ext

CONFIGURE_ARGS=	--enable-sg5100

.include <bsd.port.options.mk>

do-extract:
	${MKDIR} ${WRKSRC}
	${CP} -r files/* ${WRKSRC}

.include <bsd.port.mk>
