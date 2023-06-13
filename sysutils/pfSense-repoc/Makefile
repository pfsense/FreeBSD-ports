# $FreeBSD$

PORTNAME=	repoc
DISTVERSION=	20230605
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
GL_COMMIT=	34980b6ce28f461bc10978c1c4bc98b47adffac3
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
