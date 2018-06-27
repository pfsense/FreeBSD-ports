# $FreeBSD$

PORTNAME=	libthoth
DISTVERSION=	20180626
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
GL_COMMIT=	4c0b64a4e1841a97ebfc2e3c8b72bc8e84e66d19
FETCH_BEFORE_ARGS=	--no-verify-peer
BUILD_WRKSRC=	${WRKSRC}/${PORTNAME}
INSTALL_WRKSRC=	${WRKSRC}/${PORTNAME}

.include <bsd.port.mk>
