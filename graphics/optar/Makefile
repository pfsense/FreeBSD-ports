PORTNAME=	optar
PORTVERSION=	2015.02.09
PORTREVISION=	3
CATEGORIES=	graphics
MASTER_SITES=	http://ronja.twibright.com/
DISTNAME=	${PORTNAME}
DIST_SUBDIR=	${PORTNAME}/${PORTVERSION}

MAINTAINER=	ports@FreeBSD.org
COMMENT=	Tool for encoding data on paper
WWW=		http://ronja.twibright.com/optar/

LICENSE=	GPLv2
LICENSE_FILE=	${WRKSRC}/COPYING

LIB_DEPENDS=	libpng.so:graphics/png

WRKSRC=		${WRKDIR}/optar_dist

USES=		gmake magick:6,run tar:tgz

PLIST_FILES=	bin/optar bin/unoptar bin/pgm2ps

post-patch:
	@${REINPLACE_CMD} -e \
		's|^CFLAGS|#CFLAGS| ; \
		 s|gcc |$$(CC) | ; \
		 s|-I/usr/local/include/libpng|$$$$(libpng-config --I_opts)| ; \
		 s|-L/usr/local/lib|$$$$(libpng-config --L_opts)|' \
		${WRKSRC}/Makefile

do-install:
	(cd ${WRKSRC} && ${INSTALL_PROGRAM} optar unoptar \
		${STAGEDIR}${PREFIX}/bin)
	(cd ${WRKSRC} && ${INSTALL_SCRIPT} pgm2ps \
		${STAGEDIR}${PREFIX}/bin)

.include <bsd.port.mk>
