# $FreeBSD$

PORTNAME=	repoc
DISTVERSION=	20231207
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
GL_COMMIT=	a40bd796f41e4c0490620dffedefc4f56f1387b0
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
