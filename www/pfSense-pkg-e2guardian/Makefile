# $FreeBSD$

PORTNAME=	pfSense-pkg-E2guardian5
PORTVERSION=	0.5.3.2
PORTREVISION=   1
CATEGORIES=	www
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense E2guardian package

LICENSE=	APACHE20

RUN_DEPENDS=	${LOCALBASE}/sbin/e2guardian:www/e2guardian53 \
		/usr/local/sbin/clamd:security/clamav \
		/usr/local/share/certs/ca-root-nss.crt:security/ca_root_nss

CONFLITS_WITH=	www/e2guardian5

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/e2guardian
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/e2guardian/templates
	${MKDIR} ${STAGEDIR}${PREFIX}/www
	${MKDIR} ${STAGEDIR}${PREFIX}/etc/e2guardian
	${MKDIR} ${STAGEDIR}${PREFIX}/www/e2guardian
	${MKDIR} ${STAGEDIR}${PREFIX}/www/shortcuts
	${MKDIR} ${STAGEDIR}${PREFIX}/www/widgets/widgets
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}/root
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/e2guardian.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/e2guardian.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/e2guardian/e2guardian_antivirus_acl.xml \
		${STAGEDIR}${PREFIX}/pkg/e2guardian
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/e2guardian/e2guardian_blacklist.xml \
		${STAGEDIR}${PREFIX}/pkg/e2guardian
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/e2guardian/e2guardian_config.xml \
		${STAGEDIR}${PREFIX}/pkg/e2guardian
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/e2guardian/e2guardian_content_acl.xml \
		${STAGEDIR}${PREFIX}/pkg/e2guardian
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/e2guardian/e2guardian_file_acl.xml \
		${STAGEDIR}${PREFIX}/pkg/e2guardian
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/e2guardian/e2guardian_groups.xml \
		${STAGEDIR}${PREFIX}/pkg/e2guardian
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/e2guardian/e2guardian_header_acl.xml \
		${STAGEDIR}${PREFIX}/pkg/e2guardian
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/e2guardian/e2guardian_ldap.xml \
		${STAGEDIR}${PREFIX}/pkg/e2guardian
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/e2guardian/e2guardian_limits.xml \
		${STAGEDIR}${PREFIX}/pkg/e2guardian
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/e2guardian/e2guardian_log.xml \
		${STAGEDIR}${PREFIX}/pkg/e2guardian
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/e2guardian/e2guardian_phrase_acl.xml \
		${STAGEDIR}${PREFIX}/pkg/e2guardian
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/e2guardian/e2guardian_search_acl.xml \
		${STAGEDIR}${PREFIX}/pkg/e2guardian
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/e2guardian/e2guardian_sync.xml \
		${STAGEDIR}${PREFIX}/pkg/e2guardian
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/e2guardian/e2guardian_site_acl.xml \
		${STAGEDIR}${PREFIX}/pkg/e2guardian
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/e2guardian/e2guardian_url_acl.xml \
		${STAGEDIR}${PREFIX}/pkg/e2guardian
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/e2guardian/e2guardian_ips.xml \
		${STAGEDIR}${PREFIX}/pkg/e2guardian
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/e2guardian/e2guardian_users.xml \
		${STAGEDIR}${PREFIX}/pkg/e2guardian
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/e2guardian/e2guardian.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/e2guardian/templates/e2guardian.conf.template \
		${STAGEDIR}${PREFIX}/pkg/e2guardian/templates
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/e2guardian/templates/e2guardian_ips_header.template \
		${STAGEDIR}${PREFIX}/pkg/e2guardian/templates
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/e2guardian/templates/e2guardian_rc.template \
		${STAGEDIR}${PREFIX}/pkg/e2guardian/templates
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/e2guardian/templates/e2guardian_users_footer.template \
		${STAGEDIR}${PREFIX}/pkg/e2guardian/templates
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/e2guardian/templates/e2guardian_users_header.template \
		${STAGEDIR}${PREFIX}/pkg/e2guardian/templates
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/e2guardian/templates/e2guardian_story.template \
		${STAGEDIR}${PREFIX}/pkg/e2guardian/templates
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/e2guardian/templates/e2guardianfx.conf.template \
		${STAGEDIR}${PREFIX}/pkg/e2guardian/templates
	${INSTALL_DATA} -m 0755 ${FILESDIR}${PREFIX}/pkg/e2guardian/e2guardian.php \
		${STAGEDIR}${PREFIX}/pkg/e2guardian
	${INSTALL_DATA} -m 0755 ${FILESDIR}${PREFIX}/pkg/e2guardian/e2gerror.php \
		${STAGEDIR}${PREFIX}/pkg/e2guardian
	${INSTALL_DATA} -m 0755 ${FILESDIR}${PREFIX}/pkg/e2guardian/e2guardian_monitor_data.php \
		${STAGEDIR}${PREFIX}/pkg/e2guardian
	${INSTALL_DATA} -m 0755 ${FILESDIR}${PREFIX}/pkg/e2guardian/e2guardian_ldap.php \
		${STAGEDIR}${PREFIX}/pkg/e2guardian
	${INSTALL_DATA} -m 0755 ${FILESDIR}${PREFIX}/pkg/e2guardian/e2guardian_log_parser.php \
		${STAGEDIR}${PREFIX}/pkg/e2guardian
	${INSTALL_DATA} -m 0755 ${FILESDIR}${PREFIX}/pkg/e2guardian/e2guardian_scheds.php \
		${STAGEDIR}${PREFIX}/pkg/e2guardian
	${INSTALL_DATA} -m 0755 ${FILESDIR}${PREFIX}/pkg/e2guardian/e2guardian_logrotate.php \
		${STAGEDIR}${PREFIX}/pkg/e2guardian
	${INSTALL_DATA} -m 0755 ${FILESDIR}${PREFIX}/www/e2guardian/e2guardian_about.php \
		${STAGEDIR}${PREFIX}/www/e2guardian
	${INSTALL_DATA} -m 0755 ${FILESDIR}${PREFIX}/www/e2guardian/e2guardian_monitor.php \
		${STAGEDIR}${PREFIX}/www/e2guardian
	${INSTALL_DATA} -m 0755 ${FILESDIR}${PREFIX}/etc/e2guardian/squidparent.conf \
		${STAGEDIR}${PREFIX}/etc/e2guardian
	${INSTALL_DATA} -m 0755 ${FILESDIR}${PREFIX}/www/widgets/widgets/e2guardian.widget.php \
		${STAGEDIR}${PREFIX}/www/widgets/widgets
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/www/shortcuts/pkg_e2guardian.inc \
		${STAGEDIR}${PREFIX}/www/shortcuts
	${INSTALL_DATA} -m 0644 ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}/usr/local/pkg/e2guardian.xml
.include <bsd.port.mk>

