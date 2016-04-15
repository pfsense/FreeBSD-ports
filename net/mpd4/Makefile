# Created by: Archie Cobbs
# $FreeBSD$

PORTNAME=	mpd
DISTVERSION=	4.4.1
PORTREVISION=	1
CATEGORIES=	net
MASTER_SITES=	SF
MASTER_SITE_SUBDIR=	mpd/Mpd4/Mpd-${DISTVERSION}
DIST_SUBDIR=	mpd4
PKGNAMESUFFIX=	4

MAINTAINER=	mav@FreeBSD.org
COMMENT=	Multi-link PPP daemon based on netgraph(4)

LIB_DEPENDS=	libpdel.so.0:devel/libpdel

OPTIONS_DEFINE=	NG_IPACCT
NG_IPACCT_DESC=	Use ng_ipacct kernel module from port

HAS_CONFIGURE=	YES
CONFIGURE_WRKSRC=	"${WRKSRC}/src"

USE_OPENSSL=	yes

.include <bsd.port.options.mk>

CONFIGURE_ARGS+= --enable-ipv6

.if ${OSVERSION} >= 900007
EXTRA_PATCHES=files/patch9-auth.h.diff
EXTRA_PATCHES+=files/patch9-auth.c.diff
.endif

.if ${PORT_OPTIONS:MNG_IPACCT}
BUILD_DEPENDS+=	${LOCALBASE}/include/netgraph/ng_ipacct.h:net-mgmt/ng_ipacct
.if exists(${KMODDIR}/ng_ipacct.ko)
RUN_DEPENDS+=	${KMODDIR}/ng_ipacct.ko:net-mgmt/ng_ipacct
.else
RUN_DEPENDS+=	/boot/modules/ng_ipacct.ko:net-mgmt/ng_ipacct
.endif
.endif

USE_RC_SUBR=	mpd4.sh
HTMLNUM!=	jot 67 1
CONFSUF=	conf links secret script
DOCSDIR=	${PREFIX}/share/doc/mpd4
PORTDOCS=	${HTMLNUM:S/^/mpd/:S/$/.html/} mpd.html mpd.ps README	\
		mpd_toc.html
PLIST_DIRS=	etc/mpd4
PLIST_FILES=	${CONFSUF:S/^/etc\/mpd4\/mpd./:S/$/.sample/} sbin/mpd4 man/man8/mpd4.8.gz

.include <bsd.port.mk>
