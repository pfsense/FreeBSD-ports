# $FreeBSD$

PORTNAME=	ca
DISTVERSION=	20180626
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
GL_COMMIT=	e4f51474980a013930f432b9da468091f51e4784
FETCH_BEFORE_ARGS=	--no-verify-peer
BUILD_WRKSRC=	${WRKSRC}/${PORTNAME}
INSTALL_WRKSRC=	${WRKSRC}/${PORTNAME}

.include <bsd.port.mk>
