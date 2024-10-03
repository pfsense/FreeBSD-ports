# $FreeBSD$

PORTNAME=	repoc
DISTVERSION=	20241003
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
GL_COMMIT=	a374f8cf14b33c01d0f139c19ebb8fa6aab76916
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
