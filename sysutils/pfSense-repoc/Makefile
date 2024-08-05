# $FreeBSD$

PORTNAME=	repoc
DISTVERSION=	20240805
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
GL_COMMIT=	32bd7dd3bc183446a3d909b692a1a52bd815ad97
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
