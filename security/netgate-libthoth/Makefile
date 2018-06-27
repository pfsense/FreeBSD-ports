# $FreeBSD$

PORTNAME=	libthoth
DISTVERSION=	20180627
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
GL_COMMIT=	48e7a43a60d5e9966a76eeffa2f259f60b8a1a43
FETCH_BEFORE_ARGS=	--no-verify-peer
BUILD_WRKSRC=	${WRKSRC}/${PORTNAME}
INSTALL_WRKSRC=	${WRKSRC}/${PORTNAME}

.include <bsd.port.mk>
