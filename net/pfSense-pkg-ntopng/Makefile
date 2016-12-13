# $FreeBSD$

PORTNAME=	pfSense-pkg-ntopng
PORTVERSION=	0.8.5
CATEGORIES=	net
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense package ntopng

LICENSE=	APACHE20

RUN_DEPENDS=	${LOCALBASE}/bin/ntopng:net/ntopng \
		${LOCALBASE}/bin/redis-cli:databases/redis \
		${LOCALBASE}/bin/gdbmtool:databases/gdbm \
		${LOCALBASE}/share/fonts/webfonts/arial.ttf:x11-fonts/webfonts \
		${LOCALBASE}/bin/acyclic:graphics/graphviz \
		${LOCALBASE}/bin/geoiplookup:net/GeoIP

CONFLICTS=	pfSense-base-nanobsd-[0-9]*

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${PREFIX}/www
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/ntopng.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/ntopng.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/ntopng_redirect.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/ntopng.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
