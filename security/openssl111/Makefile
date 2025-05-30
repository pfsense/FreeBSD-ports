PORTNAME=	openssl
PORTVERSION=	1.1.1w
PORTREVISION=	2
CATEGORIES=	security devel
MASTER_SITES=	https://www.openssl.org/source/ \
		ftp://ftp.cert.dfn.de/pub/tools/net/openssl/source/
PKGNAMESUFFIX=	111

MAINTAINER=	brnrd@FreeBSD.org
COMMENT=	TLSv1.3 capable SSL and crypto library
WWW=		https://www.openssl.org/

LICENSE=	OpenSSL
LICENSE_FILE=	${WRKSRC}/LICENSE

DEPRECATED=	End-of-life since 2023-09-11, see https://www.openssl.org/blog/blog/2023/09/11/eol-111/ \
		port will be removed when FreeBSD 13 is EoL
EXPIRATION_DATE=	2026-04-30

CONFLICTS_INSTALL=	boringssl libressl libressl-devel openssl openssl3* openssl*-quictls

USES=		cpe perl5
USE_PERL5=	build
TEST_TARGET=	test

HAS_CONFIGURE=	yes
CONFIGURE_SCRIPT=	config
CONFIGURE_ENV=	PERL="${PERL}"
CONFIGURE_ARGS=	--openssldir=${OPENSSLDIR} \
		--prefix=${PREFIX}

LDFLAGS_i386=	-Wl,-znotext

MAKE_ARGS+=	WHOLE_ARCHIVE_FLAG=--whole-archive CNF_LDFLAGS="${LDFLAGS}"
MAKE_ENV+=	LIBRPATH="${PREFIX}/lib" GREP_OPTIONS=

EXTRA_PATCHES+=	${.CURDIR}/../openssl/files/patch-crypto_async_arch_async__posix.h

OPTIONS_GROUP=		CIPHERS HASHES OPTIMIZE PROTOCOLS
OPTIONS_GROUP_CIPHERS=	ARIA DES GOST IDEA SM4 RC2 RC4 RC5 WEAK-SSL-CIPHERS
OPTIONS_GROUP_HASHES=	MD2 MD4 MDC2 RMD160 SM2 SM3
OPTIONS_GROUP_OPTIMIZE=	ASM SSE2 THREADS
OPTIONS_DEFINE_i386=	I386
OPTIONS_GROUP_PROTOCOLS=NEXTPROTONEG SCTP SSL3 TLS1 TLS1_1 TLS1_2

OPTIONS_DEFINE=	ASYNC CT KTLS MAN3 RFC3779 SHARED ZLIB

OPTIONS_DEFAULT=ASM ASYNC CT GOST DES EC KTLS MAN3 MD4 NEXTPROTONEG RC2 \
		RC4 RMD160 SCTP SHARED SSE2 THREADS TLS1 TLS1_1 TLS1_2

.if ${MACHINE_ARCH} == "amd64"
OPTIONS_GROUP_OPTIMIZE+=	EC
.elif ${MACHINE_ARCH} == "mips64el"
OPTIONS_GROUP_OPTIMIZE+=	EC
.endif

OPTIONS_SUB=	yes

ARIA_DESC=	ARIA (South Korean standard)
ASM_DESC=	Assembler code
ASYNC_DESC=	Asynchronous mode
CIPHERS_DESC=	Block Cipher Support
CT_DESC=	Certificate Transparency Support
DES_DESC=	(Triple) Data Encryption Standard
EC_DESC=	Optimize NIST elliptic curves
GOST_DESC=	GOST (Russian standard)
HASHES_DESC=	Hash Function Support
I386_DESC=	i386 (instead of i486+)
IDEA_DESC=	International Data Encryption Algorithm
KTLS_DESC=	Kernel TLS offload
MAN3_DESC=	Install API manpages (section 3, 7)
MD2_DESC=	MD2 (obsolete)
MD4_DESC=	MD4 (unsafe)
MDC2_DESC=	MDC-2 (patented, requires DES)
NEXTPROTONEG_DESC=	Next Protocol Negotiation (SPDY)
OPTIMIZE_DESC=	Optimizations
PROTOCOLS_DESC=	Protocol Support
RC2_DESC=	RC2 (unsafe)
RC4_DESC=	RC4 (unsafe)
RC5_DESC=	RC5 (patented)
RMD160_DESC=	RIPEMD-160
RFC3779_DESC=	RFC3779 support (BGP)
SCTP_DESC=	SCTP (Stream Control Transmission)
SHARED_DESC=	Build shared libraries
SM2_DESC=	SM2 Elliptic Curve DH (Chinese standard)
SM3_DESC=	SM3 256bit (Chinese standard)
SM4_DESC=	SM4 128bit (Chinese standard)
SSE2_DESC=	Runtime SSE2 detection
SSL3_DESC=	SSLv3 (unsafe)
TLS1_DESC=	TLSv1.0 (requires TLS1_1, TLS1_2)
TLS1_1_DESC=	TLSv1.1 (requires TLS1_2)
TLS1_2_DESC=	TLSv1.2
WEAK-SSL-CIPHERS_DESC=	Weak cipher support (unsafe)

# Upstream default disabled options
.for _option in ktls md2 rc5 sctp ssl3 zlib weak-ssl-ciphers
${_option:tu}_CONFIGURE_ON=	enable-${_option}
.endfor

# Upstream default enabled options
.for _option in aria asm async ct des gost idea md4 mdc2 nextprotoneg rc2 rc4 \
	rfc3779 rmd160 shared sm2 sm3 sm4 sse2 threads tls1 tls1_1 tls1_2
${_option:tu}_CONFIGURE_OFF=	no-${_option}
.endfor

MDC2_IMPLIES=	DES
TLS1_IMPLIES=	TLS1_1
TLS1_1_IMPLIES=	TLS1_2

EC_CONFIGURE_ON=	enable-ec_nistp_64_gcc_128
I386_CONFIGURE_ON=	386
KTLS_EXTRA_PATCHES=	${FILESDIR}/extra-patch-ktls
MAN3_EXTRA_PATCHES_OFF=	${FILESDIR}/extra-patch-util_process__docs.pl
SHARED_MAKE_ENV=	SHLIBVER=${OPENSSL_SHLIBVER}
SHARED_PLIST_SUB=	SHLIBVER=${OPENSSL_SHLIBVER}
SHARED_USE=		ldconfig=yes
SSL3_CONFIGURE_ON+=	enable-ssl3-method
ZLIB_CONFIGURE_ON=	zlib-dynamic

PORTSCOUT=		limit:^1\.1\.1

.include <bsd.port.pre.mk>
.if ${PREFIX} == /usr
IGNORE=	the OpenSSL port can not be installed over the base version
.endif

OPENSSLDIR?=	${PREFIX}/openssl
PLIST_SUB+=	OPENSSLDIR=${OPENSSLDIR:S=^${PREFIX}/==}

.include "version.mk"

post-patch:
	${REINPLACE_CMD} \
		-e 's| install_html_docs$$||' \
		-e 's|$$(LIBDIR)/pkgconfig|libdata/pkgconfig|g' \
		${WRKSRC}/Configurations/unix-Makefile.tmpl
	${REINPLACE_CMD} -e 's|\^GNU ld|GNU|' ${WRKSRC}/Configurations/shared-info.pl

post-configure:
	${REINPLACE_CMD} \
		-e 's|SHLIB_VERSION_NUMBER=1.1|SHLIB_VERSION_NUMBER=${OPENSSL_SHLIBVER}|' \
		${WRKSRC}/Makefile
	${REINPLACE_CMD} \
		-e 's|SHLIB_VERSION_NUMBER "1.1"|SHLIB_VERSION_NUMBER "${OPENSSL_SHLIBVER}"|' \
		${WRKSRC}/include/openssl/opensslv.h

post-install-SHARED-on:
.for i in libcrypto libssl
	${INSTALL_LIB} ${WRKSRC}/$i.so.${OPENSSL_SHLIBVER} ${STAGEDIR}${PREFIX}/lib
	${LN} -sf $i.so.${OPENSSL_SHLIBVER} ${STAGEDIR}${PREFIX}/lib/$i.so
.endfor
.for i in capi padlock
	${STRIP_CMD} ${STAGEDIR}${PREFIX}/lib/engines-1.1/${i}.so
.endfor

post-install:
	${STRIP_CMD} ${STAGEDIR}${PREFIX}/bin/openssl

post-install-MAN3-on:
	( cd ${STAGEDIR}/${PREFIX} ; ${FIND} share/man/man3 share/man/man7 -not -type d ) | \
		${SED} 's/$$/.gz/' >>${TMPPLIST}

.include <bsd.port.post.mk>
