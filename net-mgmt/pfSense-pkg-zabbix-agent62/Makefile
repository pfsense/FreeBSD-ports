# $FreeBSD

MASTERDIR=	${.CURDIR}/../pfSense-pkg-zabbix-agent

PORTNAME=	pfSense-pkg-zabbix-agent62

CONFLICTS=
CONFLICTS+=	pfSense-pkg-zabbix-agent
CONFLICTS+=	pfSense-pkg-zabbix-agent4
CONFLICTS+=	pfSense-pkg-zabbix-agent44
CONFLICTS+=	pfSense-pkg-zabbix-agent5
CONFLICTS+=	pfSense-pkg-zabbix-agent54
CONFLICTS+=	pfSense-pkg-zabbix-agent6

RUN_DEPENDS=	zabbix_agentd:net-mgmt/zabbix62-agent

ZABBIXINTERNALNAME=	zabbix-agent6
ZABBIXTITLE=	Zabbix Agent 6.2
ZABBIXVERSION=	62

.include "${MASTERDIR}/Makefile"
