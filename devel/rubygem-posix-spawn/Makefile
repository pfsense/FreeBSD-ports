PORTNAME=	posix-spawn
PORTVERSION=	0.3.15
CATEGORIES=	devel rubygems
MASTER_SITES=	RG

MAINTAINER=	ruby@FreeBSD.org
COMMENT=	Ruby wrapper for posix_spawnp(2) for faster process spawning
WWW=		https://github.com/rtomayko/posix-spawn

LICENSE=	MIT
LICENSE_FILE=	${WRKSRC}/COPYING

USES=		gem

CONFIGURE_ARGS=	--with-cflags=-Wno-incompatible-function-pointer-types

PLIST_FILES=	bin/posix-spawn-benchmark

.include <bsd.port.mk>
