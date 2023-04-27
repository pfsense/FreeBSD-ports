# $FreeBSD$

PORTNAME=	pfSense-pkg-BindDump
<<<<<<< HEAD
PORTVERSION=	1.0.14
=======
PORTVERSION=	1.0.15
>>>>>>> cb7c404ba (pfSense-pkg-BindDump: Show all Zone Records which are currently served by BIND inkluding DDNS entries and the ability to delete DDNS records from master zones without bind restart. Initial release.)
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

<<<<<<< HEAD
<<<<<<< HEAD
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/binddump.xml ${STAGEDIR}${PREFIX}/pkg
=======
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/binddump.xml ${STAGEDIR}${PREFIX}/pkg
>>>>>>> cb7c404ba (pfSense-pkg-BindDump: Show all Zone Records which are currently served by BIND inkluding DDNS entries and the ability to delete DDNS records from master zones without bind restart. Initial release.)
=======
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/binddump.xml ${STAGEDIR}${PREFIX}/pkg
>>>>>>> c4a744170 (﻿pfSense-pkg-BindDump: Show all Zone Records which are currently served by BIND inkluding DDNS entries and the ability to delete DDNS records from master zones without bind restart. Initial release.)
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/binddump.xml ${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/binddump.inc ${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/binddump.priv.inc ${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/binddump/binddump.php ${STAGEDIR}${PREFIX}/www/packages/binddump
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/binddump/index.php ${STAGEDIR}${PREFIX}/www/packages/binddump
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml ${STAGEDIR}${DATADIR}
	
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${PREFIX}/pkg/binddump.xml \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
