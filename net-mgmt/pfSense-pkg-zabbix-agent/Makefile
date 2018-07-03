# $FreeBSD$

PORTNAME?=	pfSense-pkg-zabbix-agent
PORTVERSION=	1.0.1
CATEGORIES=	net-mgmt
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense package zabbix-agent

LICENSE=	APACHE20

CONFLICTS?=	pfSense-pkg-zabbix-agent22 \
		pfSense-pkg-zabbix-agent32 \
		pfSense-pkg-zabbix-agent34

RUN_DEPENDS?=	zabbix_agentd:net-mgmt/zabbix3-agent

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

ZABBIXINTERNALNAME?=	zabbix-agent
ZABBIXTITLE?=	Zabbix Agent 3.0

# Same for all Zabbix Agent ports to share their configs
# zabbixagentlts for keep compatibility with current configs
ZABBIXUNIQNAME?=	zabbixagentlts

ZABBIXVERSION?=	30

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/zabbix-agent.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/zabbix-agent.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/zabbix-agent.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}/usr/local/share/pfSense-pkg-zabbix-agent/info.xml \
		${STAGEDIR}${DATADIR}
	${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		-e "s|%%ZABBIXVERSION%%|${ZABBIXVERSION}|" \
		-e "s|%%ZABBIXUNIQNAME%%|${ZABBIXUNIQNAME}|" \
		-e "s|%%ZABBIXTITLE%%|${ZABBIXTITLE}|" \
		-e "s|%%ZABBIXINTERNALNAME%%|${ZABBIXINTERNALNAME}|" \
		${STAGEDIR}${DATADIR}/info.xml \
		${STAGEDIR}${PREFIX}/pkg/zabbix-agent.inc \
		${STAGEDIR}${PREFIX}/pkg/zabbix-agent.xml

.include <bsd.port.mk>
