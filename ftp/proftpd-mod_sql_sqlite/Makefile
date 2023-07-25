PORTNAME=	mod_sql_sqlite
DISTVERSION=	${PROFTPD_VERSION}
PORTREVISION=	2
CATEGORIES=	ftp
PKGNAMEPREFIX=	proftpd-

MAINTAINER=	cmassiot@upipe.org
COMMENT=	SQLite ProFTPD module
WWW=		http://www.proftpd.org/docs/contrib/mod_sql_sqlite.html

LICENSE=	GPLv2

DESCR=		${.CURDIR}/pkg-descr

MASTERDIR=	${.CURDIR}/../../ftp/proftpd

BUILD_DEPENDS+= ${LOCALBASE}/bin/sqlite3:databases/sqlite3
RUN_DEPENDS+=   ${LOCALBASE}/bin/sqlite3:databases/sqlite3

#_PROFTPD_MODULE_DISTFILES=	proftpd-mod_proxy-${DISTVERSION}${EXTRACT_SUFX}:proxy
#_PROFTPD_MODULE_EXTRACT_ONLY=	proftpd-mod_proxy-${DISTVERSION}${EXTRACT_SUFX}
#_PROFTPD_MODULE_MASTER_SITES=	https://codeload.github.com/Castaglia/proftpd-mod_proxy/tar.gz/v${DISTVERSION}?dummy=/:proxy

INCLUDEDIRS=	${LOCALBASE}/include
LIBDIRS=	${LOCALBASE}/lib

_BUILDING_PROFTPD_MODULE=	${PORTNAME}

#post-extract:
#	@${MV} ${WRKDIR}/proftpd-mod_proxy-${DISTVERSION} ${WRKSRC}/contrib/mod_proxy

.include "${MASTERDIR}/Makefile"
