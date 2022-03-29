# $FreeBSD

MASTERDIR=	${.CURDIR}/../pfSense-pkg-zabbix-agent

PORTNAME=	pfSense-pkg-zabbix-agent6

CONFLICTS=	pfSense-pkg-zabbix-agent \
		pfSense-pkg-zabbix-agent4 \
		pfSense-pkg-zabbix-agent44 \
		pfSense-pkg-zabbix-agent5 \
		pfSense-pkg-zabbix-agent54

RUN_DEPENDS=	zabbix_agentd:net-mgmt/zabbix6-agent

ZABBIXINTERNALNAME=	zabbix-agent6
ZABBIXTITLE=	Zabbix Agent 6
ZABBIXVERSION=	6

.include "${MASTERDIR}/Makefile"
