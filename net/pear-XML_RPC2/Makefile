# Created by: Wen Heping <wenheping@gmail.com>

PORTNAME=	XML_RPC2
PORTVERSION=	1.1.4
CATEGORIES=	net devel pear

MAINTAINER=	sunpoet@FreeBSD.org
COMMENT=	XML-RPC client/server library

LICENSE=	PHP301

BUILD_DEPENDS=	${PEARDIR}/Cache/Lite.php:sysutils/pear-Cache_Lite@${PHP_FLAVOR} \
		${PHP_PKGNAMEPREFIX}pear-HTTP_Request2-230>=0:www/pear-HTTP_Request2-230@${PHP_FLAVOR}
RUN_DEPENDS=	${PEARDIR}/Cache/Lite.php:sysutils/pear-Cache_Lite@${PHP_FLAVOR} \
		${PHP_PKGNAMEPREFIX}pear-HTTP_Request2-230>=0:www/pear-HTTP_Request2-230@${PHP_FLAVOR}

USES=		pear php
USE_PHP=	curl

.include <bsd.port.mk>
