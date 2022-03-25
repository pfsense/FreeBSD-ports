# $FreeBSD$

PORTNAME=	pfSense
PORTVERSION=	0.76
PORTREVISION=	1
CATEGORIES=	devel
MASTER_SITES=	#
DISTFILES=	#
PKGNAMEPREFIX=	${PHP_PKGNAMEPREFIX}
PKGNAMESUFFIX=	-module

MAINTAINER=	coreteam@pfsense.org
COMMENT=	Library for getting useful info

LIB_DEPENDS=	libvici.so:security/strongswan

OPTIONS_DEFINE=	IPFW ETHERSWITCH
OPTIONS_DEFAULT=IPFW ETHERSWITCH

IPFW_DESC=	Enable IPFW functions
ETHERSWITCH_DESC=	Enable etherswitch functions

USES=		php:ext
IGNORE_WITH_PHP=56 70 71

CFLAGS+=	-I/usr/local/include -I. -DTCPSTATES
CFLAGS+=	-Wall -Wredundant-decls -Wnested-externs -Winline
IPFW_CFLAGS=	-DIPFW_FUNCTIONS
ETHERSWITCH_CFLAGS=	-DETHERSWITCH_FUNCTIONS

CONFIGURE_ARGS=	--enable-pfSense

.include <bsd.port.options.mk>

extract:
	mkdir -p ${WRKSRC}
	cp -r files/* ${WRKSRC}
.if ${PORT_OPTIONS:MIPFW}
	@${REINPLACE_CMD} -e "s,%%DUMMYNET%%,dummynet.c," \
		${WRKSRC}/config.m4
.else
	@${REINPLACE_CMD} -e "s,%%DUMMYNET%%,," \
		${WRKSRC}/config.m4
.endif
.if ${PORT_OPTIONS:METHERSWITCH}
	@${REINPLACE_CMD} -e "s,%%ETHERSWITCH%%,ifmedia.c," \
		${WRKSRC}/config.m4
.else
	@${REINPLACE_CMD} -e "s,%%ETHERSWITCH%%,," \
		${WRKSRC}/config.m4
.endif

.include <bsd.port.mk>
