PORTNAME=	pfSense-pkg-coredns
GIT_SRC = github.com/coredns/coredns
DISTVERSIONPREFIX=	v
DISTVERSION=	1.11.1
PORTREVISION=	3
CATEGORIES=	dns

MAINTAINER=	yuri@FreeBSD.org
COMMENT=	DNS server that chains plugins

LICENSE=	APACHE20
LICENSE_FILE=	${WRKSRC}/LICENSE

USES=		go:modules

GO_MODULE=	github.com/coredns/coredns

PORTSCOUT=	limit:^[0-9]*\.[0-9]*\.[0-9]*$$ # otherwise it picks up the deleted tag v011

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

post-patch:
	cp ${FILESDIR}/plugin.cfg ${PATCH_WRKSRC}

	# TODO: Is there a more proper way to call go generate?
	cd ${PATCH_WRKSRC} && go generate
do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/coredns
	${MKDIR} ${STAGEDIR}${DATADIR}
	${MKDIR} ${STAGEDIR}${PREFIX}/www
	${MKDIR} ${STAGEDIR}${PREFIX}/bin
	${MKDIR} ${STAGEDIR}${PREFIX}/etc/coredns
	${INSTALL_PROGRAM} ${WRKDIR}/bin/pfSense-pkg-coredns ${STAGEDIR}${PREFIX}/bin/coredns
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/coredns.xml ${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/coredns/coredns.inc ${STAGEDIR}${PREFIX}/pkg/coredns
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/coredns_settings.php ${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/etc/coredns/Corefile ${STAGEDIR}${PREFIX}/etc/coredns

	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml
post-install:
.for n in 1 5 7
	${INSTALL_MAN} ${WRKSRC}/man/*.${n} ${STAGEDIR}${MANPREFIX}/man/man${n}
.endfor

.include <bsd.port.mk>
