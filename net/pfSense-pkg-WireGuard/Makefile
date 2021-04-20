# $FreeBSD$

PORTNAME=	pfSense-pkg-WireGuard
PORTVERSION=	0.0.6
PORREVISION=	3
CATEGORIES=	net
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty


MAINTAINER=	rcmcdonald91@gmail.com
COMMENT=	pfSense package WireGuard

LICENSE=	APACHE20

RUN_DEPENDS=	${LOCALBASE}/bin/bash:shells/bash \
		wg:net/wireguard-tools \
		wg-quick:net/wireguard-tools \
		${KMODDIR}/if_wg.ko:net/wireguard-kmod

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}/etc/inc/priv

	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/wireguard

	${MKDIR} ${STAGEDIR}${PREFIX}/www
	${MKDIR} ${STAGEDIR}${PREFIX}/www/shortcuts
	${MKDIR} ${STAGEDIR}${PREFIX}/www/wg

	${MKDIR} ${STAGEDIR}${DATADIR}
	
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/wireguard.priv.inc \
		${STAGEDIR}/etc/inc/priv

	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/wireguard.xml \
		${STAGEDIR}${PREFIX}/pkg

	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/wireguard/wg.inc \
		${STAGEDIR}${PREFIX}/pkg/wireguard
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/wireguard/wg_api.inc \
		${STAGEDIR}${PREFIX}/pkg/wireguard
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/wireguard/wg_globals.inc \
		${STAGEDIR}${PREFIX}/pkg/wireguard
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/wireguard/wg_install.inc \
		${STAGEDIR}${PREFIX}/pkg/wireguard
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/wireguard/wg_validate.inc \
		${STAGEDIR}${PREFIX}/pkg/wireguard

	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/shortcuts/pkg_wireguard.inc \
		${STAGEDIR}${PREFIX}/www/shortcuts

	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/wg/status_wireguard.php \
		${STAGEDIR}${PREFIX}/www/wg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/wg/vpn_wg.php \
		${STAGEDIR}${PREFIX}/www/wg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/wg/vpn_wg_edit.php \
		${STAGEDIR}${PREFIX}/www/wg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/wg/vpn_wg_settings.php \
		${STAGEDIR}${PREFIX}/www/wg
		
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}

	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml \
		${STAGEDIR}${PREFIX}/pkg/wireguard.xml

.include <bsd.port.mk>
