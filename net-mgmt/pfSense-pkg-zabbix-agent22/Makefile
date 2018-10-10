# $FreeBSD$

MASTERDIR=	${.CURDIR}/../pfSense-pkg-zabbix-agent

PORTNAME=	pfSense-pkg-zabbix-agent${ZABBIXVERSION}

CONFLICTS=	pfSense-pkg-zabbix-agent \
		pfSense-pkg-zabbix-agent32 \
		pfSense-pkg-zabbix-agent34 \
		pfSense-pkg-zabbix-agent4

RUN_DEPENDS=	zabbix_agentd:net-mgmt/zabbix${ZABBIXVERSION}-agent

ZABBIXINTERNALNAME=	zabbix-agent${ZABBIXVERSION}
ZABBIXTITLE=	Zabbix Agent 2.2
ZABBIXVERSION=	22

.include "${MASTERDIR}/Makefile"
