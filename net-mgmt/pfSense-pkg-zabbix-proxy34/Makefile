# $FreeBSD$

MASTERDIR=	${.CURDIR}/../pfSense-pkg-zabbix-proxy

PKGNAMESUFFIX=	34

CONFLICTS=	pfSense-pkg-zabbix-proxy \
		pfSense-pkg-zabbix-proxy22 \
		pfSense-pkg-zabbix-proxy32

RUN_DEPENDS=	zabbix_proxy:net-mgmt/zabbix34-proxy

ZABBIXUNIQNAME=	zabbixproxy34
ZABBIXTITLE=	Zabbix Proxy 3.4

.include "${MASTERDIR}/Makefile"
