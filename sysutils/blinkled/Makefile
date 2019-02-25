PORTNAME=	blinkled
PORTVERSION=	0.3
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# none
EXTRACT_ONLY=	# empty

MAINTAINER=	jimp@pfsense.org
COMMENT=	blinkled

WHERE=		bin
PLIST_FILES=	${WHERE}/${PORTNAME}

do-extract:
	mkdir -p ${WRKSRC}
	cp -r ${FILESDIR}/* ${WRKSRC}

do-install:
	${INSTALL_PROGRAM} ${WRKSRC}/${PORTNAME} ${STAGEDIR}${PREFIX}/${WHERE}/

.include <bsd.port.mk>
