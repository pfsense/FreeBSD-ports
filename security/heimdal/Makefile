PORTNAME=	heimdal
PORTVERSION=	7.8.0
PORTREVISION=	8
CATEGORIES=	security
MASTER_SITES=	https://github.com/heimdal/heimdal/releases/download/${DISTNAME}/

MAINTAINER=	hrs@FreeBSD.org
COMMENT=	Popular BSD-licensed implementation of Kerberos 5
WWW=		https://www.h5l.org/

LICENSE=	BSD3CLAUSE
LICENSE_FILE=	${WRKSRC}/LICENSE

CONFLICTS=	krb5 krb5-*

USES=		cpe gettext-runtime gssapi:bootstrap,heimdal libtool pathfix \
		pkgconfig readline makeinfo ssl
CPE_VENDOR=	${PORTNAME}_project
USE_LDCONFIG=	${GSSAPILIBDIR}

GNU_CONFIGURE=	yes
GNU_CONFIGURE_MANPREFIX=	${PREFIX}/share
CONFIGURE_ENV=	ac_cv_header_fnmatch_h=yes \
		ac_cv_header_db_h=no \
		ac_cv_header_db3_db_h=no \
		ac_cv_header_db4_db_h=no \
		ac_cv_header_db5_db_h=no \
		ac_cv_header_db6_db_h=no \
		ac_cv_prog_COMPILE_ET=${WRKSRC}/lib/com_err/compile_et \
		PYTHON="${TRUE}"
CONFIGURE_ARGS=	--with-berkeley-db \
		--with-libintl \
		--with-libintl-include="${LOCALBASE}/include" \
		--with-libintl-lib="${LOCALBASE}/lib" \
		--libdir="${GSSAPILIBDIR}" \
		--includedir="${GSSAPIINCDIR}" \
		--with-kcm \
		--with-openssl \
		--with-openssl-include="${OPENSSLINC}" \
		--with-openssl-lib="${OPENSSLLIB}" \
		--enable-otp \
		--enable-pthread-support \
		--with-readline="${LOCALBASE}" \
		--with-hdbdir="/var/${PORTNAME}" \
		--sysconfdir="${PREFIX}/etc"
# XXX --with-readline picks up libreadline even if found in /usr/lib.
MAKE_ENV=	INSTALL_CATPAGES=no
CFLAGS+=	-Wno-implicit-function-declaration
LDFLAGS+=	-Wl,--undefined-version
INSTALL_TARGET=	install-strip
.if !exists(/etc/rc.d/ipropd_master)
USE_RC_SUBR=	ipropd_master ipropd_slave
.endif
INFO=		heimdal hx509

MAKE_JOBS_UNSAFE=	yes

OPTIONS_DEFINE=	IPV6 BDB LMDB SQLITE LDAP PKINIT DIGEST KX509 CRACKLIB
OPTIONS_DEFAULT=IPV6 BDB                  PKINIT DIGEST KX509
OPTIONS_SUB=	yes

IPV6_CONFIGURE_WITH=	ipv6

BDB_DESC=		Enable BerkeleyDB KDC backend support
BDB_USES=		bdb:5 localbase
BDB_CONFIGURE_ENV=	ac_cv_header_db${BDB_VER}_db_h=yes \
			ac_cv_func_db_create=yes \
			ac_cv_funclib_db_create="-l${BDB_LIB_NAME}"
BDB_CONFIGURE_ON=	--disable-ndbm-db
BDB_CONFIGURE_ENV_OFF=	ac_cv_header_db_h=yes \
			ac_cv_func_db_create=no \
			ac_cv_funclib_db_create=no
BDB_CONFIGURE_OFF=	--enable-ndbm-db

LMDB_DESC=		Enable LMDB KDC backend support
LMDB_CONFIGURE_ENABLE=	mdb_db
LMDB_LIB_DEPENDS=	liblmdb.so:databases/lmdb

SQLITE_DESC=		Enable SQLite KDC backend support
SQLITE_USES=		sqlite
SQLITE_CONFIGURE_ON=	--with-sqlite3-include="${LOCALBASE}/include" \
			--with-sqlite3-lib="${LOCALBASE}/lib"
SQLITE_CONFIGURE_WITH=	sqlite3

LDAP_DESC=		Enable OpenLDAP KDC backend support
LDAP_USES=		ldap
LDAP_CONFIGURE_ON=	--with-openldap-include="${LOCALBASE}/include" \
			--with-openldap-lib="${LOCALBASE}/lib"
LDAP_CONFIGURE_WITH=	openldap

PKINIT_DESC=		Enable PK-INIT support
PKINIT_CONFIGURE_ENABLE=pk-init

DIGEST_DESC=		Enable DIGEST support
DIGEST_CONFIGURE_ENABLE=digest

KX509_DESC=		Enable kx509 support
KX509_CONFIGURE_ENABLE=	kx509

CRACKLIB_DESC=		Use CrackLib for password quality checking
CRACKLIB_LIB_DEPENDS=	libcrack.so:security/cracklib

.include <bsd.port.options.mk>

post-extract:
	@${MKDIR} ${WRKSRC}/kpasswdd-cracklib
	${INSTALL_DATA} ${FILESDIR}/kpasswdd-cracklib.c \
	    ${WRKSRC}/kpasswdd-cracklib
	${INSTALL_DATA} ${FILESDIR}/kpasswdd-Makefile \
	    ${WRKSRC}/kpasswdd-cracklib/Makefile

post-build-CRACKLIB-on:
	cd ${WRKSRC}/kpasswdd-cracklib && \
	    ${SETENV} ${MAKE_ENV} ${MAKE} ${MAKE_ARGS} ${BUILD_TARGET}

post-install-CRACKLIB-on:
	cd ${WRKSRC}/kpasswdd-cracklib && \
	    ${SETENV} ${MAKE_ENV} ${MAKE} ${MAKE_ARGS} ${INSTALL_TARGET}

.include <bsd.port.mk>
