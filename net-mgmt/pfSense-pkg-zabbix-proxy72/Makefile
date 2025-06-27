# $FreeBSD$

MASTERDIR=	${.CURDIR}/../pfSense-pkg-zabbix-proxy

PORTNAME=	pfSense-pkg-zabbix-proxy72

CONFLICTS=
CONFLICTS+=	pfSense-pkg-zabbix-proxy
CONFLICTS+=	pfSense-pkg-zabbix-proxy4
CONFLICTS+=	pfSense-pkg-zabbix-proxy44
CONFLICTS+=	pfSense-pkg-zabbix-proxy5
CONFLICTS+=	pfSense-pkg-zabbix-proxy54
CONFLICTS+=	pfSense-pkg-zabbix-proxy6
CONFLICTS+= pfSense-pkg-zabbix-agent64
CONFLICTS+=	pfSense-pkg-zabbix-proxy7

RUN_DEPENDS=	zabbix_proxy:net-mgmt/zabbix72-proxy

ZABBIXINTERNALNAME=	zabbix-proxy72
ZABBIXTITLE=	Zabbix Proxy 7.2
ZABBIXVERSION=	72

PORTREVISION=	1

.include "${MASTERDIR}/Makefile"
