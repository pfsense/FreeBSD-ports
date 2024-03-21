# $FreeBSD$

PORTNAME=	repoc
DISTVERSION=	20240321
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
GL_COMMIT=	e1a4594393fb67ebce5c2feb302b5072e815a36e
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
