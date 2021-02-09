# $FreeBSD$

PORTNAME=	ping-auth
DISTVERSION=	20210209
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
GL_COMMIT=	ca243f6a4ed068b439f96faf1fa45e75d88ff479
FETCH_BEFORE_ARGS=	--no-verify-peer
BUILD_WRKSRC=	${WRKSRC}/${PORTNAME}
INSTALL_WRKSRC=	${WRKSRC}/${PORTNAME}

.include <bsd.port.mk>
