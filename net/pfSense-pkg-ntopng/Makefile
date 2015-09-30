# $FreeBSD$

PORTNAME=	pfSense-pkg-ntopng
PORTVERSION=	0.8.1
CATEGORIES=	net
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense package ntopng

RUN_DEPENDS=	${LOCALBASE}/bin/ntopng:${PORTSDIR}/net/ntopng \
		${LOCALBASE}/bin/redis-cli:${PORTSDIR}/databases/redis \
		${LOCALBASE}/bin/gdbmtool:${PORTSDIR}/databases/gdbm \
		${LOCALBASE}/share/fonts/webfonts/arial.ttf:${PORTSDIR}/x11-fonts/webfonts \
		${LOCALBASE}/bin/acyclic:${PORTSDIR}/graphics/graphviz

CONFLICTS=	pfSense-base-nanobsd-[0-9]*

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/ntopng.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/ntopng.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}

.include <bsd.port.mk>
