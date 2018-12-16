# $FreeBSD$

PORTNAME=	pfSense-Status_Monitoring
PORTVERSION=	1.7.7
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense Status Monitoring

LICENSE=	APACHE20

RUN_DEPENDS+=	${PECL_PKGNAMEPREFIX}rrd>=2.0.0:databases/pecl-rrd@${PHP_FLAVOR}

IGNORE_WITH_PHP=	56 70 71
USES=		php:flavors

NO_BUILD=	yes
NO_MTREE=	yes

PLIST_SUB=	PORTNAME=${PORTNAME}

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

.include <bsd.port.mk>
