# $FreeBSD$

PORTNAME=	ca
DISTVERSION=	20180827
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
GL_COMMIT=	a894ad212de44d0159b271ab5e5859e14c1d4198
FETCH_BEFORE_ARGS=	--no-verify-peer
BUILD_WRKSRC=	${WRKSRC}/${PORTNAME}
INSTALL_WRKSRC=	${WRKSRC}/${PORTNAME}

.include <bsd.port.mk>
