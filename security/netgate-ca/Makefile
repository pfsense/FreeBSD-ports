# $FreeBSD$

PORTNAME=	ca
DISTVERSION=	20202511
PORTREVISION=	1
CATEGORIES=	security
PKGNAMEPREFIX=	netgate-

MAINTAINER=	garga@FreeBSD.org
COMMENT=	Netgate thoth CA chain

PLIST_FILES=	etc/thoth/ca.pem

USE_GITLAB=	yes
PREFIX=		/
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	thoth
GL_PROJECT=	thoth
GL_COMMIT=	43d4127fed887a1f32a38b05f24f733e7d221065
FETCH_BEFORE_ARGS=	--no-verify-peer
BUILD_WRKSRC=	${WRKSRC}/${PORTNAME}
INSTALL_WRKSRC=	${WRKSRC}/${PORTNAME}

.include <bsd.port.mk>
