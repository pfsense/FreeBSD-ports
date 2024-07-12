# $FreeBSD

MASTERDIR=	${.CURDIR}/../pfSense-pkg-zabbix-agent

PORTNAME=	pfSense-pkg-zabbix-agent64

CONFLICTS=
CONFLICTS+=	pfSense-pkg-zabbix-agent
CONFLICTS+=	pfSense-pkg-zabbix-agent4
CONFLICTS+=	pfSense-pkg-zabbix-agent44
CONFLICTS+=	pfSense-pkg-zabbix-agent5
CONFLICTS+=	pfSense-pkg-zabbix-agent54
CONFLICTS+=	pfSense-pkg-zabbix-agent6

RUN_DEPENDS=	zabbix_agentd:net-mgmt/zabbix64-agent

ZABBIXINTERNALNAME=	zabbix-agent64
ZABBIXTITLE=	Zabbix Agent 6.4
ZABBIXVERSION=	64

.include "${MASTERDIR}/Makefile"
