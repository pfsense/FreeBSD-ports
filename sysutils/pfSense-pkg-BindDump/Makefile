# $FreeBSD$

PORTNAME=	pfSense-pkg-BindDump
PORTVERSION=	1.0.16
PORTREVISION=	1
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	andreas@awpross.de
COMMENT=	pfSense package BindDump

LICENSE=	APACHE20

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${PREFIX}/www/packages/binddump
	${MKDIR} ${STAGEDIR}${DATADIR}

	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/binddump.xml ${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/binddump.xml ${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/binddump.inc ${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/binddump.priv.inc ${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/binddump/binddump.php ${STAGEDIR}${PREFIX}/www/packages/binddump
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/binddump/binddump.js ${STAGEDIR}${PREFIX}/www/packages/binddump
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/binddump/zoneEdit.php ${STAGEDIR}${PREFIX}/www/packages/binddump
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/binddump/zoneEdit.js ${STAGEDIR}${PREFIX}/www/packages/binddump
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/binddump/index.php ${STAGEDIR}${PREFIX}/www/packages/binddump
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml ${STAGEDIR}${DATADIR}
	
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${PREFIX}/pkg/binddump.xml \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
