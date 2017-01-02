# $FreeBSD$

PORTNAME=	pfSense-pkg-haproxy-devel
PORTVERSION=	0.52
PORTREVISION=	4
CATEGORIES=	net
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense package haproxy-devel

LICENSE=	ESF

RUN_DEPENDS=	${LOCALBASE}/sbin/haproxy:net/haproxy

CONFLICTS=	pfSense-pkg-haproxy-[0-9]*

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/haproxy
	${MKDIR} ${STAGEDIR}${PREFIX}/www
	${MKDIR} ${STAGEDIR}${PREFIX}/www/widgets/widgets
	${MKDIR} ${STAGEDIR}${PREFIX}/www/shortcuts
	${MKDIR} ${STAGEDIR}${PREFIX}/www/haproxy
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/haproxy.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/haproxy_rules.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/haproxy/haproxy.inc \
		${STAGEDIR}${PREFIX}/pkg/haproxy
	${INSTALL_DATA} -m 0755 ${FILESDIR}${PREFIX}/pkg/haproxy/haproxy_socket.sh \
		${STAGEDIR}${PREFIX}/pkg/haproxy
	${INSTALL_DATA} -m 0755 ${FILESDIR}${PREFIX}/pkg/haproxy/haproxy_sync.sh \
		${STAGEDIR}${PREFIX}/pkg/haproxy
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/haproxy/haproxy_gui.inc \
		${STAGEDIR}${PREFIX}/pkg/haproxy
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/haproxy/haproxy_socketinfo.inc \
		${STAGEDIR}${PREFIX}/pkg/haproxy
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/haproxy/haproxy_xmlrpcsyncclient.inc \
		${STAGEDIR}${PREFIX}/pkg/haproxy
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/haproxy/haproxy_htmllist.inc \
		${STAGEDIR}${PREFIX}/pkg/haproxy
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/haproxy/haproxy_utils.inc \
		${STAGEDIR}${PREFIX}/pkg/haproxy
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/haproxy/pkg_haproxy_tabs.inc \
		${STAGEDIR}${PREFIX}/pkg/haproxy
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/haproxy/haproxy_upgrade_config.inc \
		${STAGEDIR}${PREFIX}/pkg/haproxy
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/haproxy/haproxy_listeners.php \
		${STAGEDIR}${PREFIX}/www/haproxy
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/haproxy/haproxy_listeners_edit.php \
		${STAGEDIR}${PREFIX}/www/haproxy
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/haproxy/haproxy_global.php \
		${STAGEDIR}${PREFIX}/www/haproxy
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/haproxy/haproxy_files.php \
		${STAGEDIR}${PREFIX}/www/haproxy
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/haproxy/haproxy_pools.php \
		${STAGEDIR}${PREFIX}/www/haproxy
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/haproxy/haproxy_pool_edit.php \
		${STAGEDIR}${PREFIX}/www/haproxy
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/haproxy/haproxy_stats.php \
		${STAGEDIR}${PREFIX}/www/haproxy
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/haproxy/haproxy_templates.php \
		${STAGEDIR}${PREFIX}/www/haproxy
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/haproxy/haproxy_geturl.js \
		${STAGEDIR}${PREFIX}/www/haproxy
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/widgets/widgets/haproxy.widget.php \
		${STAGEDIR}${PREFIX}/www/widgets/widgets
	${INSTALL_DATA} -m 0755 ${FILESDIR}${PREFIX}/www/shortcuts/pkg_haproxy.inc \
		${STAGEDIR}${PREFIX}/www/shortcuts
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/haproxy.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
