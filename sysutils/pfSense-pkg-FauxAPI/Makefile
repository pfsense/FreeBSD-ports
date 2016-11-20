# $FreeBSD$

PORTNAME=	pfSense-pkg-FauxAPI
PORTVERSION=	1
PORTREVISION=	0
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	contact@nicholasdejong.com
COMMENT=	pfSense package FauxAPI

LICENSE=	APACHE20

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/fauxapi.xml \
		${STAGEDIR}${PREFIX}/pkg

	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/fauxapi.priv.inc \
		${STAGEDIR}/etc/inc/priv

	${MKDIR} ${STAGEDIR}/etc/fauxapi
	${INSTALL_DATA} -m 0600 ${FILESDIR}/etc/fauxapi/credentials.ini \
		${STAGEDIR}/etc/fauxapi

	${MKDIR} ${STAGEDIR}${PREFIX}/www/fauxapi
	${MKDIR} ${STAGEDIR}${PREFIX}/www/fauxapi/v1
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/fauxapi/v1/index.php \
		${STAGEDIR}${PREFIX}/www/fauxapi/v1

	${MKDIR} ${STAGEDIR}${PREFIX}/www/fauxapi/admin
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/fauxapi/admin/about.php \
		${STAGEDIR}${PREFIX}/www/fauxapi/admin
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/fauxapi/admin/credentials.php \
		${STAGEDIR}${PREFIX}/www/fauxapi/admin

	${MKDIR} ${STAGEDIR}/etc/inc/fauxapi
	${INSTALL_DATA} ${FILESDIR}/etc/inc/fauxapi/fauxapi.inc \
		${STAGEDIR}/etc/inc/fauxapi
	${INSTALL_DATA} ${FILESDIR}/etc/inc/fauxapi/fauxapi_actions.inc \
		${STAGEDIR}/etc/inc/fauxapi
	${INSTALL_DATA} ${FILESDIR}/etc/inc/fauxapi/fauxapi_auth.inc \
		${STAGEDIR}/etc/inc/fauxapi
	${INSTALL_DATA} ${FILESDIR}/etc/inc/fauxapi/fauxapi_logger.inc \
		${STAGEDIR}/etc/inc/fauxapi
	${INSTALL_DATA} ${FILESDIR}/etc/inc/fauxapi/fauxapi_pfsense_interface.inc \
		${STAGEDIR}/etc/inc/fauxapi
	${INSTALL_DATA} ${FILESDIR}/etc/inc/fauxapi/fauxapi_utils.inc \
		${STAGEDIR}/etc/inc/fauxapi

	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
