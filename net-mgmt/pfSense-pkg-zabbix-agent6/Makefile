# $FreeBSD

MASTERDIR=	${.CURDIR}/../pfSense-pkg-zabbix-agent

PORTNAME=	pfSense-pkg-zabbix-agent6

CONFLICTS=	pfSense-pkg-zabbix-agent
CONFLICTS+=	pfSense-pkg-zabbix-agent7
CONFLICTS+=	pfSense-pkg-zabbix-agent74

RUN_DEPENDS=	zabbix_agentd:net-mgmt/zabbix6-agent

ZABBIXINTERNALNAME=	zabbix-agent6
ZABBIXTITLE=	Zabbix Agent 6
ZABBIXVERSION=	6

.include "${MASTERDIR}/Makefile"
