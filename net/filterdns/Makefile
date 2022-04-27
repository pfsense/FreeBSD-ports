PORTNAME=	filterdns
PORTVERSION=	2.0
PORTREVISION=	6
CATEGORIES=	net
MASTER_SITES=	# empty
DISTFILES=	# none
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	filterdns

LICENSE=	APACHE20

WHERE=		sbin
PLIST_FILES=    ${WHERE}/${PORTNAME}

do-extract:
	mkdir -p ${WRKSRC}
	cp -r ${FILESDIR}/* ${WRKSRC}
	@${REINPLACE_CMD} -e 's|%%VERSION%%|${PORTVERSION}|' ${WRKSRC}/filterdns.c

do-install:
	${INSTALL_PROGRAM} ${WRKSRC}/${PORTNAME} ${STAGEDIR}${PREFIX}/${WHERE}/

.include <bsd.port.mk>
