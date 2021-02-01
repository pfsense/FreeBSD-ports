# $FreeBSD$

PORTNAME=	wireguard-tools
DISTVERSION=	20210201
CATEGORIES=	security

LICENSE=	GPLv2

MAINTAINER=	loos@FreeBSD.org
COMMENT=	Wireguard tools

USES=		gmake

WRKSRC?=	${WRKDIR}/${GL_PROJECT}-${GL_COMMIT}-${GL_COMMIT}/src

PLIST_FILES=	bin/wg

USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	pfSense
GL_PROJECT=	wireguard-tools
GL_COMMIT=	dca69a9314d96a31e2d7b3a6740da7eba6080d7c
FETCH_BEFORE_ARGS=	--no-verify-peer

post-install:
	${STRIP_CMD} ${STAGEDIR}${PREFIX}/bin/wg

.include <bsd.port.mk>
