# $FreeBSD$

PORTNAME=	thoth
DISTVERSION=	20190130
PORTREVISION=	1
CATEGORIES=	security
PKGNAMEPREFIX=	netgate-

MAINTAINER=	garga@FreeBSD.org
COMMENT=	Netgate thoth

LIB_DEPENDS=	libucl.so:textproc/libucl

RUN_DEPENDS=	/etc/thoth/ca.pem:security/netgate-ca \
		/usr/local/bin/curl:ftp/curl

PLIST_FILES=	sbin/thoth sbin/thoth-prov

USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	thoth
GL_PROJECT=	thoth
GL_COMMIT=	0f1ea2cafaa7d7243fce9c2fa9cd8877f72f4524
FETCH_BEFORE_ARGS=	--no-verify-peer
BUILD_WRKSRC=	${WRKSRC}/${PORTNAME}
INSTALL_WRKSRC=	${WRKSRC}/${PORTNAME}

.include <bsd.port.mk>
