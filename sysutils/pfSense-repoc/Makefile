PORTNAME=	repoc
DISTVERSION=	20250131
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
GL_COMMIT=	f1810671e54e84cec19e61494be6b7e381426968
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
