# $FreeBSD$

PORTNAME=	pfSense-pkg-Avahi
PORTVERSION=	2.2
PORTREVISION=	8
CATEGORIES=	net
MASTER_SITES=	# empty
DISTFILES=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense package Avahi

LICENSE=	APACHE20

RUN_DEPENDS=	avahi-app>=0:net/avahi-app \
		nss_mdns>0:dns/nss_mdns

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/avahi
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${PREFIX}/www/shortcuts
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/avahi.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/avahi/avahi.inc \
		${STAGEDIR}${PREFIX}/pkg/avahi
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/avahi.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/shortcuts/pkg_avahi.inc \
		${STAGEDIR}${PREFIX}/www/shortcuts
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/avahi_settings.php \
		${STAGEDIR}${PREFIX}/www

	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
