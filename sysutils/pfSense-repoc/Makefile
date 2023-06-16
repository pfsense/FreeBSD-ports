# $FreeBSD$

PORTNAME=	repoc
DISTVERSION=	20230616
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
GL_COMMIT=	5b49b75f2a3cdf2349139152b2ca52e78dcbfd18
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
