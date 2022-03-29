# $FreeBSD

MASTERDIR=	${.CURDIR}/../pfSense-pkg-zabbix-agent

PORTNAME=	pfSense-pkg-zabbix-agent5

CONFLICTS=	pfSense-pkg-zabbix-agent \
		pfSense-pkg-zabbix-agent4 \
		pfSense-pkg-zabbix-agent44 \
		pfSense-pkg-zabbix-agent54 \
		pfSense-pkg-zabbix-agent6

RUN_DEPENDS=	zabbix_agentd:net-mgmt/zabbix5-agent

ZABBIXINTERNALNAME=	zabbix-agent5
ZABBIXTITLE=	Zabbix Agent 5.0
ZABBIXVERSION=	50

.include "${MASTERDIR}/Makefile"
