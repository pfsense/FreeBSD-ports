PORTNAME=	pfSense-pkg-nordvpn_routing
PORTVERSION=	2.0.2
CATEGORIES=	net

MAINTAINER=	icepick869@github
COMMENT=	NordVPN Selective Routing package for pfSense

LICENSE=	MIT

RUN_DEPENDS=	${LOCALBASE}/bin/php:lang/php83

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${PREFIX}/www/nordvpn
	${MKDIR} ${STAGEDIR}${PREFIX}/etc
	${MKDIR} ${STAGEDIR}${PREFIX}/share/${PORTNAME}
	${INSTALL_DATA} ${WRKSRC}/usr/local/pkg/nordvpn_routing.xml \
		${STAGEDIR}${PREFIX}/pkg/nordvpn_routing.xml
	${INSTALL_DATA} ${WRKSRC}/usr/local/pkg/nordvpn_routing.inc \
		${STAGEDIR}${PREFIX}/pkg/nordvpn_routing.inc
	${INSTALL_DATA} ${WRKSRC}/usr/local/www/nordvpn/nordvpn_routing.inc \
		${STAGEDIR}${PREFIX}/www/nordvpn/nordvpn_routing.inc
	${INSTALL_DATA} ${WRKSRC}/usr/local/www/nordvpn/page_chrome.inc \
		${STAGEDIR}${PREFIX}/www/nordvpn/page_chrome.inc
	${INSTALL_DATA} ${WRKSRC}/usr/local/www/nordvpn/tunnels.php \
		${STAGEDIR}${PREFIX}/www/nordvpn/tunnels.php
	${INSTALL_DATA} ${WRKSRC}/usr/local/www/nordvpn/rules.php \
		${STAGEDIR}${PREFIX}/www/nordvpn/rules.php
	${INSTALL_DATA} ${WRKSRC}/usr/local/www/nordvpn/killswitch.php \
		${STAGEDIR}${PREFIX}/www/nordvpn/killswitch.php
	${INSTALL_DATA} ${WRKSRC}/usr/local/www/nordvpn/dns.php \
		${STAGEDIR}${PREFIX}/www/nordvpn/dns.php
	${INSTALL_DATA} ${WRKSRC}/usr/local/www/nordvpn/status.php \
		${STAGEDIR}${PREFIX}/www/nordvpn/status.php
	${INSTALL_DATA} ${WRKSRC}/usr/local/etc/nordvpn_boot.php \
		${STAGEDIR}${PREFIX}/etc/nordvpn_boot.php
	${INSTALL_DATA} ${WRKSRC}/usr/local/share/${PORTNAME}/info.xml \
		${STAGEDIR}${PREFIX}/share/${PORTNAME}/info.xml

.include <bsd.port.mk>
