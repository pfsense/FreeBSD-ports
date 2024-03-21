# $FreeBSD$

PORTNAME=	repoc
DISTVERSION=	20240320
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
GL_COMMIT=	af1fb35a80890fb79c5d5359ece28f722efb8989
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
