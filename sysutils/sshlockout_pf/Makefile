# $FreeBSD$

PORTNAME=	sshlockout_pf
PORTVERSION=	0.0.2
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# none
EXTRACT_ONLY=	# empty

MAINTAINER=	garga@pfsense.org
COMMENT=	SSH lockout pf

WHERE=		sbin
PLIST_FILES=	${WHERE}/${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-build:
	${CC} ${CFLAGS} -Wall -lpthread -o ${WRKSRC}/${PORTNAME} ${FILESDIR}/${PORTNAME}.c

do-install:
	${INSTALL_PROGRAM} ${WRKSRC}/${PORTNAME} ${STAGEDIR}${PREFIX}/${WHERE}/

.include <bsd.port.mk>
