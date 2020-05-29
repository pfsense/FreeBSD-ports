# $FreeBSD$

PORTNAME=	pfSense-pkg-frr
PORTVERSION=	0.6.5
CATEGORIES=	net
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	FRR package for pfSense

LICENSE=	APACHE20

RUN_DEPENDS=	frr7>=0:net/frr7

CONFLICTS=	pfSense-pkg-OpenBGPD-[0-9]* \
		pfSense-pkg-Quagga_OSPF-[0-9]*

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/frr/inc
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${PREFIX}/www
	${MKDIR} ${STAGEDIR}${PREFIX}/bin
	${MKDIR} ${STAGEDIR}${DATADIR}

	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/frr.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/frr.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/frr/frr_global_acls.xml \
		${STAGEDIR}${PREFIX}/pkg/frr
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/frr/frr_bgp_aspaths.xml \
		${STAGEDIR}${PREFIX}/pkg/frr
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/frr/frr_bgp_neighbors.xml \
		${STAGEDIR}${PREFIX}/pkg/frr
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/frr/frr_ospf_interfaces.xml \
		${STAGEDIR}${PREFIX}/pkg/frr
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/frr/frr_ospf_areas.xml \
		${STAGEDIR}${PREFIX}/pkg/frr
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/frr/frr_global_routemaps.xml \
		${STAGEDIR}${PREFIX}/pkg/frr
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/frr/frr_bgp_communities.xml \
		${STAGEDIR}${PREFIX}/pkg/frr
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/frr/frr_global_raw.xml \
		${STAGEDIR}${PREFIX}/pkg/frr
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/frr/frr_ospf6.xml \
		${STAGEDIR}${PREFIX}/pkg/frr
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/frr/frr_ospf6_interfaces.xml \
		${STAGEDIR}${PREFIX}/pkg/frr
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/frr/frr_ospf.xml \
		${STAGEDIR}${PREFIX}/pkg/frr
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/frr/frr_bgp_advanced.xml \
		${STAGEDIR}${PREFIX}/pkg/frr
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/frr/frr_global_prefixes.xml \
		${STAGEDIR}${PREFIX}/pkg/frr
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/frr/frr_bgp.xml \
		${STAGEDIR}${PREFIX}/pkg/frr
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/frr/inc/frr_ospf6.inc \
		${STAGEDIR}${PREFIX}/pkg/frr/inc
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/frr/inc/frr_ospf.inc \
		${STAGEDIR}${PREFIX}/pkg/frr/inc
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/frr/inc/frr_bgp.inc \
		${STAGEDIR}${PREFIX}/pkg/frr/inc
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/frr/inc/frr_validation.inc \
		${STAGEDIR}${PREFIX}/pkg/frr/inc
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/frr/inc/frr_zebra.inc \
		${STAGEDIR}${PREFIX}/pkg/frr/inc
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/www/status_frr.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} -m 0755 ${FILESDIR}${PREFIX}/bin/frrctl \
		${STAGEDIR}${PREFIX}/bin
	${INSTALL_DATA} -m 0644 ${FILESDIR}/etc/inc/priv/frr.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
