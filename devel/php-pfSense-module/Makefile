PORTNAME=	pfSense
PORTVERSION=	0.97
CATEGORIES=	devel
MASTER_SITES=	#
DISTFILES=	#
PKGNAMEPREFIX=	${PHP_PKGNAMEPREFIX}
PKGNAMESUFFIX=	-module

MAINTAINER=	coreteam@pfsense.org
COMMENT=	Library for getting useful info

LIB_DEPENDS=	libvici.so:security/strongswan \
				libpfctl.so.0:net/libpfctl

OPTIONS_DEFINE=	ETHERSWITCH PF_CAPTIVEPORTAL
OPTIONS_DEFAULT=ETHERSWITCH PF_CAPTIVEPORTAL

ETHERSWITCH_DESC=	Enable etherswitch functions

USES=		php:ext
IGNORE_WITH_PHP=56 70 71 74 80 81 82

CFLAGS+=	-I/usr/local/include -I. -DTCPSTATES
CFLAGS+=	-DPHP_PFSENSE_VERSION='\"${PORTVERSION}\"'
CFLAGS+=	-Wall -Wredundant-decls -Wnested-externs -Winline
LDFLAGS+=	-L${LOCALBASE}/lib -lpfctl
ETHERSWITCH_CFLAGS=	-DETHERSWITCH_FUNCTIONS
PF_CAPTIVEPORTAL_CFLAGS=	-DPF_CP_FUNCTIONS
CONFIGURE_ARGS=	--enable-pfSense

.include <bsd.port.options.mk>

extract:
	mkdir -p ${WRKSRC}
	cp -r ${FILESDIR}/* ${WRKSRC}
	@${REINPLACE_CMD} -e "s,%%DUMMYNET%%,," \
		${WRKSRC}/config.m4
.if ${PORT_OPTIONS:METHERSWITCH}
	@${REINPLACE_CMD} -e "s,%%ETHERSWITCH%%,ifmedia.c," \
		${WRKSRC}/config.m4
.else
	@${REINPLACE_CMD} -e "s,%%ETHERSWITCH%%,," \
		${WRKSRC}/config.m4
.endif

.include <bsd.port.mk>
