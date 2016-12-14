# $FreeBSD$

PORTNAME=	pfSense-pkg-LCDproc
PORTVERSION=	0.10.3
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	LCDproc package for pfSense

LICENSE=	APACHE20

RUN_DEPENDS=	${LOCALBASE}/sbin/LCDd:sysutils/lcdproc

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${PREFIX}/www/packages/lcdproc
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/lcdproc.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/lcdproc_client.php \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/lcdproc_bandwidth_by_ip.php \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/lcdproc.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/lcdproc/lcdproc.php \
		${STAGEDIR}${PREFIX}/www/packages/lcdproc
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/lcdproc/lcdproc_screens.php \
		${STAGEDIR}${PREFIX}/www/packages/lcdproc
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/lcdproc/index.php \
		${STAGEDIR}${PREFIX}/www/packages/lcdproc
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/lcdproc.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml \
		${STAGEDIR}${PREFIX}/pkg/lcdproc.xml

.include <bsd.port.mk>
