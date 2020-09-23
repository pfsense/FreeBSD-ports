# $FreeBSD$

PORTNAME=	ssh_tunnel_shell
PORTVERSION=	0.1
PORTREVISION=	2
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# none
EXTRACT_ONLY=	# empty

MAINTAINER=	garga@pfsense.org
COMMENT=	SSH tunnel shell

WHERE=		sbin

do-extract:
	${MKDIR} ${WRKSRC}

do-build:
	${CC} ${CFLAGS} -static -o ${WRKSRC}/${PORTNAME} ${FILESDIR}/${PORTNAME}.c

do-install:
	${INSTALL_PROGRAM} ${WRKSRC}/${PORTNAME} ${STAGEDIR}${PREFIX}/${WHERE}/

.include <bsd.port.mk>
