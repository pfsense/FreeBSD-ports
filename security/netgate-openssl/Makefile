# $FreeBSD$

PORTNAME=	openssl
DISTVERSION=	20180618
PORTREVISION=	1
CATEGORIES=	security
PKGNAMEPREFIX=	netgate-

MAINTAINER=	garga@FreeBSD.org
COMMENT=	Netgate thoth OpenSSL engine

LIB_DEPENDS=	libcryptoauth.so:security/netgate-cryptoauthlib \
		libthoth.so:security/netgate-libthoth

MAKE_ARGS=	ATCA_HAL_I2C=1
USES=		ssl
PREFIX=		/usr
PLIST_FILES=	${PREFIX}/lib/engines/libateccx08.so
USE_LDCONFIG=	yes
USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	thoth
GL_PROJECT=	thoth
GL_COMMIT=	ad99dde939e0c38016fbaef9232689e1c28e43a9
FETCH_BEFORE_ARGS=	--no-verify-peer
BUILD_WRKSRC=	${WRKSRC}/${PORTNAME}
INSTALL_WRKSRC=	${WRKSRC}/${PORTNAME}

.include <bsd.port.mk>
