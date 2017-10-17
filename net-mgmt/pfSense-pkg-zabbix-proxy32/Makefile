# $FreeBSD$

MASTERDIR=	${.CURDIR}/../pfSense-pkg-zabbix-proxy

PORTNAME=	pfSense-pkg-zabbix-proxy${ZABBIXVERSION}

CONFLICTS=	pfSense-pkg-zabbix-proxy \
		pfSense-pkg-zabbix-proxy22 \
		pfSense-pkg-zabbix-proxy34

RUN_DEPENDS=	zabbix_proxy:net-mgmt/zabbix${ZABBIXVERSION}-proxy

ZABBIXINTERNALNAME=	zabbix-proxy${ZABBIXVERSION}
ZABBIXTITLE=	Zabbix Proxy 3.2
ZABBIXVERSION=	32	

.include "${MASTERDIR}/Makefile"
