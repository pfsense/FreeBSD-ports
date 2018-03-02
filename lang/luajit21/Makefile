# Created by: Luiz Otavio O Souza <luiz@netgate.com>
# $FreeBSD$

PORTNAME=	luajit
DISTVERSION=	2.1.0-beta3
CATEGORIES=	lang
MASTER_SITES=	http://luajit.org/download/
DISTNAME=	LuaJIT-${DISTVERSION}

MAINTAINER=	luiz@netgate.com
COMMENT=	Just-In-Time Compiler for Lua

LICENSE=	MIT

WRKSRC=		${WRKDIR}/LuaJIT-${DISTVERSION}
USES=		gmake
USE_LDCONFIG=	yes

PLIST_SUB+=	VERSION=${DISTVERSION}

post-install:
	${LN} -sf ${PORTNAME}-${DISTVERSION} \
		${STAGEDIR}${PREFIX}/bin/${PORTNAME}
	@${INSTALL_MAN} ${WRKSRC}/etc/luajit.1 ${STAGEDIR}${MAN1PREFIX}/man/man1

.include <bsd.port.mk>
