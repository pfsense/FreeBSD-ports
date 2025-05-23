PORTNAME=	avfs
PORTVERSION=	1.1.4
CATEGORIES=	filesystems sysutils
MASTER_SITES=	SF/avf/${PORTNAME}/${PORTVERSION}

MAINTAINER=	danfe@FreeBSD.org
COMMENT=	Virtual File System library
WWW=		https://avf.sourceforge.net/

# FUSE daemon is licensed as GPLv2, shared library is LGPLv2
LICENSE=	LGPL20 GPLv2
LICENSE_COMB=	multi

RUN_DEPENDS=	${LOCALBASE}/bin/bash:shells/bash

USES=		libtool pkgconfig tar:bz2 shebangfix perl5
USE_PERL5=	run
SHEBANG_FILES=	extfs/uimg scripts/umountavfs scripts/mountavfs
USE_LDCONFIG=	yes
GNU_CONFIGURE=	yes
CONFIGURE_ENV=	PERL="${PERL}"

INSTALL_TARGET=	install-strip

OPTIONS_DEFINE=	DAV DEBUG FUSE ZSTD
OPTIONS_SUB=	yes

DAV_DESC=	Build the DAV module (requires libneon)
DAV_LIB_DEPENDS=	libneon.so:www/neon
DAV_CONFIGURE_ENABLE=	dav

DEBUG_CONFIGURE_ENABLE=	debug

FUSE_CONFIGURE_ENABLE=	fuse
FUSE_USES=	fuse

ZSTD_LIB_DEPENDS=	libzstd.so:archivers/zstd
ZSTD_CONFIGURE_WITH=	zstd

post-patch:
	@${REINPLACE_CMD} -e '/#include/s,neon/,,' ${WRKSRC}/modules/dav*.?

.include <bsd.port.mk>
