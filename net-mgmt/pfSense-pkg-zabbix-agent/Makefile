# $FreeBSD$

PORTNAME=	pfSense-pkg-zabbix-agent
PORTVERSION=	0.8.9
PORTREVISION=	3
CATEGORIES=	net-mgmt
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty
PKGNAMESUFFIX?=	30

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

ZABBIXUNIQNAME?=	zabbixagentlts
ZABBIXTITLE?=	Zabbix Agent LTS

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
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		-e "s|%%ZABBIXVERSION%%|${PKGNAMESUFFIX}|" \
		-e "s|%%ZABBIXUNIQNAME%%|${ZABBIXUNIQNAME}|" \
		-e "s|%%ZABBIXTITLE%%|${ZABBIXTITLE}|" \
		${STAGEDIR}${DATADIR}/info.xml \
		${STAGEDIR}${PREFIX}/pkg/zabbix-agent.inc \
		${STAGEDIR}${PREFIX}/pkg/zabbix-agent.xml

.include <bsd.port.mk>
