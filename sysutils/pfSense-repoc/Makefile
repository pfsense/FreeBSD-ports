# $FreeBSD$

PORTNAME=	repoc
DISTVERSION=	20230719
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
GL_COMMIT=	ee22a030f7d24aa4e7856ad294c12f1848166a1d
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
