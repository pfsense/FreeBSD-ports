# Created by: Steve Thames <sthames42@gmail.com>
# $FreeBSD$
#*******************************************************************************
# vHosts package makefile.
# see: https://github.com/pfsense/FreeBSD-ports/blob/devel/Mk/bsd.port.mk
# and: https://www.freebsd.org/doc/en/books/porters-handbook/
#*******************************************************************************

PORTNAME=	pfSense-pkg-vHosts
PORTVERSION=	1.0.0
PORTREVISION=	0
CATEGORIES=	www
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	sthames42@gmail.com
COMMENT=	Web Server Package for pfSense

LICENSE=	APACHE20

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/shortcuts
	${MKDIR} ${STAGEDIR}${PREFIX}/www/packages
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/vhosts.inc                ${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/vhosts.xml                ${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/vhosts.inc                ${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/shortcuts/vhosts.inc      ${STAGEDIR}${PREFIX}/pkg/shortcuts
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/vhosts.php       ${STAGEDIR}${PREFIX}/www/packages
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/vhosts_certs.php ${STAGEDIR}${PREFIX}/www/packages
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml                     ${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" ${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
