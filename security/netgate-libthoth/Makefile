# $FreeBSD$

PORTNAME=	libthoth
DISTVERSION=	20180621
CATEGORIES=	security
PKGNAMEPREFIX=	netgate-

MAINTAINER=	garga@FreeBSD.org
COMMENT=	Netgate thoth libraries

LIB_DEPENDS=	libcryptoauth.so:security/netgate-cryptoauthlib

USE_LDCONFIG=	yes
USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	thoth
GL_PROJECT=	thoth
GL_COMMIT=	5529fc55c7191eb138de129041b0cb350d19f30e
FETCH_BEFORE_ARGS=	--no-verify-peer
BUILD_WRKSRC=	${WRKSRC}/${PORTNAME}
INSTALL_WRKSRC=	${WRKSRC}/${PORTNAME}

.include <bsd.port.mk>
