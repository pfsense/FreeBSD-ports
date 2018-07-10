# $FreeBSD$

PORTNAME=	thoth
DISTVERSION=	20180630
CATEGORIES=	security
PKGNAMEPREFIX=	netgate-

MAINTAINER=	garga@FreeBSD.org
COMMENT=	Netgate thoth

LIB_DEPENDS=	libcryptoauth.so:security/netgate-cryptoauthlib \
		libthoth.so:security/netgate-libthoth \
		libucl.so:textproc/libucl

RUN_DEPENDS=	/etc/thoth/ca.pem:security/netgate-ca

PLIST_FILES=	bin/thoth bin/thoth-prov

USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	thoth
GL_PROJECT=	thoth
GL_COMMIT=	0d33b9008319341a4c6122b6bf0f6a1c68a83045
FETCH_BEFORE_ARGS=	--no-verify-peer
BUILD_WRKSRC=	${WRKSRC}/${PORTNAME}
INSTALL_WRKSRC=	${WRKSRC}/${PORTNAME}

.include <bsd.port.mk>
