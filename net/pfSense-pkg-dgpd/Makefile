# Copyright 2026 gebra
# SPDX-License-Identifier: Apache-2.0

PORTNAME=	pfSense-pkg-dgpd
PORTVERSION=	1.0.0
CATEGORIES=	net
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	projects@gbrands.de
COMMENT=	Dynamic GUA prefix delegation for pfSense
WWW=	https://github.com/gebra-de/pfSense-Dyn-GUA-Delegation

LICENSE=	APACHE20

RUN_DEPENDS=	${LOCALBASE}/bin/socat:net/socat

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg ${STAGEDIR}${DATADIR} \
		${STAGEDIR}${PREFIX}/libexec ${STAGEDIR}/cf/conf/kea6-scripts
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/dgpd.xml \
		${STAGEDIR}${PREFIX}/pkg/dgpd.xml
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/dgpd.inc \
		${STAGEDIR}${PREFIX}/pkg/dgpd.inc
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/dgpd_kea.inc \
		${STAGEDIR}${PREFIX}/pkg/dgpd_kea.inc
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}/info.xml
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/libexec/dgpd_worker.php \
		${STAGEDIR}${PREFIX}/libexec/dgpd_worker.php
	${INSTALL_SCRIPT} ${FILESDIR}/conf/kea6-scripts/kea-pd-allocator.php \
		${STAGEDIR}/cf/conf/kea6-scripts/kea-pd-allocator.php
	@${REINPLACE_CMD} -i '' -e 's|%%PKGVERSION%%|${PKGVERSION}|g' \
		${STAGEDIR}${DATADIR}/info.xml ${STAGEDIR}${PREFIX}/pkg/dgpd.xml

.include <bsd.port.mk>
