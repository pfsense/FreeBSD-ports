# $FreeBSD$

PORTNAME=	pfSense-pkg-suricata
PORTVERSION=	3.0
PORTREVISION=	12
CATEGORIES=	security
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense package suricata

LICENSE=	APACHE20

RUN_DEPENDS=	${LOCALBASE}/bin/suricata:security/suricata \
		${LOCALBASE}/bin/barnyard2:security/barnyard2

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/suricata
	${MKDIR} ${STAGEDIR}${PREFIX}/www/suricata
	${MKDIR} ${STAGEDIR}${PREFIX}/www/widgets/javascript
	${MKDIR} ${STAGEDIR}${PREFIX}/www/widgets/widgets
	${MKDIR} ${STAGEDIR}${PREFIX}/www/widgets/include
	${MKDIR} ${STAGEDIR}/var/db/suricata/sidmods
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/suricata.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/suricata.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/suricata/suricata.inc \
		${STAGEDIR}${PREFIX}/pkg/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/suricata/suricata_sync.xml \
		${STAGEDIR}${PREFIX}/pkg/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/suricata/suricata_check_cron_misc.inc \
		${STAGEDIR}${PREFIX}/pkg/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/suricata/suricata_check_for_rule_updates.php \
		${STAGEDIR}${PREFIX}/pkg/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/suricata/suricata_yaml_template.inc \
		${STAGEDIR}${PREFIX}/pkg/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/suricata/suricata_generate_yaml.php \
		${STAGEDIR}${PREFIX}/pkg/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/suricata/suricata_migrate_config.php \
		${STAGEDIR}${PREFIX}/pkg/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/suricata/suricata_post_install.php \
		${STAGEDIR}${PREFIX}/pkg/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/suricata/suricata_uninstall.php \
		${STAGEDIR}${PREFIX}/pkg/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/suricata/suricata_defs.inc \
		${STAGEDIR}${PREFIX}/pkg/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/suricata/suricata_geoipupdate.php \
		${STAGEDIR}${PREFIX}/pkg/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/suricata/suricata_etiqrisk_update.php \
		${STAGEDIR}${PREFIX}/pkg/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/suricata/deprecated_rules \
		${STAGEDIR}${PREFIX}/pkg/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_download_updates.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_global.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_alerts.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_interfaces.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_interfaces_edit.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_download_rules.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_rules.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_rulesets.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_rules_flowbits.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_rules_edit.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_flow_stream.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_os_policy_engine.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_import_aliases.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_suppress.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_suppress_edit.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_logs_browser.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_logs_mgmt.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_sid_mgmt.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_list_view.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_app_parsers.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_libhtp_policy_engine.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_define_vars.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_barnyard.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_ip_list_mgmt.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_ip_reputation.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_iprep_list_browser.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/widgets/javascript/suricata_alerts.js \
		${STAGEDIR}${PREFIX}/www/widgets/javascript
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/widgets/widgets/suricata_alerts.widget.php \
		${STAGEDIR}${PREFIX}/www/widgets/widgets
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/widgets/include/widget-suricata.inc \
		${STAGEDIR}${PREFIX}/www/widgets/include
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_blocked.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_passlist.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_passlist_edit.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/suricata/suricata_select_alias.php \
		${STAGEDIR}${PREFIX}/www/suricata
	${INSTALL_DATA} ${FILESDIR}/var/db/suricata/sidmods/disablesid-sample.conf \
		${STAGEDIR}/var/db/suricata/sidmods
	${INSTALL_DATA} ${FILESDIR}/var/db/suricata/sidmods/enablesid-sample.conf \
		${STAGEDIR}/var/db/suricata/sidmods
	${INSTALL_DATA} ${FILESDIR}/var/db/suricata/sidmods/modifysid-sample.conf \
		${STAGEDIR}/var/db/suricata/sidmods
	${INSTALL_DATA} ${FILESDIR}/var/db/suricata/sidmods/dropsid-sample.conf \
		${STAGEDIR}/var/db/suricata/sidmods
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
