PORTNAME=	mksh
PORTVERSION=	59c
DISTVERSIONPREFIX=	R
PORTREVISION=	2
CATEGORIES=	shells
MASTER_SITES=	https://www.mirbsd.org/MirOS/dist/mir/mksh/ \
		https://pub.allbsd.org/MirOS/dist/mir/mksh/

MAINTAINER=	schaiba@gmail.com
COMMENT=	MirBSD Korn Shell
WWW=		https://www.mirbsd.org/mksh.htm

LICENSE=	ISCL ML
LICENSE_COMB=	multi
LICENSE_GROUPS_ML=	OSI
LICENSE_NAME_ML=	MirOS
LICENSE_TEXT_ML=	Please, read its contents from http://www.mirbsd.org/TaC-mksh.txt
LICENSE_PERMS_ML=	dist-mirror dist-sell pkg-mirror pkg-sell auto-accept

USES=		tar:tgz
WRKSRC=		${WRKDIR}/${PORTNAME}

OPTIONS_DEFINE=	EXAMPLES

do-build:
	cd ${WRKSRC} && ${SETENV} ${MAKE_ENV} SHELL=${SH} ${SH} ${WRKSRC}/Build.sh -r

do-install:
	${INSTALL_PROGRAM} ${WRKSRC}/mksh ${STAGEDIR}${PREFIX}/bin
	${MV} ${WRKSRC}/mksh.1 ${WRKSRC}/mksh.1.orig
	${CAT} ${WRKSRC}/mksh.1.orig | mandoc -T man >${WRKSRC}/mksh.1
	${INSTALL_MAN} ${WRKSRC}/mksh.1 ${STAGEDIR}${PREFIX}/share/man/man1

do-install-EXAMPLES-on:
	${MKDIR} ${STAGEDIR}${EXAMPLESDIR}
	${INSTALL_DATA} ${WRKSRC}/dot.mkshrc ${STAGEDIR}${EXAMPLESDIR}

.include <bsd.port.mk>
