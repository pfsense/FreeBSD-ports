PORTNAME=	ivykis
DISTVERSIONPREFIX=	v
DISTVERSION=	0.43.2
PORTREVISION=  1
CATEGORIES=	devel

MAINTAINER=	cy@FreeBSD.org
COMMENT=	Asynchronous I/O readiness notification library
WWW=		https://github.com/buytenh/ivykis

LICENSE=	LGPL21
LICENSE_FILE=	${WRKSRC}/COPYING

USES=		libtool pathfix pkgconfig
GNU_CONFIGURE=	yes
USE_LDCONFIG=	yes

GNU_CONFIGURE_MANPREFIX= ${PREFIX}/share

USE_GITHUB=	yes
GH_ACCOUNT=	buytenh

INSTALL_TARGET=	install-strip

.include <bsd.port.mk>
