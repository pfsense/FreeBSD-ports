# $FreeBSD$

PORTNAME=	pfSense-pkg-Avahi
PORTVERSION=	2.1
PORTREVISION=	1
CATEGORIES=	net
MASTER_SITES=	# empty
DISTFILES=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense package Avahi

LICENSE=	APACHE20

RUN_DEPENDS=	avahi-app>=0:net/avahi-app

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/avahi
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${PREFIX}/www
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/avahi.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/avahi/avahi.inc \
		${STAGEDIR}${PREFIX}/pkg/avahi
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/avahi.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/avahi_settings.php \
		${STAGEDIR}${PREFIX}/www

	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
