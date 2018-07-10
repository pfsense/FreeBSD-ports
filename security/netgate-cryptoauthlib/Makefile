# $FreeBSD$

PORTNAME=	netgate-cryptoauthlib
PORTVERSION=	20180710
CATEGORIES=	security

MAINTAINER=	garga@FreeBSD.org
COMMENT=	Microchip CryptoAuthentication Library

LICENSE=	LGPL21

USE_LDCONFIG=	yes
USE_GITHUB=	yes
GH_ACCOUNT=	netgate
GH_PROJECT=	cryptoauthlib

MAKE_ARGS=	-DATCA_PRINTF -DATCA_HAL_I2C -D__FreeBSD__

.include <bsd.port.mk>
