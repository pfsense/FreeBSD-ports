# $FreeBSD$

PORTNAME=	arm64resetbutton
PORTVERSION=	0.1
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# none
EXTRACT_ONLY=	# empty

MAINTAINER=	luiz@netgate.com
COMMENT=	Utility to detect platform reset button state for use in scripting

PLIST_FILES=	sbin/sg3200resetbtn

ONLY_FOR_ARCHS=	arm64

do-extract:
	${MKDIR} ${WRKSRC}

do-build:
	${CC} ${CFLAGS} -o ${WRKSRC}/sg3200resetbtn ${FILESDIR}/sg3200resetbtn.c -lgpio

do-install:
	${INSTALL_PROGRAM} ${WRKSRC}/sg3200resetbtn ${STAGEDIR}${PREFIX}/sbin

.include <bsd.port.mk>
