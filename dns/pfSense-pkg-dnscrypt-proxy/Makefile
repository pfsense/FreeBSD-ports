# $FreeBSD$

PORTNAME=	pfSense-pkg-dnscrypt-proxy
PORTVERSION=	1.2.1
CATEGORIES=	dns
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	ports@FreeBSD.org
COMMENT=	pfSense package for DNSCrypt Proxy encrypted DNS client

LICENSE=	ISC

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${PREFIX}/bin/dnscrypt-proxy-bin
	${MKDIR} ${STAGEDIR}${PREFIX}/www/shortcuts
	${MKDIR} ${STAGEDIR}${DATADIR}
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/dnscrypt-proxy.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/dnscrypt-proxy.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/dnscrypt-proxy-advanced.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/dnscrypt-proxy-cache.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/dnscrypt-proxy-lists.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/dnscrypt-proxy-logging.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/dnscrypt-proxy-querylog.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/dnscrypt-proxy-servers.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/pfSense-pkg-dnscrypt-proxy/info.xml \
		${STAGEDIR}${DATADIR}
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/dnscrypt-proxy-config.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/dnscrypt-proxy-querylog.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/shortcuts/pkg_dnscrypt-proxy.inc \
		${STAGEDIR}${PREFIX}/www/shortcuts
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/dnscrypt-proxy.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/bin/dnscrypt-proxy-bin/LICENSE \
		${STAGEDIR}${PREFIX}/bin/dnscrypt-proxy-bin
	${INSTALL_PROGRAM} ${FILESDIR}${PREFIX}/bin/dnscrypt-proxy-bin/dnscrypt-proxy-amd64 \
		${STAGEDIR}${PREFIX}/bin/dnscrypt-proxy-bin
	${INSTALL_PROGRAM} ${FILESDIR}${PREFIX}/bin/dnscrypt-proxy-bin/dnscrypt-proxy-arm64 \
		${STAGEDIR}${PREFIX}/bin/dnscrypt-proxy-bin
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
