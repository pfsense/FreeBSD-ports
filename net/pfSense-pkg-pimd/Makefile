# $FreeBSD$

PORTNAME=	pfSense-pkg-pimd
PORTVERSION=	0.0.1
CATEGORIES=	net
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	PIMD package for pfSense

LICENSE=	APACHE20

RUN_DEPENDS=	pimd>=0:net/pimd

CONFLICTS=	pfSense-pkg-Quagga_OSPF-[0-9]*

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/pimd/inc
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/shortcuts
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${PREFIX}/www
	${MKDIR} ${STAGEDIR}${DATADIR}

	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/pimd.inc \
		${STAGEDIR}${PREFIX}/pkg/
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/pimd.xml \
		${STAGEDIR}${PREFIX}/pkg/
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/pimd/pimd_interfaces.xml \
		${STAGEDIR}${PREFIX}/pkg/pimd/
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/pimd/pimd_bsrcandidate.xml \
		${STAGEDIR}${PREFIX}/pkg/pimd/
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/pimd/pimd_rpcandidate.xml \
		${STAGEDIR}${PREFIX}/pkg/pimd/
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/pimd/pimd_rpaddress.xml \
		${STAGEDIR}${PREFIX}/pkg/pimd/
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/pimd/inc/pimd_validation.inc \
		${STAGEDIR}${PREFIX}/pkg/pimd/inc/
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/shortcuts/pimd.sc.inc \
		${STAGEDIR}${PREFIX}/pkg/shortcuts/
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/www/status_pimd.php \
		${STAGEDIR}${PREFIX}/www/
	${INSTALL_DATA} -m 0644 ${FILESDIR}/etc/inc/priv/pimd.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
