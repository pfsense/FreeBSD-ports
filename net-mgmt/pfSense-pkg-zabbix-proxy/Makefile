# $FreeBSD$

PORTNAME?=	pfSense-pkg-zabbix-proxy
PORTVERSION=	1.0.1
CATEGORIES=	net-mgmt
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense package zabbix-proxy

LICENSE=	APACHE20

CONFLICTS?=	pfSense-pkg-zabbix-proxy22 \
		pfSense-pkg-zabbix-proxy32 \
		pfSense-pkg-zabbix-proxy34

RUN_DEPENDS?=	zabbix_proxy:net-mgmt/zabbix3-proxy

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

ZABBIXINTERNALNAME?=	zabbix-proxy
ZABBIXTITLE?=	Zabbix Proxy 3.0

# Same for all Zabbix Proxy ports to share their configs
# zabbixproxylts for keep compatibility with current configs
ZABBIXUNIQNAME?=	zabbixproxylts

ZABBIXVERSION?=	30

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/zabbix-proxy.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/zabbix-proxy.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/zabbix-proxy.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}/usr/local/share/pfSense-pkg-zabbix-proxy/info.xml \
		${STAGEDIR}${DATADIR}
	${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|"  \
		-e "s|%%ZABBIXVERSION%%|${ZABBIXVERSION}|" \
		-e "s|%%ZABBIXUNIQNAME%%|${ZABBIXUNIQNAME}|" \
		-e "s|%%ZABBIXTITLE%%|${ZABBIXTITLE}|" \
		-e "s|%%ZABBIXINTERNALNAME%%|${ZABBIXINTERNALNAME}|" \
		${STAGEDIR}${DATADIR}/info.xml \
		${STAGEDIR}${PREFIX}/pkg/zabbix-proxy.inc \
		${STAGEDIR}${PREFIX}/pkg/zabbix-proxy.xml
		
.include <bsd.port.mk>
