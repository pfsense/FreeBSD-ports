# $FreeBSD$

PORTNAME=	pfSense-pkg-zabbix-proxy
PORTVERSION=	1.0.0
CATEGORIES=	net-mgmt
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty
PKGNAMESUFFIX?=	30

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

ZABBIXUNIQNAME?=	zabbixproxylts
ZABBIXTITLE?=	Zabbix Proxy LTS

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
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|"  \
		-e "s|%%ZABBIXVERSION%%|${PKGNAMESUFFIX}|" \
		-e "s|%%ZABBIXUNIQNAME%%|${ZABBIXUNIQNAME}|" \
		-e "s|%%ZABBIXTITLE%%|${ZABBIXTITLE}|" \
		${STAGEDIR}${DATADIR}/info.xml \
		${STAGEDIR}${PREFIX}/pkg/zabbix-proxy.inc \
		${STAGEDIR}${PREFIX}/pkg/zabbix-proxy.xml
		
.include <bsd.port.mk>
