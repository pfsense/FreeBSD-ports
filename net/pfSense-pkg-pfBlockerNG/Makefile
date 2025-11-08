PORTNAME=	pfSense-pkg-pfBlockerNG
PORTVERSION=	3.2.9
PORTREVISION=	3
CATEGORIES=	net
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense package pfBlockerNG

LICENSE=	APACHE20

RUN_DEPENDS=	${LOCALBASE}/bin/mmdblookup:net/libmaxminddb \
		${LOCALBASE}/bin/ggrep:textproc/gnugrep \
		${LOCALBASE}/bin/grepcidr:net-mgmt/grepcidr \
		${LOCALBASE}/bin/iprange:net-mgmt/iprange \
		${LOCALBASE}/bin/jq:textproc/jq \
		${LOCALBASE}/bin/rsync:net/rsync \
		${LOCALBASE}/sbin/lighttpd:www/lighttpd \
		${PYTHON_PKGNAMEPREFIX}sqlite3>0:databases/py-sqlite3@${PY_FLAVOR} \
		${PYTHON_PKGNAMEPREFIX}maxminddb>0:net/py-maxminddb@${PY_FLAVOR}

USES=		php python

CONFLICTS=	pfSense-pkg-pfBlockerNG-devel

USE_PHP=	intl
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
	${MKDIR} ${STAGEDIR}${PREFIX}/www/wizards
	${MKDIR} ${STAGEDIR}/var/unbound
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
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/ip_pre_AWS_AF.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/ip_pre_AWS_ALL_REGIONS.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/ip_pre_AWS_AP.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/ip_pre_AWS_AP_EAST.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/ip_pre_AWS_AP_NORTHEAST.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/ip_pre_AWS_AP_SOUTH.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/ip_pre_AWS_AP_SOUTHEAST.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/ip_pre_AWS_CA.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/ip_pre_AWS_CN.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/ip_pre_AWS_CN_NORTH.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/ip_pre_AWS_CN_NORTHWEST.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/ip_pre_AWS_EU.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/ip_pre_AWS_EU_CENTRAL.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/ip_pre_AWS_EU_NORTH.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/ip_pre_AWS_EU_SOUTH.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/ip_pre_AWS_EU_WEST.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/ip_pre_AWS_IL.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/ip_pre_AWS_ME.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/ip_pre_AWS_ME_CENTRAL.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/ip_pre_AWS_ME_SOUTH.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/ip_pre_AWS_SA.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/ip_pre_AWS_US.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/ip_pre_AWS_US_EAST.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/ip_pre_AWS_US_GOV.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/ip_pre_AWS_US_WEST.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/pfblockerng/pfBlockerNG.js \
		${STAGEDIR}${PREFIX}/www/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/pfblockerng/pfblockerng_asn.txt \
		${STAGEDIR}${PREFIX}/www/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/pfblockerng/pfblockerng.php \
		${STAGEDIR}${PREFIX}/www/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/pfblockerng/pfblockerng_alerts.php \
		${STAGEDIR}${PREFIX}/www/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/pfblockerng/pfblockerng_blacklist.php \
		${STAGEDIR}${PREFIX}/www/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/pfblockerng/pfblockerng_category.php \
		${STAGEDIR}${PREFIX}/www/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/pfblockerng/pfblockerng_category_edit.php \
		${STAGEDIR}${PREFIX}/www/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/pfblockerng/pfblockerng_dnsbl.php \
		${STAGEDIR}${PREFIX}/www/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/pfblockerng/pfblockerng_feeds.json \
		${STAGEDIR}${PREFIX}/www/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/pfblockerng/pfblockerng_feeds.php \
		${STAGEDIR}${PREFIX}/www/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/pfblockerng/pfblockerng_general.php \
		${STAGEDIR}${PREFIX}/www/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/pfblockerng/pfblockerng_ip.php \
		${STAGEDIR}${PREFIX}/www/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/pfblockerng/pfblockerng_update.php \
		${STAGEDIR}${PREFIX}/www/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/pfblockerng/pfblockerng_sync.php \
		${STAGEDIR}${PREFIX}/www/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/pfblockerng/pfblockerng_safesearch.php \
		${STAGEDIR}${PREFIX}/www/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/pfblockerng/pfblockerng_threats.php \
		${STAGEDIR}${PREFIX}/www/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/pfblockerng/pfblockerng_log.php \
		${STAGEDIR}${PREFIX}/www/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/widgets/widgets/pfblockerng.widget.php \
		${STAGEDIR}${PREFIX}/www/widgets/widgets
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/widgets/include/widget-pfblockerng.inc \
		${STAGEDIR}${PREFIX}/www/widgets/include
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/widgets/javascript/pfblockerng.js \
		${STAGEDIR}${PREFIX}/www/widgets/javascript
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/wizards/pfblockerng_wizard.xml \
		${STAGEDIR}${PREFIX}/www/wizards/pfblockerng_wizard.xml
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/wizards/pfblockerng_wizard.inc \
		${STAGEDIR}${PREFIX}/www/wizards/pfblockerng_wizard.inc
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/pfblockerng/dnsbl_tld \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/pfblockerng/shallalist_global_usage \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/pfblockerng/ut1_global_usage \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/pfblockerng/pfb_dnsbl.doh.conf \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/pfblockerng/pfb_dnsbl.safesearch.conf \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/pfblockerng/pfb_dnsbl.youtube_restrict.conf \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/pfblockerng/pfb_dnsbl.youtube_restrictmoderate.conf \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/pfblockerng.sh \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/pfb_unbound.py \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/pfblockerng/pfb_unbound_include.inc \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/pfblockerng/pfb_py_hsts.txt \
		${STAGEDIR}${PREFIX}/pkg/pfblockerng
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/www/pfblockerng/www/index.php \
		${STAGEDIR}${PREFIX}/www/pfblockerng/www
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/www/pfblockerng/www/dnsbl_default.php \
		${STAGEDIR}${PREFIX}/www/pfblockerng/www
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
