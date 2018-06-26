# $FreeBSD$

PORTNAME=	simplepie
PORTVERSION=	1.5.1
CATEGORIES=	www
PKGNAMEPREFIX=	${PHP_PKGNAMEPREFIX}

MAINTAINER=	garga@FreeBSD.org
COMMENT=	Simple Atom/RSS parsing library for PHP

LICENSE=	BSD3CLAUSE
LICENSE_FILE=	${WRKSRC}/LICENSE.txt

USES=		php:build,cli,flavors
USE_PHP=	curl mbstring pcre
USE_GNOME=	libxml2
USE_GITHUB=	yes
NO_MTREE=	yes
NO_BUILD=	yes
NO_ARCH=	yes

do-install:
	${MKDIR} ${STAGEDIR}${WWWDIR}
	cd ${WRKSRC}/library && ${COPYTREE_SHARE} . ${STAGEDIR}${WWWDIR}

.include <bsd.port.mk>
