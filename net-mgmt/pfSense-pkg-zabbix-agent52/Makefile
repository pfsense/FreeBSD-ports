# $FreeBSD

MASTERDIR=	${.CURDIR}/../pfSense-pkg-zabbix-agent

PORTNAME=	pfSense-pkg-zabbix-agent52

CONFLICTS=	pfSense-pkg-zabbix-agent \
		pfSense-pkg-zabbix-agent4 \
		pfSense-pkg-zabbix-agent44 \
		pfSense-pkg-zabbix-agent5

RUN_DEPENDS=	zabbix_agentd:net-mgmt/zabbix52-agent

ZABBIXINTERNALNAME=	zabbix-agent52
ZABBIXTITLE=	Zabbix Agent 5.2
ZABBIXVERSION=	52

.include "${MASTERDIR}/Makefile"
