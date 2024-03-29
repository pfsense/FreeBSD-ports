# $FreeBSD$

PORTNAME=	repoc
DISTVERSION=	20240322
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
GL_COMMIT=	078cb4c49e3ce249d41d07058cec94b02e4aa040
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
