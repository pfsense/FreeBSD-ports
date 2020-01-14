# $FreeBSD$

PORTNAME=	ping-auth
DISTVERSION=	20191007
CATEGORIES=	security
PKGNAMEPREFIX=	netgate-

MAINTAINER=	garga@FreeBSD.org
COMMENT=	Netgate thoth authentication

LIB_DEPENDS=	libucl.so:textproc/libucl

RUN_DEPENDS=	/etc/thoth/ca.pem:security/netgate-ca

PLIST_FILES=	bin/ping-auth.sh sbin/ping-auth
USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	thoth
GL_PROJECT=	thoth
GL_COMMIT=	df047aa5274693b2026d6bb812d922d89084e9b7
FETCH_BEFORE_ARGS=	--no-verify-peer
BUILD_WRKSRC=	${WRKSRC}/${PORTNAME}
INSTALL_WRKSRC=	${WRKSRC}/${PORTNAME}

.include <bsd.port.mk>
