# $FreeBSD$

MASTERDIR=	${.CURDIR}/../pfSense-pkg-zabbix-agent

PKGNAMESUFFIX=	34

CONFLICTS=	pfSense-pkg-zabbix-agent \
		pfSense-pkg-zabbix-agent22 \
		pfSense-pkg-zabbix-agent32

RUN_DEPENDS=	zabbix_agentd:net-mgmt/zabbix34-agent

ZABBIXUNIQNAME=	zabbixagent34
ZABBIXTITLE=	Zabbix Agent 3.4

.include "${MASTERDIR}/Makefile"
