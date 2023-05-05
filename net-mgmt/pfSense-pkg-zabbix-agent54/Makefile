# $FreeBSD

MASTERDIR=	${.CURDIR}/../pfSense-pkg-zabbix-agent

PORTNAME=	pfSense-pkg-zabbix-agent54

CONFLICTS=	pfSense-pkg-zabbix-agent \
		pfSense-pkg-zabbix-agent4 \
		pfSense-pkg-zabbix-agent44 \
		pfSense-pkg-zabbix-agent5 \
		pfSense-pkg-zabbix-agent6 \
		pfSense-pkg-zabbix-agent62 \
		pfSense-pkg-zabbix-agent64

RUN_DEPENDS=	zabbix_agentd:net-mgmt/zabbix54-agent

ZABBIXINTERNALNAME=	zabbix-agent54
ZABBIXTITLE=	Zabbix Agent 5.4
ZABBIXVERSION=	54

.include "${MASTERDIR}/Makefile"
