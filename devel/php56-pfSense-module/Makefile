# $FreeBSD$

PORTNAME=	pfSense
PORTVERSION=	0.13
CATEGORIES=	devel
MASTER_SITES=	#
DISTFILES=	#
PKGNAMEPREFIX=	php56-
PKGNAMESUFFIX=	-module

MAINTAINER=	garga@pfsense.org
COMMENT=	Library for getting useful info

LIB_DEPENDS=	libvici.so:security/strongswan

OPTIONS_DEFINE=	IPFW
OPTIONS_DEFAULT=IPFW

IPFW_DESC=	Enable IPFW functions

USE_PHP=	yes
USE_PHPEXT=	yes

CFLAGS+=	-I/usr/local/include -I. -DTCPSTATES
CFLAGS+=	-Wall -Wredundant-decls -Wnested-externs -Winline
IPFW_CFLAGS=	-DIPFW_FUNCTIONS

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

.include <bsd.port.mk>
