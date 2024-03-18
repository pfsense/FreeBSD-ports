# $FreeBSD$

PORTNAME=	repoc
DISTVERSION=	20240318
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
GL_COMMIT=	6e0c8658ca623dc4a4c400c8a49a1b9e790852f5
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
