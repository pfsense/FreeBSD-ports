# Created by: Archie Cobbs
# $FreeBSD$

PORTNAME=	mpd
DISTVERSION=	5.7
PORTREVISION=	5
CATEGORIES=	net
MASTER_SITES=	SF/${PORTNAME}/Mpd5/Mpd-${PORTVERSION}
PKGNAMESUFFIX=	5
DIST_SUBDIR=	mpd5

MAINTAINER=	mav@FreeBSD.org
COMMENT=	Multi-link PPP daemon based on netgraph(4)

LICENSE=	BSD3CLAUSE

OPTIONS_DEFINE=	NG_IPACCT
NG_IPACCT_DESC=	Use ng_ipacct kernel module from port

HAS_CONFIGURE=	YES
CONFIGURE_WRKSRC=	"${WRKSRC}/src"

USE_OPENSSL=	yes

.include <bsd.port.options.mk>

.if ${PORT_OPTIONS:MNG_IPACCT}
BUILD_DEPENDS+=	${LOCALBASE}/include/netgraph/ng_ipacct.h:${PORTSDIR}/net-mgmt/ng_ipacct
.if exists(${KMODDIR}/ng_ipacct.ko)
RUN_DEPENDS+=	${KMODDIR}/ng_ipacct.ko:${PORTSDIR}/net-mgmt/ng_ipacct
.else
RUN_DEPENDS+=	/boot/modules/ng_ipacct.ko:${PORTSDIR}/net-mgmt/ng_ipacct
.endif
.endif

USE_RC_SUBR=	mpd5
HTMLNUM!=	jot 70 1
CONFSUF=	conf secret script
DOCSDIR=	${PREFIX}/share/doc/mpd5
PORTDOCS=	${HTMLNUM:S/^/mpd/:S/$/.html/} mpd.html mpd.ps README	\
		mpd_toc.html
PLIST_FILES=	${CONFSUF:S/^/etc\/mpd5\/mpd./:S/$/.sample/} sbin/mpd5

.if !defined(WITHOUT_MAN)
PLIST_FILES+=	man/man8/mpd5.8.gz
.endif

# RFC 4638 support - requires kernel support introduced in:
# FreeBSD 10.x			base r288918	(${OSVERSION} bumped to 1002501 in base r289005)
# FreeBSD 11.x and later	base r287654	(${OSVERSION} bumped to 1100080 in base r288943)
.if (${OPSYS} == FreeBSD && ((${OSVERSION} >= 1002501 && ${OSVERSION} < 1100000) || ${OSVERSION} >= 1100080))
EXTRA_PATCHES+=	${PATCHDIR}/rfc4638/patch-src_bund.c
EXTRA_PATCHES+=	${PATCHDIR}/rfc4638/patch-src_lcp.c
EXTRA_PATCHES+=	${PATCHDIR}/rfc4638/patch-src_phys.c
EXTRA_PATCHES+=	${PATCHDIR}/rfc4638/patch-src_phys.h
EXTRA_PATCHES+=	${PATCHDIR}/rfc4638/patch-src_pppoe.c
COMMENT+=	 (with RFC 4638 support)
.endif

.include <bsd.port.mk>
