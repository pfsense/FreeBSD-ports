PORTNAME=	radius
PORTVERSION=	1.4.0b1
PORTREVISION=	3
CATEGORIES=	net security pear

MAINTAINER=	sunpoet@FreeBSD.org
COMMENT=	Radius client library for PHP
WWW=		https://pecl.php.net/package/radius \
		https://github.com/LawnGnome/php-radius

LICENSE=	BSD3CLAUSE

USES=		php:pecl

CONFIGURE_ARGS=	--enable-radius

OPTIONS_DEFINE=	EXAMPLES

post-extract:
	${CP} ${FILESDIR}/*.stub.php ${WRKSRC}
	${CP} ${FILESDIR}/*_arginfo.h ${WRKSRC}

post-install-EXAMPLES-on:
	${MKDIR} ${STAGEDIR}${EXAMPLESDIR}
	${INSTALL_DATA} ${WRKSRC}/examples/* ${STAGEDIR}${EXAMPLESDIR}

.include <bsd.port.mk>
