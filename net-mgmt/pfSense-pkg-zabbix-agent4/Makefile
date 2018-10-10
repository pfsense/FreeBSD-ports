# $FreeBSD$

MASTERDIR=	${.CURDIR}/../pfSense-pkg-zabbix-agent

PORTNAME=	pfSense-pkg-zabbix-agent4

CONFLICTS=	pfSense-pkg-zabbix-agent \
		pfSense-pkg-zabbix-agent22 \
		pfSense-pkg-zabbix-agent32 \
		pfSense-pkg-zabbix-agent34

RUN_DEPENDS=	zabbix_agentd:net-mgmt/zabbix4-agent

ZABBIXINTERNALNAME=	zabbix-agent4
ZABBIXTITLE=	Zabbix Agent 4.0
ZABBIXVERSION=	40

.include "${MASTERDIR}/Makefile"
