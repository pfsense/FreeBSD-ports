# $FreeBSD

MASTERDIR=	${.CURDIR}/../pfSense-pkg-zabbix-agent

PORTNAME=	pfSense-pkg-zabbix-agent72

CONFLICTS=
CONFLICTS+=	pfSense-pkg-zabbix-agent
CONFLICTS+=	pfSense-pkg-zabbix-agent4
CONFLICTS+=	pfSense-pkg-zabbix-agent44
CONFLICTS+=	pfSense-pkg-zabbix-agent5
CONFLICTS+=	pfSense-pkg-zabbix-agent54
CONFLICTS+=	pfSense-pkg-zabbix-agent6
CONFLICTS+=	pfSense-pkg-zabbix-agent64
CONFLICTS+=	pfSense-pkg-zabbix-agent7

RUN_DEPENDS=	zabbix_agentd:net-mgmt/zabbix72-agent

ZABBIXINTERNALNAME=	zabbix-agent72
ZABBIXTITLE=	Zabbix Agent 7.2
ZABBIXVERSION=	72

PORTREVISION=	1

.include "${MASTERDIR}/Makefile"
