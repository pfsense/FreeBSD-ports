# $FreeBSD$

PORTNAME=	pfSense-Status_Monitoring
PORTVERSION=	1.6.0
PORTREVISION=	2
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense Status Monitoring

LICENSE=	APACHE20

NO_BUILD=	yes
NO_MTREE=	yes
USE_PHP=	yes

PLIST_SUB=	PORTNAME=${PORTNAME}

.include <bsd.port.pre.mk>

RUN_DEPENDS+=	${PHPBASE}/lib/php/${PHP_EXT_DIR}/rrd.so:databases/pecl-rrd

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${PREFIX}/www
	${MKDIR} ${STAGEDIR}${PREFIX}/share/pfSense/menu
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/status_monitoring.priv.inc \
		${STAGEDIR}/etc/inc/priv/${PORTNAME}.priv.inc
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/status_monitoring.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/rrd_fetch_json.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/menu.xml \
		${STAGEDIR}${PREFIX}/share/pfSense/menu/${PORTNAME}.xml

.include <bsd.port.post.mk>
