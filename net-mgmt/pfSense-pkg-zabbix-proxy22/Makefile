# $FreeBSD$

MASTERDIR=	${.CURDIR}/../pfSense-pkg-zabbix-proxy

PORTNAME=	pfSense-pkg-zabbix-proxy${ZABBIXVERSION}

CONFLICTS=	pfSense-pkg-zabbix-proxy \
		pfSense-pkg-zabbix-proxy4 \
		pfSense-pkg-zabbix-proxy42 \
		pfSense-pkg-zabbix-proxy44

RUN_DEPENDS=	zabbix_proxy:net-mgmt/zabbix${ZABBIXVERSION}-proxy

ZABBIXINTERNALNAME=	zabbix-proxy${ZABBIXVERSION}
ZABBIXTITLE=	Zabbix Proxy 2.2
ZABBIXVERSION=	22

.include "${MASTERDIR}/Makefile"
