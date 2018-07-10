# $FreeBSD$

PORTNAME=	openssl
DISTVERSION=	20180710
CATEGORIES=	security
PKGNAMEPREFIX=	netgate-
PKGNAMESUFFIX=	-static

MAINTAINER=	garga@FreeBSD.org
COMMENT=	Netgate thoth static OpenSSL engine

BUILD_DEPENDS=	${LOCALBASE}/lib/libcryptoauth.a:security/netgate-cryptoauthlib \
		${LOCALBASE}/lib/libthoth.a:security/netgate-libthoth

MAKE_ARGS=	-DATCA_HAL_I2C
USES=		ssl
PREFIX=		/usr/local
PLIST_FILES=	${PREFIX}/lib/libateccx08.a
USE_LDCONFIG=	yes
USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	thoth
GL_PROJECT=	thoth
GL_COMMIT=	8490c4af0d555918ea1867ec562e102c6780bd79
FETCH_BEFORE_ARGS=	--no-verify-peer
BUILD_WRKSRC=	${WRKSRC}/${PORTNAME}
INSTALL_WRKSRC=	${WRKSRC}/${PORTNAME}

.include <bsd.port.mk>
