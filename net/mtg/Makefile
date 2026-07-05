PORTNAME=	mtg
DISTVERSIONPREFIX=	v
DISTVERSION=	2.1.13
CATEGORIES=	net

MAINTAINER=	drtr0jan@yandex.ru
COMMENT=	MTPROTO proxy server for Telegram
WWW=		https://github.com/9seconds/mtg

LICENSE=	MIT
LICENSE_FILE=	${WRKSRC}/LICENSE

USES=		go:1.26+,modules

USE_RC_SUBR=	${PORTNAME}

GO_MODULE=	github.com/9seconds/mtg/v2
GO_BUILDFLAGS+=	-ldflags "${LD_FLAG_STRING}" -tags netgo

LD_FLAG_STRING=	-s -w -X main.version=${DISTVERSION}

PLIST_FILES=	bin/${PORTNAME} \
		"@sample ${ETCDIR}/config.toml.sample"

post-install:
	${MKDIR} ${STAGEDIR}${ETCDIR}
	${INSTALL_DATA} ${WRKSRC}/example.config.toml \
		${STAGEDIR}${ETCDIR}/config.toml.sample

.include <bsd.port.mk>
