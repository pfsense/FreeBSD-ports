# $FreeBSD$

PORTNAME=	pfSense-pkg-OpenBGPD
PORTVERSION=	0.11
PORTREVISION=	8
CATEGORIES=	net
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense package OpenBGPD

LICENSE=	APACHE20

RUN_DEPENDS=	${LOCALBASE}/sbin/bgpctl:net/openbgpd

CONFLICTS=	pfSense-pkg-Quagga_OSPF-[0-9]*

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${PREFIX}/www
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/openbgpd.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/openbgpd_status.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/openbgpd_raw.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/openbgpd.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/openbgpd.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/openbgpd_groups.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/openbgpd_neighbors.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
