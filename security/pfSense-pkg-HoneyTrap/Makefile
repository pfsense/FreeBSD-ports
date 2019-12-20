# $FreeBSD$

PORTNAME=	pfSense-pkg-HoneyTrap
PORTVERSION=	0.1
CATEGORIES=	security
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	remco.verhoef@dutchsec.com
COMMENT=	PfSense package HoneyTrap

LICENSE=	ART20

RUN_DEPENDS=	honeytrap:security/honeytrap

NO_BUILD=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}/var/log/honeytrap
	${MKDIR} ${STAGEDIR}${DATADIR}
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/honeytrap
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/honeytrap/config
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/honeytrap/.data
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/priv
	${MKDIR} ${STAGEDIR}${PREFIX}/www/honeytrap
	${MKDIR} ${STAGEDIR}${PREFIX}/www/shortcuts
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/honeytrap-plugin.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/honeytrap/config/default.toml \
		${STAGEDIR}${PREFIX}/pkg/honeytrap/config
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/honeytrap/honeytrap-plugin.inc \
		${STAGEDIR}${PREFIX}/pkg/honeytrap
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/priv/honeytrap.priv.inc \
		${STAGEDIR}${PREFIX}/pkg/priv
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/honeytrap/honeytrap_settings.php \
		${STAGEDIR}${PREFIX}/www/honeytrap/
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/honeytrap/honeytrap_logs.php \
		${STAGEDIR}${PREFIX}/www/honeytrap/
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/shortcuts/pkg_honeytrap.inc \
		${STAGEDIR}${PREFIX}/www/shortcuts
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
