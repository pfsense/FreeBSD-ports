# $FreeBSD$

MASTERDIR=	${.CURDIR}/../pfSense-pkg-zabbix-proxy

PKGNAMESUFFIX=	32

CONFLICTS=	pfSense-pkg-zabbix-proxy \
		pfSense-pkg-zabbix-proxy22 \
		pfSense-pkg-zabbix-proxy34

RUN_DEPENDS=	zabbix_proxy:net-mgmt/zabbix32-proxy

ZABBIXUNIQNAME=	zabbixproxy32
ZABBIXTITLE=	Zabbix Proxy 3.2

.include "${MASTERDIR}/Makefile"
