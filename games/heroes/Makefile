PORTNAME=	heroes
PORTVERSION=	0.21
PORTREVISION=	21
CATEGORIES=	games
MASTER_SITES=	SF \
		SF/${PORTNAME}/${PORTNAME}-data/${DATAVERSION}:data \
		SF/${PORTNAME}/${PORTNAME}-sound-effects/${SOUNDVERSION}:sound \
		SF/${PORTNAME}/${PORTNAME}-sound-tracks/${MUSICVERSION}:music
DISTFILES=	${DISTNAME}${EXTRACT_SUFX} \
		${DATADISTNAME}${EXTRACT_SUFX}:data \
		${SOUNDISTNAME}${EXTRACT_SUFX}:sound \
		${MUSICDISTNAME}${EXTRACT_SUFX}:music

MAINTAINER=	danfe@FreeBSD.org
COMMENT=	Game of yore similar to the "Tron" and "Nibbles"
WWW=		https://heroes.sourceforge.net/

LICENSE=	GPLv2
LICENSE_FILE=	${WRKSRC}/COPYING

USES=		gettext gmake iconv localbase:ldflags sdl
USE_SDL=	mixer sdl

GNU_CONFIGURE=	yes
CONFIGURE_ARGS=	--with-sdl_mixer=${LOCALBASE} --without-gii --without-ggi

LDFLAGS+=	-lpthread -lm

INFO=		heroes

OPTIONS_DEFINE=	OPTIMIZED_CFLAGS

OPTIMIZED_CFLAGS_CONFIGURE_ENABLE=	optimizations

DATADISTNAME=	${PORTNAME}-data-${DATAVERSION}
DATASRC=	${WRKDIR}/${DATADISTNAME}
DATAVERSION=	1.5

MUSICDISTNAME=	${PORTNAME}-sound-tracks-${MUSICVERSION}
MUSICSRC=	${WRKDIR}/${MUSICDISTNAME}
MUSICVERSION=	1.0

SOUNDISTNAME=	${PORTNAME}-sound-effects-${SOUNDVERSION}
SOUNDSRC=	${WRKDIR}/${SOUNDISTNAME}
SOUNDVERSION=	1.0

post-patch:
	@for dir in ${DATASRC} ${SOUNDSRC} ${MUSICSRC}; do \
		${FIND} $${dir} -name "Makefile*" -delete; \
	done
	@${RM} ${WRKSRC}/lib/getopt.h

post-install:
.for dir in levels pics tilesets
	@${MKDIR} ${STAGEDIR}${DATADIR}/${dir}
	${INSTALL_DATA} ${DATASRC}/${dir}/* ${STAGEDIR}${DATADIR}/${dir}
.endfor
	@${MKDIR} ${STAGEDIR}${DATADIR}/sfx
	${INSTALL_DATA} ${SOUNDSRC}/*.wav ${SOUNDSRC}/sfx.conf ${STAGEDIR}${DATADIR}/sfx
	@${MKDIR} ${STAGEDIR}${DATADIR}/mod
	${INSTALL_DATA} ${MUSICSRC}/*.xm ${MUSICSRC}/sound.conf ${STAGEDIR}${DATADIR}/mod

.include <bsd.port.mk>
