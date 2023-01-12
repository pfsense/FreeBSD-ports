PORTNAME=	libmaxminddb
PORTVERSION=	1.7.1
CATEGORIES=	net

MAINTAINER=	sunpoet@FreeBSD.org
COMMENT=	Library for the MaxMind DB file format used for GeoIP2
WWW=		https://github.com/maxmind/libmaxminddb

LICENSE=	APACHE20
LICENSE_FILE=	${WRKSRC}/LICENSE

USES=		autoreconf compiler:c11 cpe gmake libtool

USE_GITHUB=	yes
GH_ACCOUNT=	maxmind
GH_PROJECT=	libmaxminddb 

GH_TUPLE=	zorgnax:libtap:56e3123:libtap/t/libtap \
		maxmind:MaxMind-DB:31a33b3:MaxMind_DB/t/maxmind-db

CONFIGURE_ARGS=	--with-pkgconfigdir=${PREFIX}/libdata/pkgconfig
GNU_CONFIGURE=	yes
INSTALL_TARGET=	install-strip
USE_LDCONFIG=	yes

CPE_VENDOR=	maxmind

.include <bsd.port.mk>
