PORTNAME=		pfSense-pkg-DNSleaktest
PORTVERSION=		0.1.0
CATEGORIES=		sysutils
MASTER_SITES=		# empty
DISTFILES=		# empty
EXTRACT_ONLY=		# empty

MAINTAINER=		luis@moraguez.com
COMMENT=		DNSleaktest package for pfSense

LICENSE=		APACHE20

USE_GITHUB=		yes
GH_ACCOUNT=		z3d6380

NO_BUILD=		yes
NO_MTREE=		yes

SUB_FILES=		pkg-install pkg-deinstall
SUB_LIST=		PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${PREFIX}/www/packages/dnsleaktest
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/dnsleaktest.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/dnsleaktest.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} -m 0755 ${FILESDIR}${PREFIX}/pkg/dnsleaktest.sh \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/dnsleaktest.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/dnsleaktest/dnsleaktest.php \
		${STAGEDIR}${PREFIX}/www/packages/dnsleaktest
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/dnsleaktest/index.php \
		${STAGEDIR}${PREFIX}/www/packages/dnsleaktest
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${PREFIX}/pkg/dnsleaktest.xml \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
