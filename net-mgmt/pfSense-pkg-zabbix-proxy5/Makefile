# $FreeBSD$

MASTERDIR=	${.CURDIR}/../pfSense-pkg-zabbix-proxy

PORTNAME=	pfSense-pkg-zabbix-proxy5

CONFLICTS=	pfSense-pkg-zabbix-proxy \
		pfSense-pkg-zabbix-proxy4 \
		pfSense-pkg-zabbix-proxy44 \
		pfSense-pkg-zabbix-proxy54 \
		pfSense-pkg-zabbix-proxy6

RUN_DEPENDS=	zabbix_proxy:net-mgmt/zabbix5-proxy

ZABBIXINTERNALNAME=	zabbix-proxy5
ZABBIXTITLE=	Zabbix Proxy 5.0
ZABBIXVERSION=	50

.include "${MASTERDIR}/Makefile"
