# $FreeBSD$

PORTNAME=	netgate-cryptoauthlib
PORTVERSION=	20180423
CATEGORIES=	security

MAINTAINER=	garga@FreeBSD.org
COMMENT=	Microchip CryptoAuthentication Library

LICENSE=	LGPL21

USE_LDCONFIG=	yes
USE_GITHUB=	yes
GH_ACCOUNT=	netgate
GH_PROJECT=	cryptoauthlib

MAKE_ARGS=	ATCA_PRINTF=1 ATCA_HAL_I2C=1 __FreeBSD__=1

.include <bsd.port.mk>
