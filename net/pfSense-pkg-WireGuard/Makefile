PORTNAME=	pfSense-pkg-WireGuard
PORTVERSION=	0.2.11
CATEGORIES=	net
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense package WireGuard

LICENSE=	APACHE20

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}/etc/inc/priv

	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/wireguard/classes
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/wireguard/includes

	${MKDIR} ${STAGEDIR}${PREFIX}/www/shortcuts
	${MKDIR} ${STAGEDIR}${PREFIX}/www/widgets/include
	${MKDIR} ${STAGEDIR}${PREFIX}/www/widgets/widgets
	${MKDIR} ${STAGEDIR}${PREFIX}/www/wg/js

	${MKDIR} ${STAGEDIR}${DATADIR}

	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/*.priv.inc \
		${STAGEDIR}/etc/inc/priv

	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/*.xml \
		${STAGEDIR}${PREFIX}/pkg

	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/wireguard/classes/*.class.php \
		${STAGEDIR}${PREFIX}/pkg/wireguard/classes

	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/wireguard/includes/*.inc \
		${STAGEDIR}${PREFIX}/pkg/wireguard/includes

	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/shortcuts/*.inc \
		${STAGEDIR}${PREFIX}/www/shortcuts

	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/widgets/include/*.inc \
		${STAGEDIR}${PREFIX}/www/widgets/include

	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/widgets/widgets/*.widget.php \
		${STAGEDIR}${PREFIX}/www/widgets/widgets

	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/wg/*.php \
		${STAGEDIR}${PREFIX}/www/wg

	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/wg/js/*.js \
		${STAGEDIR}${PREFIX}/www/wg/js

	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}

	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml \
		${STAGEDIR}${PREFIX}/pkg/wireguard.xml

.include <bsd.port.mk>
