# $FreeBSD$

PORTNAME=	wireguard-tools
DISTVERSION=	20201216
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
GL_COMMIT=	b252b4effb62f0a3a5727cc188cfa4ec7ea7ca59
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
