PORTNAME=	filterdns
PORTVERSION=	1.0
PORTREVISION=	5
CATEGORIES=	net
MASTER_SITES=	# empty
DISTFILES=	# none
EXTRACT_ONLY=	# empty

MAINTAINER=	eri@pfsense.org
COMMENT=	filterdns

WHERE=		sbin
PLIST_FILES=    ${WHERE}/${PORTNAME}

do-extract:
	mkdir -p ${WRKSRC}
	cp -r ${FILESDIR}/* ${WRKSRC}

do-install:
	${INSTALL_PROGRAM} ${WRKSRC}/${PORTNAME} ${STAGEDIR}${PREFIX}/${WHERE}/

.include <bsd.port.mk>
