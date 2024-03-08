# $FreeBSD$

PORTNAME=	repoc
DISTVERSION=	20240308
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
GL_COMMIT=	24efce843da68a7098bb82d1bdaa81c20a0308a6
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
