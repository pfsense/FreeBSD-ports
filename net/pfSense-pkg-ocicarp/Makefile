PORTNAME=	pfSense-pkg-ocicarp
PORTVERSION=	0.1
PORTREVISION=	1
CATEGORIES=	net
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	ce_coe_infra_code_assist_ww_grp@oracle.com
COMMENT=	pfSense package Oracle Cloud Infrastructure CARP

LICENSE=	APACHE20

RUN_DEPENDS=	oci:devel/oci-cli

USES=		python:3.9-3.11 shebangfix

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

SHEBANG_FILES=	${FILESDIR}${PREFIX}/bin/ocicarp.py

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${PREFIX}/bin
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/ocicarp
	${MKDIR} ${STAGEDIR}${PREFIX}/www/shortcuts
	${MKDIR} ${STAGEDIR}${DATADIR}

	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/ocicarp.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/bin/ocicarp.py \
		${STAGEDIR}${PREFIX}/bin
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/ocicarp.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/ocicarp/ocicarp.inc \
		${STAGEDIR}${PREFIX}/pkg/ocicarp
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/ocicarp_settings.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/shortcuts/pkg_ocicarp.inc \
		${STAGEDIR}${PREFIX}/www/shortcuts
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}

	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

post-install:
	${MKDIR} ${STAGEDIR}/usr/local/etc/devd
	${MKDIR} ${STAGEDIR}/usr/local/etc/ocicarp

.include <bsd.port.mk>
