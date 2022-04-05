PORTNAME=		libbe
DISTVERSIONPREFIX=	v
DISTVERSION=		0.1.2
CATEGORIES=		devel
PKGNAMEPREFIX=		${PHP_PKGNAMEPREFIX}

MAINTAINER=		cmcdonald@netgate.com
COMMENT=		FreeBSD libbe(3) library glue extension for PHP

LICENSE=		BSD

BUILD_DEPENDS=		re2c>0:devel/re2c

USES=			libtool php:ext pkgconfig

USE_GITHUB=		yes
GH_ACCOUNT=		theonemcdonald
GH_PROJECT=		php-ext-libbe

GNU_CONFIGURE=		yes
CONFIGURE_ARGS=		--with-libbe

PHP_MODNAME=		libbe

.include <bsd.port.mk>
