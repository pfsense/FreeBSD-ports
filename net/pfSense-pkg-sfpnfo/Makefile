PORTNAME=	pfSense-pkg-sfpnfo
PORTVERSION=	0.1
PORTREVISION=	1
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	mg.solariz@gmail.com
COMMENT=	pfSense SFP Information

LICENSE=	APACHE20

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${PREFIX}/www/packages/sfpnfo
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/sfpnfo.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/sfpnfo/index.php \
		${STAGEDIR}${PREFIX}/www/packages/sfpnfo
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/sfpnfo/sfpnfo.php \
		${STAGEDIR}${PREFIX}/www/packages/sfpnfo
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/sfpnfo/sfpnfo.inc.php \
		${STAGEDIR}${PREFIX}/www/packages/sfpnfo
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
