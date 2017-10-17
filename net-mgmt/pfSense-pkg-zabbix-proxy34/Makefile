# $FreeBSD$

MASTERDIR=	${.CURDIR}/../pfSense-pkg-zabbix-proxy

PORTNAME=	pfSense-pkg-zabbix-proxy${ZABBIXVERSION}

CONFLICTS=	pfSense-pkg-zabbix-proxy \
		pfSense-pkg-zabbix-proxy22 \
		pfSense-pkg-zabbix-proxy32

RUN_DEPENDS=	zabbix_proxy:net-mgmt/zabbix${ZABBIXVERSION}-proxy

ZABBIXINTERNALNAME=	zabbix-proxy${ZABBIXVERSION}
ZABBIXTITLE=	Zabbix Proxy 3.4
ZABBIXVERSION=	34	

.include "${MASTERDIR}/Makefile"
