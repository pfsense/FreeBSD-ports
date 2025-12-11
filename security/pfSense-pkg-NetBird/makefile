PORTNAME=		pfSense-pkg-NetBird
PORTVERSION=	0.1.0
CATEGORIES=		security
MASTER_SITES=	# empty
DISTFILES=		# empty
EXTRACT_ONLY=	# empty

MAINTAINER=		dev@netbird.io
COMMENT=		pfSense package NetBird

LICENSE=		APACHE20

RUN_DEPENDS=	netbird>0:security/netbird

NO_ARCH=		yes
NO_BUILD=		yes
NO_MTREE=		yes

SUB_FILES=		pkg-install pkg-deinstall
SUB_LIST=		PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${DATADIR}
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/netbird
	${MKDIR} ${STAGEDIR}${PREFIX}/www/shortcuts

	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}

	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/*.xml \
		${STAGEDIR}${PREFIX}/pkg

	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/netbird/*.inc \
		${STAGEDIR}${PREFIX}/pkg/netbird

	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/*.php \
		${STAGEDIR}${PREFIX}/www

	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/shortcuts/*.inc \
    		${STAGEDIR}${PREFIX}/www/shortcuts

	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml \
		${STAGEDIR}${PREFIX}/pkg/netbird.xml

.include <bsd.port.mk>