# $FreeBSD$

PORTNAME=	Kontrol-pkg-Sarg
PORTVERSION=	0.6.19
#PORTREVISION=	5
CATEGORIES=	www
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	contato@kontrolsecurity.com.br
COMMENT=	Kontrol SARG Package

LICENSE=	APACHE20

RUN_DEPENDS=	${LOCALBASE}/bin/sarg:www/sarg

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${PREFIX}/www
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/sarg.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/sarg_sync.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/sarg.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/sarg.inc \
		${STAGEDIR}${PREFIX}/pkg
	 ${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/sarg_schedule.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/sarg.template \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/sarg_users.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/www/sarg_frame.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/www/sarg_reports.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/www/sarg.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} -m 0644 ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/www/sarg_sorttable.js \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/www/sarg_report_pdf.php \
		${STAGEDIR}${PREFIX}/www
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${PREFIX}/pkg/sarg.xml

.include <bsd.port.mk>
