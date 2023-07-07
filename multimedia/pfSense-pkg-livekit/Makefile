# $FreeBSD$

PORTNAME=	pfSense-pkg-livekit
DISTVERSION=	1.4.3
CLIVERSION=	1.2.7
DISTVERSIONPREFIX=	v
PORTREVISION=	1
CATEGORIES=	net

MAINTAINER=	amanelli@forssea-robotics.fr
COMMENT=	LiveKit port
WWW=		https://livekit.io/

LICENSE=	APACHE20
LICENSE_FILE=	${WRKSRC}/LICENSE

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

USES=		go:modules
GO_MODULE=	github.com/livekit/livekit-server@v${DISTVERSION}
BUILD_DEPENDS=	mage:devel/mage \
		go:devel/go \
		git:devel/git

pre-build:
	cd ${WRKDIR} && git clone --depth 1 --branch v${CLIVERSION} https://github.com/livekit/livekit-cli

do-build:
	cd ${WRKDIR}/livekit-cli && \
		GOOS=freebsd GOARCH=amd64 go build -o bin/livekit-cli ./cmd/livekit-cli
	cd ${WRKSRC} && mage

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${PREFIX}/etc
	${MKDIR} ${STAGEDIR}${DATADIR}
	${MKDIR} ${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/etc/livekit.yml \
		${STAGEDIR}${PREFIX}/etc
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/livekit.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/livekit.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/livekit_token_gen.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_PROGRAM} ${WRKSRC}/bin/livekit-server \
		${STAGEDIR}${PREFIX}/bin
	${INSTALL_PROGRAM} ${WRKDIR}/livekit-cli/bin/livekit-cli \
		${STAGEDIR}${PREFIX}/bin
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${PREFIX}/pkg/livekit.xml \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
