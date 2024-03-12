# $FreeBSD$

PORTNAME=	repoc
DISTVERSION=	20240311
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
GL_COMMIT=	07bc78e1c35a6dbd00049b96ccdefdc32af0af42
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
