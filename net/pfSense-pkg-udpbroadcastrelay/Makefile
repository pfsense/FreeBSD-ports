PORTNAME=	pfSense-pkg-udpbroadcastrelay
PORTVERSION=	1.2.6
CATEGORIES=	net
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense package UDP Broadcast Relay

LICENSE=	APACHE20

RUN_DEPENDS=	udpbroadcastrelay>0:net/udpbroadcastrelay

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/udpbroadcastrelay
	${MKDIR} ${STAGEDIR}${PREFIX}/www/udpbroadcastrelay
	${MKDIR} ${STAGEDIR}${DATADIR}

	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/udpbroadcastrelay.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/udpbroadcastrelay.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/udpbroadcastrelay/udpbroadcastrelay.inc \
		${STAGEDIR}${PREFIX}/pkg/udpbroadcastrelay
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/udpbroadcastrelay/udpbroadcastrelay.php \
		${STAGEDIR}${PREFIX}/www/udpbroadcastrelay
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/udpbroadcastrelay/udpbroadcastrelay_edit.php \
		${STAGEDIR}${PREFIX}/www/udpbroadcastrelay
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}

	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
