# $FreeBSD$

PORTNAME=	pfSense-pkg-pfBlockerNG
PORTVERSION=	2.1.1
PORTREVISION=	5
CATEGORIES=	net
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense package pfBlockerNG

LICENSE=	ESF

RUN_DEPENDS=	${LOCALBASE}/bin/geoiplookup:net/GeoIP \
		${LOCALBASE}/bin/grepcidr:net-mgmt/grepcidr \
		${LOCALBASE}/bin/aggregate:net-mgmt/aggregate \
		${LOCALBASE}/bin/mwhois:net/whois \
		${LOCALBASE}/sbin/lighttpd:www/lighttpd

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${MKDIR} ${STAGEDIR}${PREFIX}/www/pfblockerng/www
	${MKDIR} ${STAGEDIR}${PREFIX}/www/widgets/widgets
	${MKDIR} ${STAGEDIR}${PREFIX}/www/widgets/include
	${MKDIR} ${STAGEDIR}${PREFIX}/www/widgets/javascript
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/pfblockerng.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/pfblockerng.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/pfblockerng/pfblockerng.inc \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/pfblockerng/pfblockerng_install.inc \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/pfblockerng/pfblockerng_extra.inc \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/pfblockerng/pfblockerng.php \
		${STAGEDIR}${PREFIX}/www/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/pfblockerng/pfblockerng_alerts.php \
		${STAGEDIR}${PREFIX}/www/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/pfblockerng/pfblockerng_alerts_ar.php \
		${STAGEDIR}${PREFIX}/www/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/pfblockerng/pfblockerng_update.php \
		${STAGEDIR}${PREFIX}/www/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/pfblockerng/pfblockerng_log.php \
		${STAGEDIR}${PREFIX}/www/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/pfblockerng/pfblockerng_threats.php \
		${STAGEDIR}${PREFIX}/www/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/widgets/widgets/pfblockerng.widget.php \
		${STAGEDIR}${PREFIX}/www/widgets/widgets
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/widgets/include/widget-pfblockerng.inc \
		${STAGEDIR}${PREFIX}/www/widgets/include
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/widgets/javascript/pfblockerng.js \
		${STAGEDIR}${PREFIX}/www/widgets/javascript
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/pfblockerng/dnsbl_tld \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/pfblockerng/pfblockerng_dnsbl.xml \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/pfblockerng/pfblockerng_dnsbl_lists.xml \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/pfblockerng/pfblockerng_dnsbl_easylist.xml \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/pfblockerng/pfblockerng_v4lists.xml \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/pfblockerng/pfblockerng_v6lists.xml \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/pfblockerng/pfblockerng_sync.xml \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_DATA} -m 0755 ${FILESDIR}${PREFIX}/pkg/pfblockerng/pfblockerng.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_DATA} -m 0755 ${FILESDIR}${PREFIX}/www/pfblockerng/www/index.php \
		${STAGEDIR}${PREFIX}/www/pfblockerng/www
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
