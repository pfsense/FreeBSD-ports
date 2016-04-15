# $FreeBSD$

PORTNAME=	pfSense
PORTVERSION=	0.12
CATEGORIES=	devel
MASTER_SITES=	#
DISTFILES=	#
PKGNAMEPREFIX=	php56-
PKGNAMESUFFIX=	-module

MAINTAINER=	garga@pfsense.org
COMMENT=	Library for getting useful info

LIB_DEPENDS=	libvici.so:security/strongswan

USE_PHP=	yes
USE_PHPEXT=	yes

CFLAGS+=	-I/usr/local/include -I. -DIPFW_FUNCTIONS -DTCPSTATES
CFLAGS+=	-Wall -Wredundant-decls -Wnested-externs -Winline

CONFIGURE_ARGS=	--enable-pfSense

extract:
	mkdir -p ${WRKSRC}
	cp -r files/* ${WRKSRC}

.include <bsd.port.mk>
