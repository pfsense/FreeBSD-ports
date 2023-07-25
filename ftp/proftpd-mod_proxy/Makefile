PORTNAME=	mod_proxy
DISTVERSION=	0.9.2
PORTREVISION=	2
CATEGORIES=	ftp
PKGNAMEPREFIX=	proftpd-

MAINTAINER=	cmassiot@upipe.org
COMMENT=	Reverse Proxy ProFTPD module
WWW=		https://htmlpreview.github.io/?https://github.com/Castaglia/proftpd-mod_proxy/blob/master/mod_proxy.html

LICENSE=	GPLv2

DESCR=		${.CURDIR}/pkg-descr

MASTERDIR=	${.CURDIR}/../../ftp/proftpd

_PROFTPD_MODULE_DISTFILES=	proftpd-mod_proxy-${DISTVERSION}${EXTRACT_SUFX}:proxy
_PROFTPD_MODULE_EXTRACT_ONLY=	proftpd-mod_proxy-${DISTVERSION}${EXTRACT_SUFX}
_PROFTPD_MODULE_MASTER_SITES=	https://codeload.github.com/Castaglia/proftpd-mod_proxy/tar.gz/v${DISTVERSION}?dummy=/:proxy

INCLUDEDIRS=	${LOCALBASE}/include
LIBDIRS=	${LOCALBASE}/lib

_BUILDING_PROFTPD_MODULE=	${PORTNAME}

post-extract:
	@${MV} ${WRKDIR}/proftpd-mod_proxy-${DISTVERSION} ${WRKSRC}/contrib/mod_proxy

.include "${MASTERDIR}/Makefile"
