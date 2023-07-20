# $FreeBSD$

PORTNAME=	repoc
DISTVERSION=	20230720
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
GL_COMMIT=	4a5ee9405af34344dd9c3045f8062e859c797253
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
