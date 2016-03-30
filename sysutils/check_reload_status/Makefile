# $FreeBSD$

PORTNAME=	check_reload_status
PORTVERSION=	0.0.6
PORTREVISION=	1
CATEGORIES?=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# none
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	run various pfSense scripts on event.

LICENSE=	ESF

WHERE=		sbin
PLIST_FILES=	${WHERE}/${PORTNAME} ${WHERE}/pfSctl ${WHERE}/fcgicli
CFLAGS+=	-I/usr/local/include -L/usr/local/lib 

CFLAGS+=	-Wsystem-headers -Werror -Wall -Wno-format-y2k -W \
                -Wno-unused-parameter -Wstrict-prototypes \
                -Wmissing-prototypes -Wpointer-arith -Wreturn-type \
                -Wcast-qual -Wwrite-strings -Wswitch -Wshadow \
                -Wunused-parameter -Wchar-subscripts -Winline \
                -Wnested-externs -Wredundant-decls -Wno-pointer-sign    

LIB_DEPENDS=	libevent.so:${PORTSDIR}/devel/libevent2

do-extract:
	mkdir -p ${WRKSRC}

do-build:
	${CC} ${CFLAGS} -lsbuf -levent -o ${WRKSRC}/${PORTNAME} ${FILESDIR}/${PORTNAME}.c
	${CC} ${CFLAGS} -o ${WRKSRC}/pfSctl ${FILESDIR}/pfSctl.c
	${CC} ${CFLAGS} -lsbuf -o ${WRKSRC}/fcgicli ${FILESDIR}/fcgicli.c

do-install:
	${INSTALL_PROGRAM} ${WRKSRC}/${PORTNAME} ${STAGEDIR}${PREFIX}/${WHERE}/
	${INSTALL_PROGRAM} ${WRKSRC}/pfSctl ${STAGEDIR}${PREFIX}/${WHERE}/
	${INSTALL_PROGRAM} ${WRKSRC}/fcgicli ${STAGEDIR}${PREFIX}/${WHERE}/

.include <bsd.port.mk>
