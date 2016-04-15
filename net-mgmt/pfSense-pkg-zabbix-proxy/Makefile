# $FreeBSD$

PORTNAME=	pfSense-pkg-zabbix-proxy
PORTVERSION=	0.8.9
PORTREVISION=	2
CATEGORIES=	net-mgmt
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense package zabbix-proxy

LICENSE=	ESF

RUN_DEPENDS=	${LOCALBASE}/sbin/zabbix_proxy:net-mgmt/zabbix22-proxy

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/zabbix-proxy-lts.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/zabbix-proxy-lts.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/zabbix-proxy-lts.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
