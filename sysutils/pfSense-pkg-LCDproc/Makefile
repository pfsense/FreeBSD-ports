# $FreeBSD$

PORTNAME=	pfSense-pkg-LCDproc
PORTVERSION=	0.10.0
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	treer.git@gmail.com
COMMENT=	LCDproc package for pfSense

# If your build machine fails with "License not correctly defined", try replacing bsd.licenses.db.mk as follows:
#    cd /usr/ports/Templates/Licenses
#    curl -o ESF https://raw.githubusercontent.com/pfsense/FreeBSD-ports/devel/Templates/Licenses/ESF
#    cd /usr/ports/Mk
#    curl -o bsd.licenses.db.mk https://raw.githubusercontent.com/pfsense/FreeBSD-ports/devel/Mk/bsd.licenses.db.mk
LICENSE=	ESF

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

# Until the sysutils/lcdproc port is included in the pfSense-core repository catalogue
# you may have to install it manually using the following commands:
#   32bit:
#      pkg install print/freetype2
#      (or pkg add http://pkg.freebsd.org/freebsd:10:x86:32/release_3/All/freetype2-2.6.2.txz)
#      pkg add http://pkg.freebsd.org/freebsd:10:x86:32/release_3/All/lcdproc-0.5.7_2.txz
#   64bit:
#      pkg install print/freetype2
#      (or pkg add http://pkg.freebsd.org/freebsd:10:x86:64/release_3/All/freetype2-2.6.2.txz)
#      pkg add http://pkg.freebsd.org/freebsd:10:x86:64/release_3/All/lcdproc-0.5.7_2.txz
RUN_DEPENDS=	${LOCALBASE}/sbin/LCDd:sysutils/lcdproc

# the sysutils/lcdproc port already has this dependency
#LIB_DEPENDS=	libfreetype.so:print/freetype2

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${PREFIX}/www/packages/lcdproc	
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/lcdproc.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/lcdproc_client.php \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/lcdproc.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/lcdproc/lcdproc.php \
		${STAGEDIR}${PREFIX}/www/packages/lcdproc
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/lcdproc/lcdproc_screens.php \
		${STAGEDIR}${PREFIX}/www/packages/lcdproc
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/lcdproc/index.php \
		${STAGEDIR}${PREFIX}/www/packages/lcdproc
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/lcdproc.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
