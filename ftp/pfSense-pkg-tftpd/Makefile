# $FreeBSD$

PORTNAME=	pfSense-pkg-tftpd
PORTVERSION=	0.1.3
PORTREVISION=	7
CATEGORIES=	ftp
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	pfsense@stefanseidel.info
COMMENT=	pfSense package for tftp server

LICENSE=	APACHE20

RUN_DEPENDS=	${LOCALBASE}/libexec/in.tftpd:ftp/tftp-hpa

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${PREFIX}/www/shortcuts
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/tftpd.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/tftpd.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} -m 0755 ${FILESDIR}${PREFIX}/www/tftp_files.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/shortcuts/pkg_tftpd.inc \
		${STAGEDIR}${PREFIX}/www/shortcuts
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/tftpd.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${PREFIX}/pkg/tftpd.xml \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
