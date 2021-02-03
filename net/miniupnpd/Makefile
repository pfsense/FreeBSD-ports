# Created by: Daniel O'Connor <darius@dons.net.au>
# $FreeBSD$

PORTNAME=	miniupnpd
DISTVERSION=	2.2.1
PORTEPOCH=	1
CATEGORIES=	net
MASTER_SITES=	http://miniupnp.tuxfamily.org/files/ \
		http://miniupnp.free.fr/files/

MAINTAINER=	squat@squat.no
COMMENT=	UPnP IGD implementation which uses pf/ipf

LICENSE=	BSD3CLAUSE

USES=		cpe ssl
HAS_CONFIGURE=	yes
CPE_VENDOR=	miniupnp_project
PLIST_FILES=	etc/miniupnpd.conf.sample sbin/miniupnpd \
		man/man8/miniupnpd.8.gz
MAKE_JOBS_UNSAFE=yes
USE_RC_SUBR=	miniupnpd

CFLAGS+=	-I${OPENSSLINC}
LDFLAGS+=	-L${OPENSSLLIB}

OPTIONS_DEFINE=	PF_FILTER_RULES CHECK_PORTINUSE IPV6 UPNP_IGDV2 UPNP_STRICT LEASEFILE
PF_FILTER_RULES_DESC=	Enable pf generation of filter rules
CHECK_PORTINUSE_DESC=	Check if ports are in use
UPNP_IGDV2_DESC=	Build an IGDv2 instead of an IGDv1
UPNP_STRICT_DESC=	More strict UPnP specification compliance
LEASEFILE_DESC=	Enable lease file

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
