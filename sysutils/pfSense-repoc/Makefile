PORTNAME=	repoc
DISTVERSION=	20241121
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
GL_COMMIT=	8552d9c8cf4cf3a033f816fe3ed119e0e9c6bde8
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
