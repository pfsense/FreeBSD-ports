PORTNAME=	pftop
PORTVERSION=	0.12
PORTREVISION=	1
CATEGORIES=	sysutils net
MASTER_SITES=	https://github.com/grembo/pftop/releases/download/${DISTVERSION}/

MAINTAINER=	grembo@FreeBSD.org
COMMENT=	Utility for real-time display of statistics for pf
COMMENT.debug= Debug files for pftop
WWW=		https://github.com/grembo/pftop/

LICENSE=	BSD2CLAUSE

SUBPACKAGES=	debug
SELF_DEPENDS.debug=	main

LIB_DEPENDS=	libpfctl.so:net/libpfctl

USES=		localbase uidfix

OPTIONS_DEFINE=	ALTQ

ALTQ_DESC=	ALTQ support for queue statistics

.include <bsd.port.options.mk>

MAKE_ARGS=	LOCALBASE="${PREFIX}" MANDIR=${PREFIX}/share/man/man MK_DEBUG_FILES=yes

CFLAGS+=	-g -DHAVE_SNPRINTF=1 -DHAVE_VSNPRINTF=1

.if ${PORT_OPTIONS:MALTQ}
CFLAGS+=	-DHAVE_ALTQ=1
.endif

MAKE_ARGS+=	CSTD=gnu89

PLIST_FILES=	sbin/pftop \
		share/man/man8/pftop.8.gz
PLIST_FILES.debug=	\
	sbin/.debug/pftop.debug

.include <bsd.port.mk>
