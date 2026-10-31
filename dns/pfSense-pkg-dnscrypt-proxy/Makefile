# $FreeBSD$

PORTNAME=	pfSense-pkg-dnscrypt-proxy
PORTVERSION=	1.2.13
CATEGORIES=	dns

MAINTAINER=	bill.lowney@gmail.com
COMMENT=	pfSense package for DNSCrypt Proxy encrypted DNS client

LICENSE=	ISCL
LICENSE_FILE=	${WRKSRC}/LICENSE

# The daemon is not built here: upstream publishes signed, statically linked
# FreeBSD binaries per release, and they are fetched and checksum-pinned by
# distinfo rather than committed to the tree.
DNSCRYPT_VERSION=	2.1.20
MASTER_SITES=	https://github.com/DNSCrypt/dnscrypt-proxy/releases/download/${DNSCRYPT_VERSION}/

ONLY_FOR_ARCHS=	amd64 aarch64

NO_BUILD=	yes
NO_MTREE=	yes

# dns/dnscrypt-proxy2 installs sbin/dnscrypt-proxy. No plist collision, but two
# copies of the daemon and two services is not a supported configuration.
CONFLICTS_INSTALL=	dnscrypt-proxy2

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

.include <bsd.port.pre.mk>

# FreeBSD calls it aarch64; upstream names the asset arm64.
.if ${ARCH} == aarch64
DNSCRYPT_ARCH=	arm64
.else
DNSCRYPT_ARCH=	${ARCH}
.endif

DISTFILES=	dnscrypt-proxy-freebsd_${DNSCRYPT_ARCH}-${DNSCRYPT_VERSION}${EXTRACT_SUFX}
WRKSRC=		${WRKDIR}/freebsd-${DNSCRYPT_ARCH}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/bin
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${PREFIX}/www/shortcuts
	${MKDIR} ${STAGEDIR}${DATADIR}
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${INSTALL_PROGRAM} ${WRKSRC}/dnscrypt-proxy \
		${STAGEDIR}${PREFIX}/bin/dnscrypt-proxy
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
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.post.mk>
