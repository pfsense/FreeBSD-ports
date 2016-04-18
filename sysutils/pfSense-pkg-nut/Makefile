# $FreeBSD$

PORTNAME=	pfSense-pkg-nut
PORTVERSION=	0.1
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

RUN_DEPENDS=	${LOCALBASE}/sbin/upsd:${PORTSDIR}/sysutils/nut

CONFLICTS=	

MAINTAINER=	pfs
COMMENT=	pfSense package nut

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${PREFIX}/www
	${MKDIR} ${STAGEDIR}${PREFIX}/www/widgets
	${MKDIR} ${STAGEDIR}${PREFIX}/www/widgets/widgets
	${MKDIR} ${STAGEDIR}${PREFIX}/www/widgets/javascript
	${MKDIR} ${STAGEDIR}${PREFIX}/www/widgets/include
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/nut.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/nut.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/status_nut.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/widgets/widgets/ups_status.widget.php \
		${STAGEDIR}${PREFIX}/www/widgets/widgets
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/widgets/javascript/ups_status.js \
		${STAGEDIR}${PREFIX}/www/widgets/javascript
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/widgets/include/ups_status.inc \
		${STAGEDIR}${PREFIX}/www/widgets/include	
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/nut.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
