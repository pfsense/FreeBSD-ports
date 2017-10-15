# $FreeBSD$

MASTERDIR=	${.CURDIR}/../pfSense-pkg-zabbix-proxy

PKGNAMESUFFIX=	22

CONFLICTS=	pfSense-pkg-zabbix-proxy \
		pfSense-pkg-zabbix-proxy32 \
		pfSense-pkg-zabbix-proxy34

RUN_DEPENDS=	zabbix_proxy:net-mgmt/zabbix22-proxy

ZABBIXUNIQNAME=	zabbixproxy22
ZABBIXTITLE=	Zabbix Proxy 2.2

.include "${MASTERDIR}/Makefile"
