
PORTNAME=	pfflowd
PORTVERSION=	0.8
PORTREVISION=	1
CATEGORIES=	net
MASTER_SITES=	# empty
DISTFILES=	# none
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=        Convert pfsync states to NetFlow datagrams

WHERE=		sbin
PLIST_FILES=    ${WHERE}/${PORTNAME}
CFLAGS+=	-I/usr/local/include -L/usr/local/lib 

do-extract:
	mkdir -p ${WRKSRC}
	${CP} files/* ${WRKSRC}

do-install:
	${INSTALL_PROGRAM} ${WRKSRC}/${PORTNAME} ${STAGEDIR}${PREFIX}/${WHERE}/

	
.include <bsd.port.mk>
