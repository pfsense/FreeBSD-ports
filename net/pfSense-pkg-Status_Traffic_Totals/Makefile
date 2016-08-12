# $FreeBSD$

PORTNAME=	pfSense-pkg-Status_Traffic_Totals
PORTVERSION=	1.1
PORTREVISION=	1
CATEGORIES=	net
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	Traffic Totals using the vnStat database

LICENSE=	ESF

NO_BUILD=	yes
NO_MTREE=	yes

PLIST_SUB=	PORTNAME=${PORTNAME}

.include <bsd.port.pre.mk>

RUN_DEPENDS+=	${LOCALBASE}/bin/vnstat:net/vnstat

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${PREFIX}/www
	${MKDIR} ${STAGEDIR}${PREFIX}/www/widgets/widgets
	${MKDIR} ${STAGEDIR}${PREFIX}/www/widgets/include
	${MKDIR} ${STAGEDIR}${PREFIX}/share/pfSense/menu
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/status_traffic_totals.priv.inc \
		${STAGEDIR}/etc/inc/priv/${PORTNAME}.priv.inc
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/status_traffic_totals.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/vnstat_fetch_json.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/widgets/widgets/traffic_totals.widget.php \
		${STAGEDIR}${PREFIX}/www/widgets/widgets
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/widgets/include/traffic_totals.inc \
		${STAGEDIR}${PREFIX}/www/widgets/include
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/menu.xml \
		${STAGEDIR}${PREFIX}/share/pfSense/menu/${PORTNAME}.xml

.include <bsd.port.post.mk>
