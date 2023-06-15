# $FreeBSD$

PORTNAME=	repoc
DISTVERSION=	20230615
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
GL_COMMIT=	a9bda6a568830a6aaf79fe165dc23f44bf322d50
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
