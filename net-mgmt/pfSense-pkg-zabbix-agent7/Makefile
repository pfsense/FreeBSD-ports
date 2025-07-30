# $FreeBSD

MASTERDIR=	${.CURDIR}/../pfSense-pkg-zabbix-agent

PORTNAME=	pfSense-pkg-zabbix-agent7

CONFLICTS=
CONFLICTS+=	pfSense-pkg-zabbix-agent
CONFLICTS+=	pfSense-pkg-zabbix-agent4
CONFLICTS+=	pfSense-pkg-zabbix-agent44
CONFLICTS+=	pfSense-pkg-zabbix-agent5
CONFLICTS+=	pfSense-pkg-zabbix-agent54
CONFLICTS+=	pfSense-pkg-zabbix-agent6

RUN_DEPENDS=	zabbix_agentd:net-mgmt/zabbix7-agent

ZABBIXINTERNALNAME=	zabbix-agent7
ZABBIXTITLE=	Zabbix Agent 7
ZABBIXVERSION=	7

PORTREVISION=	1

.include "${MASTERDIR}/Makefile"
