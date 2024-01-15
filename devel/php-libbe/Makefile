PORTNAME=		libbe
DISTVERSIONPREFIX=	v
DISTVERSION=		0.1.4.1
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
GH_TAGNAME=		3c94e7d599d24bba095fff250b1104246318649e

GNU_CONFIGURE=		yes
CONFIGURE_ARGS=		--with-libbe

PHP_MODNAME=		libbe

.include <bsd.port.mk>
