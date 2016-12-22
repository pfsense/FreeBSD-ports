# $FreeBSD$

PORTNAME=	pfSense-pkg-acme
PORTVERSION=	0.1
CATEGORIES=	security
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	PiBa-NL
COMMENT=	pfSense package acme

USE_PHP= ftp

CONFLICTS=	

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/acme
	${MKDIR} ${STAGEDIR}${PREFIX}/www
	${MKDIR} ${STAGEDIR}${PREFIX}/www/acme
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/acme.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/acme/acme.inc \
		${STAGEDIR}${PREFIX}/pkg/acme
	${INSTALL_DATA} -m 0755 ${FILESDIR}${PREFIX}/pkg/acme/acme.sh \
		${STAGEDIR}${PREFIX}/pkg/acme
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/acme/acme_sh.inc \
		${STAGEDIR}${PREFIX}/pkg/acme
	${INSTALL_DATA} -m 0755 ${FILESDIR}${PREFIX}/pkg/acme/acme_command.sh \
		${STAGEDIR}${PREFIX}/pkg/acme
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/acme/acme_gui.inc \
		${STAGEDIR}${PREFIX}/pkg/acme
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/acme/acme_htmllist.inc \
		${STAGEDIR}${PREFIX}/pkg/acme
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/acme/acme_serverconnectors.inc \
		${STAGEDIR}${PREFIX}/pkg/acme
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/acme/acme_utils.inc \
		${STAGEDIR}${PREFIX}/pkg/acme
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/acme/pkg_acme_tabs.inc \
		${STAGEDIR}${PREFIX}/pkg/acme
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/acme/acme_accountkeys.php \
		${STAGEDIR}${PREFIX}/www/acme
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/acme/acme_accountkeys_edit.php \
		${STAGEDIR}${PREFIX}/www/acme
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/acme/acme_certificates.php \
		${STAGEDIR}${PREFIX}/www/acme
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/acme/acme_certificates_edit.php \
		${STAGEDIR}${PREFIX}/www/acme
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/acme/acme_generalsettings.php \
		${STAGEDIR}${PREFIX}/www/acme
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/acme.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
