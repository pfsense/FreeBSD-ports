# $FreeBSD$

MASTERDIR=	${.CURDIR}/../pfSense-pkg-zabbix-proxy

PORTNAME=	pfSense-pkg-zabbix-proxy64

CONFLICTS=
CONFLICTS+=	pfSense-pkg-zabbix-proxy
CONFLICTS+=	pfSense-pkg-zabbix-proxy4
CONFLICTS+=	pfSense-pkg-zabbix-proxy44
CONFLICTS+=	pfSense-pkg-zabbix-proxy5
CONFLICTS+=	pfSense-pkg-zabbix-proxy54
CONFLICTS+=	pfSense-pkg-zabbix-proxy6

RUN_DEPENDS=	zabbix_proxy:net-mgmt/zabbix64-proxy

ZABBIXINTERNALNAME=	zabbix-proxy64
ZABBIXTITLE=	Zabbix Proxy 6.4
ZABBIXVERSION=	64

.include "${MASTERDIR}/Makefile"
