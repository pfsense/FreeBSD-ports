# $FreeBSD$

PORTNAME=	repoc
DISTVERSION=	20230719
PORTREVISION=	2
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
GL_COMMIT=	d4c38158eb7f02ded81930b7c6faefdaeb87be48
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
