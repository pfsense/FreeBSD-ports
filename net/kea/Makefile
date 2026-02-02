PORTNAME=	kea
DISTVERSION=	3.0.2
CATEGORIES=	net
MASTER_SITES=	ISC/kea/${DISTVERSION}

PATCHFILES+=	netgate.diff:-p1
MASTER_SITE_BACKUP=
FETCH_DEPENDS=	curl:ftp/curl

MAINTAINER=	apevnev@me.com
COMMENT=	Alternative DHCP implementation by ISC
WWW=		https://kea.isc.org/

LICENSE=	MPL20
LICENSE_FILE=	${WRKSRC}/COPYING

LIB_DEPENDS=	libboost_date_time.so:devel/boost-libs \
		liblog4cplus.so:devel/log4cplus

USES=		compiler:c++11-lang cpe iconv pathfix ssl python tar:xz meson pkgconfig cmake:indirect shebangfix
MESON_ARGS=	--auto-features=disabled \
		-Dcrypto=openssl \
		-Dnetconf=disabled

BUILD_DEPENDS=	googletest>0:devel/googletest \
		rst2man:textproc/py-docutils@${PY_FLAVOR}

CPE_VENDOR=	isc
CPE_VERSION=	${DISTVERSION:C/-.*//}
.if ${DISTVERSION:M*-*}
CPE_UPDATE=	${DISTVERSION:C/.*-//:tl}
.endif

USE_LDCONFIG=	yes
USE_RC_SUBR=	${PORTNAME}

PORTDOCS=	AUTHORS CONTRIBUTING.md COPYING ChangeLog README SECURITY.md \
		code_of_conduct.md examples platforms.rst

SHEBANG_GLOB=	*.py *.in
python_OLD_CMD+=@PYTHON@

OPTIONS_DEFINE=	DOCS MYSQL PGSQL
OPTIONS_SUB=	yes

MYSQL_USES=		mysql
MYSQL_MESON_ENABLED=	mysql
MYSQL_SUB_LIST=		REQ_MYSQL=mysql
MYSQL_SUB_LIST_OFF=	REQ_MYSQL=""

PGSQL_USES=		pgsql
PGSQL_MESON_ENABLED=	postgresql
PGSQL_SUB_LIST=		REQ_PGSQL=postgresql
PGSQL_SUB_LIST_OFF=	REQ_PGSQL=""
PGSQL_VARS=		WANT_PGSQL=client

post-build:
	cd ${WRKSRC}/doc/sphinx/man; \
	for i in *.8.rst; do rst2man $$i > $$(basename $$i .rst); done

post-install:
	@${MKDIR} ${STAGEDIR}${PREFIX}/etc/kea ${STAGEDIR}/var/db/kea \
	${STAGEDIR}/var/run/kea
	@${RM} -rf ${STAGEDIR}${PREFIX}/var
	@cd ${WRKSRC}/doc/sphinx/man; \
	${CP} *.8 ${STAGEDIR}${PREFIX}/share/man/man8
	@cd ${WRKSRC}/_build/src/bin/keactrl; \
	for i in *.conf; do ${CP} $$i ${STAGEDIR}${PREFIX}/etc/kea/$$i.sample; done

pre-fetch:
	if [ ! -f "${DISTDIR}/${DIST_SUBDIR}/netgate.diff" ] || [ "$$( awk '$$1 == "SHA256" && $$2 == "(netgate.diff)" {print $$4}' "${DISTINFO_FILE}" )" != "$$( sha256 -q "${DISTDIR}/${DIST_SUBDIR}/netgate.diff" )" ]; then \
		curl -vv --header 'PRIVATE-TOKEN: ${GITLAB_TOKEN}' -o "${DISTDIR}/${DIST_SUBDIR}/netgate.diff" "https://gitlab.netgate.com/api/v4/projects/8/repository/files/net%2Fkea%2Ffiles%2Fnetgate.diff/raw?ref=plus-devel"; \
	fi

.include <bsd.port.mk>
