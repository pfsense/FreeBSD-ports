# $FreeBSD$

PORTNAME=	repoc
DISTVERSION=	20241031
CATEGORIES=	sysutils
PKGNAMEPREFIX=	pfSense-

MAINTAINER=	luiz@netgate.com
COMMENT=	pfSense dynamic repository client

LIB_DEPENDS=	libucl.so:textproc/libucl

PLIST_FILES=	sbin/pfSense-repoc \
		sbin/pfSense-repoc-static
USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	pfSense
GL_PROJECT=	repoc
GL_COMMIT=	a6ad79d7e61f8bd6377201a2cd6d6edb02af21de
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
