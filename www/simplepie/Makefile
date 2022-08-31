# $FreeBSD$

PORTNAME=	simplepie
PORTVERSION=	1.5.8
PORTREVISION=	2
CATEGORIES=	www
PKGNAMEPREFIX=	${PHP_PKGNAMEPREFIX}

MAINTAINER=	garga@FreeBSD.org
COMMENT=	Simple Atom/RSS parsing library for PHP

LICENSE=	BSD3CLAUSE
LICENSE_FILE=	${WRKSRC}/LICENSE.txt

USES=		php:build,cli,flavors
USE_PHP=	curl mbstring pcre tokenizer
USE_GNOME=	libxml2
USE_GITHUB=	yes
GH_ACCOUNT=	pfsense
GH_TAGNAME=	52f37fa510746ac988aa77bbf24595e11e07b863
NO_MTREE=	yes
NO_ARCH=	yes
PLIST_FILES=	${WWWDIR}/simplepie.inc

post-patch:
	${FIND} ${WRKSRC} -name '*.bak' -delete -o -name '*.orig' -delete

do-build:
	cd ${WRKSRC} && ${PREFIX}/bin/php build/compile.php

do-install:
	${MKDIR} ${STAGEDIR}${WWWDIR}
	${INSTALL_DATA} ${WRKSRC}/SimplePie.compiled.php \
		${STAGEDIR}${WWWDIR}/simplepie.inc

.include <bsd.port.mk>
