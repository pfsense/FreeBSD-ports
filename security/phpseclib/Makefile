# $FreeBSD$

PORTNAME=	phpseclib
PORTVERSION=	2.0.17
CATEGORIES=	security
PKGNAMEPREFIX=	${PHP_PKGNAMEPREFIX}

MAINTAINER=	garga@FreeBSD.org
COMMENT=	PHP arbitrary-precision integer arithmetic library

LICENSE=	MIT
LICENSE_FILE=	${WRKSRC}/LICENSE

USES=		php:flavors

USE_GITHUB=	yes

NO_ARCH=	yes
NO_BUILD=	yes

do-install:
	@${MKDIR} ${STAGEDIR}${WWWDIR}
	(cd ${WRKSRC}/${PORTNAME} && ${COPYTREE_SHARE} \
		"Crypt File Math Net System" ${STAGEDIR}${WWWDIR})

.include <bsd.port.mk>
