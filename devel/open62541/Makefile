PORTNAME=	open62541
DISTVERSIONPREFIX=	v
DISTVERSION=	1.4.11
CATEGORIES=	devel

MAINTAINER=	nsonack@herrhotzenplotz.de
COMMENT=	Open source implementation of OPC UA
WWW=		https://open62541.org/

LICENSE=	MPL20
LICENSE_FILE=	${WRKSRC}/LICENSE

USES=		cmake python shebangfix
SHEBANG_GLOB=	*.py
USE_LDCONFIG=	yes

USE_GITHUB=	yes

PLIST_SUB=	DISTVERSION=${DISTVERSION}

CMAKE_ON=	BUILD_SHARED_LIBS \
		CMAKE_DISABLE_FIND_PACKAGE_Git
CMAKE_OFF=	UA_FORCE_WERROR

OPTIONS_DEFINE=		LTO OPENSSL
OPTIONS_DEFAULT=	LTO OPENSSL

LTO_CMAKE_BOOL=	CMAKE_INTERPROCEDURAL_OPTIMIZATION

OPENSSL_USES=	ssl
OPENSSL_CMAKE_ON=	-DUA_ENABLE_ENCRYPTION=OPENSSL

.include <bsd.port.mk>
