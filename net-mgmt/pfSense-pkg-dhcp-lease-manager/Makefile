# $FreeBSD$

PORTNAME=	pfSense-pkg-dhcp-lease-manager
PORTVERSION=	0.1.0
CATEGORIES=	net-mgmt
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	branson@sandsite.org
COMMENT=	pfSense package to sort, explore and manage DHCP leases
WWW=		https://github.com/sandinak/pfsense-pkg-dhcp-lease-manager

LICENSE=	APACHE20

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${PREFIX}/www/dhcp_lease_manager
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/dhcp_lease_manager.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/dhcp_lease_manager.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/status_dhcp_lease_manager.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/dhcp_lease_manager/lease_manager.js \
		${STAGEDIR}${PREFIX}/www/dhcp_lease_manager
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/dhcp_lease_manager/lease_manager.css \
		${STAGEDIR}${PREFIX}/www/dhcp_lease_manager
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/dhcp_lease_manager.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
