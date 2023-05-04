PORTNAME=	miniupnpd
PORTVERSION=	2.3.3
PORTEPOCH=	1
CATEGORIES=	net

MAINTAINER=	squat@squat.no
COMMENT=	UPnP IGD implementation which uses pf/ipf
WWW=		http://miniupnp.free.fr/

LICENSE=	BSD3CLAUSE

USES=		cpe ssl
CPE_VENDOR=	miniupnp_project

USE_GITHUB=	yes
GH_ACCOUNT=	miniupnp
GH_PROJECT=	miniupnp
GH_TAGNAME=	e439318

USE_RC_SUBR=	miniupnpd

HAS_CONFIGURE=	yes
CPE_VENDOR=	miniupnp_project
PLIST_FILES=	etc/miniupnpd.conf.sample sbin/miniupnpd \
		man/man8/miniupnpd.8.gz
MAKE_JOBS_UNSAFE=yes
USE_RC_SUBR=	miniupnpd

CFLAGS+=	-I${OPENSSLINC}
LDFLAGS+=	-L${OPENSSLLIB}

WRKSRC_SUBDIR=	miniupnpd

PLIST_FILES=	etc/miniupnpd.conf.sample \
		man/man8/miniupnpd.8.gz \
		sbin/miniupnpd

OPTIONS_DEFINE=			CHECK_PORTINUSE IPV6 LEASEFILE UPNP_IGDV2 \
				UPNP_STRICT
OPTIONS_DEFAULT=		AUTODETECT_FW
OPTIONS_SINGLE=			FIREWALL
OPTIONS_SINGLE_FIREWALL=	AUTODETECT_FW IPFW PF
AUTODETECT_FW_DESC=		Try to autodetect firewall type
CHECK_PORTINUSE_DESC=		Check if ports are in use
IPFW_DESC=			Use IPFW as firewall type
LEASEFILE_DESC=			Enable lease file
PF_DESC=			Use PF as firewall type
UPNP_IGDV2_DESC=		Build an IGDv2 instead of an IGDv1
UPNP_STRICT_DESC=		More strict UPnP specification compliance

PF_FILTER_RULES_CONFIGURE_ON=	--firewall=pf
CHECK_PORTINUSE_CONFIGURE_ON=	--portinuse
IPV6_CONFIGURE_ON=		--ipv6
UPNP_IGDV2_CONFIGURE_ON=	--igd2
UPNP_STRICT_CONFIGURE_ON=	--strict
LEASEFILE_CONFIGURE_ON=		--leasefile

post-patch:
	${REINPLACE_CMD} -e 's|\(-lssl -lcrypto\)|$$(LDFLAGS) \1|g' \
		${WRKSRC}/Makefile.bsd

.include <bsd.port.mk>
