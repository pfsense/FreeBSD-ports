# $FreeBSD$

PORTNAME=	repoc
DISTVERSION=	20230719
PORTREVISION=	1
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
GL_COMMIT=	402350f869f2edf456b9dc565a32ab971cfa88e2
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
