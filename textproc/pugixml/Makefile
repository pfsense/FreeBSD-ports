PORTNAME=	pugixml
DISTVERSION=	1.15
CATEGORIES=	textproc
MASTER_SITES=	https://github.com/zeux/pugixml/releases/download/v${DISTVERSION}/

MAINTAINER=	eduardo@FreeBSD.org
COMMENT=	Light-weight, simple and fast XML parser for C++ with XPath support
WWW=		https://pugixml.org/

LICENSE=	MIT
LICENSE_FILE=	${WRKSRC}/LICENSE.md

USES=		cmake compiler:c++11-lang cpe
CPE_VENDOR=	${PORTNAME}_project
USE_LDCONFIG=	yes

CMAKE_ON=	BUILD_SHARED_LIBS
CMAKE_OFF=	PUGIXML_BUILD_TESTS PUGIXML_USE_VERSIONED_LIBDIR

SOVERSION=	${PORTVERSION:R}
PLIST_SUB=	DISTVERSION=${DISTVERSION} \
		SOVERSION=${SOVERSION}

.include <bsd.port.mk>
