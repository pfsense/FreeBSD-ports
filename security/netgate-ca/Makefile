# $FreeBSD$

PORTNAME=	ca
DISTVERSION=	20210105
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
GL_COMMIT=	e2d5b82dddcdf34dc0241ce54f0146e864cbf33a
FETCH_BEFORE_ARGS=	--no-verify-peer
BUILD_WRKSRC=	${WRKSRC}/${PORTNAME}
INSTALL_WRKSRC=	${WRKSRC}/${PORTNAME}

.include <bsd.port.mk>
