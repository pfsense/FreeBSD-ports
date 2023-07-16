# $FreeBSD$

PORTNAME=	repoc
DISTVERSION=	20230711
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
GL_COMMIT=	4afccf8daf6db42cb0b84a378698b9b99546b03b
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
