PORTNAME=	rvm
DISTVERSION=	1.20
PORTREVISION=	2
CATEGORIES=	devel
MASTER_SITES=	http://ftp.coda.cs.cmu.edu/pub/rvm/src/

MAINTAINER=	ports@FreeBSD.org
COMMENT=	Persistent VM library
WWW=		http://www.coda.cs.cmu.edu/

LICENSE=	GPLv2
LICENSE_FILE=	${WRKSRC}/COPYING

LIB_DEPENDS=	liblwp.so:devel/lwp

USES=		libtool pathfix pkgconfig
GNU_CONFIGURE=	yes
GNU_CONFIGURE_MANPREFIX=${PREFIX}/share
USE_LDCONFIG=	yes
INSTALL_TARGET=	install-strip

.include <bsd.port.mk>
