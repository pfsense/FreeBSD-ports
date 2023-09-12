# $FreeBSD$

PORTNAME=	repoc
DISTVERSION=	20230911
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
GL_COMMIT=	cf0a12986a2dccbab09cd6343cc05346234f4e2c
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
