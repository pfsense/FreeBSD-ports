# $FreeBSD$

PORTNAME=	wireguard-tools
DISTVERSION=	20200727
CATEGORIES=	security

LICENSE=	GPLv2

MAINTAINER=	loos@FreeBSD.org
COMMENT=	Wireguard tools

USES=		gmake

WRKSRC?=	${WRKDIR}/${GL_PROJECT}-${GL_COMMIT}-${GL_COMMIT}/src

PLIST_FILES=	bin/wg bin/wg-quick

USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	pfSense
GL_PROJECT=	wireguard-tools
GL_COMMIT=	a2d3a3dc9e7a0fb540d977932f82bfaa0f33bdcb
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
