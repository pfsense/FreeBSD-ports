# $FreeBSD$

MASTERDIR=	${.CURDIR}/../pfSense-pkg-zabbix-agent

PKGNAMESUFFIX=	32

CONFLICTS=	pfSense-pkg-zabbix-agent \
		pfSense-pkg-zabbix-agent22 \
		pfSense-pkg-zabbix-agent34

RUN_DEPENDS=	zabbix_agentd:net-mgmt/zabbix32-agent

ZABBIXUNIQNAME=	zabbixagent32
ZABBIXTITLE=	Zabbix Agent 3.2

.include "${MASTERDIR}/Makefile"
