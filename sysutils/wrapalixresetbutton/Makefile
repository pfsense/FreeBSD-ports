# $FreeBSD$

PORTNAME=	wrapalixresetbutton
PORTVERSION=	0.0.8
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# none
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	Utility to detect platform reset button state for use in scripting

PLIST_FILES=	sbin/wrapresetbtn \
		sbin/alixresetbtn \
		sbin/FW7541resetbtn \
		sbin/APUresetbtn \
		sbin/RCC-VEresetbtn \
		sbin/RCC-DFFresetbtn \
		sbin/SG-5100resetbtn \
		sbin/SG-5100led \
		sbin/6100resetbtn

ONLY_FOR_ARCHS=	amd64

do-extract:
	${MKDIR} ${WRKSRC}

do-build:
	${CC} ${CFLAGS} -lpthread -o ${WRKSRC}/wrapresetbtn ${FILESDIR}/wrapresetbtn.c
	${CC} ${CFLAGS} -lpthread -o ${WRKSRC}/alixresetbtn ${FILESDIR}/alixresetbtn.c
	${CC} ${CFLAGS} -lpthread -o ${WRKSRC}/FW7541resetbtn ${FILESDIR}/FW7541resetbtn.c
	${CC} ${CFLAGS} -lpthread -o ${WRKSRC}/APUresetbtn ${FILESDIR}/APUresetbtn.c
	${CC} ${CFLAGS} -lpthread -o ${WRKSRC}/RCC-VEresetbtn ${FILESDIR}/RCC-VE.c
	${CC} ${CFLAGS} -lpthread -o ${WRKSRC}/SG-5100resetbtn ${FILESDIR}/sg5100resetbtn.c
	${CC} ${CFLAGS} -lpthread -o ${WRKSRC}/SG-5100led ${FILESDIR}/sg5100led.c
	${CC} ${CFLAGS} -lgpio -lpthread -o ${WRKSRC}/6100resetbtn ${FILESDIR}/6100resetbtn.c

do-install:
	${INSTALL_PROGRAM} ${WRKSRC}/wrapresetbtn ${STAGEDIR}${PREFIX}/sbin
	${INSTALL_PROGRAM} ${WRKSRC}/alixresetbtn ${STAGEDIR}${PREFIX}/sbin
	${INSTALL_PROGRAM} ${WRKSRC}/FW7541resetbtn ${STAGEDIR}${PREFIX}/sbin
	${INSTALL_PROGRAM} ${WRKSRC}/APUresetbtn ${STAGEDIR}${PREFIX}/sbin
	${INSTALL_PROGRAM} ${WRKSRC}/RCC-VEresetbtn ${STAGEDIR}${PREFIX}/sbin
	${LN} -sf RCC-VEresetbtn ${STAGEDIR}${PREFIX}/sbin/RCC-DFFresetbtn
	${INSTALL_PROGRAM} ${WRKSRC}/SG-5100resetbtn ${STAGEDIR}${PREFIX}/sbin
	${INSTALL_PROGRAM} ${WRKSRC}/SG-5100led ${STAGEDIR}${PREFIX}/sbin
	${INSTALL_PROGRAM} ${WRKSRC}/6100resetbtn ${STAGEDIR}${PREFIX}/sbin

.include <bsd.port.mk>
