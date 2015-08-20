# $FreeBSD$

PORTNAME=	pfSense
PORTVERSION=	0.5
CATEGORIES=	devel
MASTER_SITES=	#
DISTFILES=	#
PKGNAMEPREFIX=	php55-
PKGNAMESUFFIX=	-module

MAINTAINER=	eri@pfsense.org
COMMENT=	Library for getting useful info

USE_PHP=	yes
USE_PHPEXT=	yes

CFLAGS+=	-I/usr/local/include -L/usr/local/lib -I. -DIPFW_FUNCTIONS

CONFIGURE_ARGS=	--enable-pfSense

extract:
	mkdir -p ${WRKSRC}
	cp -r files/* ${WRKSRC}

.include <bsd.port.mk>
