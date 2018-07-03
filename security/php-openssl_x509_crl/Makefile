# $FreeBSD$

PORTNAME=	openssl_x509_crl
PORTVERSION=	1.2
CATEGORIES=	security devel
PKGNAMEPREFIX=	${PHP_PKGNAMEPREFIX}

MAINTAINER=	garga@FreeBSD.org
COMMENT=	PHP Class to create openssl Certificate Revocation List (CRL)

LICENSE=	MIT
LICENSE_FILE=	${WRKSRC}/LICENSE

NO_BUILD=	yes
NO_ARCH=	yes

USES=		php:flavors
USE_PHP=	bcmath openssl
USE_GITHUB=	yes
GH_ACCOUNT=	ukrbublik

do-install:
	@${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} ${WRKSRC}/src/* ${STAGEDIR}${DATADIR}

.include <bsd.port.mk>
