# $FreeBSD$

MASTERDIR=	${.CURDIR}/../pfSense-pkg-zabbix-agent

PKGNAMESUFFIX=	22

CONFLICTS=	pfSense-pkg-zabbix-agent \
		pfSense-pkg-zabbix-agent32 \
		pfSense-pkg-zabbix-agent34

RUN_DEPENDS=	zabbix_agentd:net-mgmt/zabbix22-agent

ZABBIXUNIQNAME=	zabbixagent22
ZABBIXTITLE=	Zabbix Agent 2.2

.include "${MASTERDIR}/Makefile"
