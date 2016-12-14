# $FreeBSD$

PORTNAME=	pfSense-pkg-snort
PORTVERSION=	3.2.9.2
PORTREVISION=	15
CATEGORIES=	security
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense package snort

LICENSE=	APACHE20

RUN_DEPENDS=	${LOCALBASE}/bin/snort:security/snort \
		${LOCALBASE}/bin/barnyard2:security/barnyard2

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/snort
	${MKDIR} ${STAGEDIR}${PREFIX}/www/snort
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${PREFIX}/www/widgets/javascript
	${MKDIR} ${STAGEDIR}${PREFIX}/www/widgets/widgets
	${MKDIR} ${STAGEDIR}${PREFIX}/www/widgets/include
	${MKDIR} ${STAGEDIR}/var/db/snort/sidmods
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/snort.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/snort/snort.inc \
		${STAGEDIR}${PREFIX}/pkg/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/snort/snort_check_cron_misc.inc \
		${STAGEDIR}${PREFIX}/pkg/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/snort/snort_conf_template.inc \
		${STAGEDIR}${PREFIX}/pkg/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/snort/snort_migrate_config.php \
		${STAGEDIR}${PREFIX}/pkg/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/snort/snort_post_install.php \
		${STAGEDIR}${PREFIX}/pkg/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/snort/snort_uninstall.php \
		${STAGEDIR}${PREFIX}/pkg/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/snort/snort_sync.xml \
		${STAGEDIR}${PREFIX}/pkg/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/snort/deprecated_rules \
		${STAGEDIR}${PREFIX}/pkg/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_alerts.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_barnyard.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_blocked.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_define_servers.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_download_rules.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_download_updates.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/snort/snort_check_for_rule_updates.php \
		${STAGEDIR}${PREFIX}/pkg/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/snort/snort_defs.inc \
		${STAGEDIR}${PREFIX}/pkg/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_interfaces.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_interfaces_edit.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_interfaces_global.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_rules.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_rules_edit.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_rulesets.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_preprocessors.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_passlist.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_passlist_edit.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_interfaces_suppress.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_interfaces_suppress_edit.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_list_view.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_rules_flowbits.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_edit_hat_data.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_frag3_engine.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_stream5_engine.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_httpinspect_engine.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_ftp_client_engine.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_ftp_server_engine.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_import_aliases.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_select_alias.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/snort.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_ip_reputation.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_ip_list_mgmt.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_iprep_list_browser.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_interface_logs.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_log_mgmt.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/snort/snort_sid_mgmt.php \
		${STAGEDIR}${PREFIX}/www/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/snort/snort_generate_conf.php \
		${STAGEDIR}${PREFIX}/pkg/snort
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/widgets/javascript/snort_alerts.js \
		${STAGEDIR}${PREFIX}/www/widgets/javascript
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/widgets/widgets/snort_alerts.widget.php \
		${STAGEDIR}${PREFIX}/www/widgets/widgets
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/widgets/include/widget-snort.inc \
		${STAGEDIR}${PREFIX}/www/widgets/include
	${INSTALL_DATA} ${FILESDIR}/var/db/snort/sidmods/disablesid-sample.conf \
		${STAGEDIR}/var/db/snort/sidmods
	${INSTALL_DATA} ${FILESDIR}/var/db/snort/sidmods/enablesid-sample.conf \
		${STAGEDIR}/var/db/snort/sidmods
	${INSTALL_DATA} ${FILESDIR}/var/db/snort/sidmods/modifysid-sample.conf \
		${STAGEDIR}/var/db/snort/sidmods
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml \
		${STAGEDIR}${PREFIX}/pkg/snort/snort_sync.xml \
		${STAGEDIR}${PREFIX}/pkg/snort.xml

.include <bsd.port.mk>
