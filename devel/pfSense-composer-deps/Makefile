PORTNAME=	pfSense-composer-deps
DISTVERSION=	0.3
CATEGORIES=	devel

MAINTAINER=	coreteam@pfSense.org
COMMENT=	pfSense deps from composer
WWW=

VENDORBASE=	/pfSense/include/vendor

NO_BUILD=	yes
SHCMD_VER=	1.7.0
UTILS_VER=	v4.0.5
SIMPLEPIE_VER=	1.8.1
CACHE_VER=	v7.2.1
CONSOLE_VER=	v7.2.1

USE_GITHUB=	nodefault

# Explicit deps
GH_TUPLE=	mikehaertl:php-shellcommand:${SHCMD_VER}:shellcommand \
		nette:utils:${UTILS_VER}:utils \
		simplepie:simplepie:${SIMPLEPIE_VER}:simplepie \
		symfony:cache:${CACHE_VER}:cache \
		symfony:console:${CONSOLE_VER}:console

# Implicit deps
PSR_CACHE_VER=	3.0.0
PSR_CONTA_VER=	2.0.2
PSR_LOG_VER=	3.0.2
SYM_CACHE_VER=	v3.5.1
SYM_DEPRE_VER=	v3.5.1
SYM_CTYPE_VER=	v1.31.0
SYM_IGRAP_VER=	v1.31.0
SYM_INORM_VER=	v1.31.0
SYM_MBSTR_VER=	v1.31.0
SYM_SERV_VER=	v3.5.1
SYM_STR_VER=	v7.2.0
SYM_VAR_VER=	v7.2.0
GH_TUPLE+=	php-fig:cache:${PSR_CACHE_VER}:psrcache \
		php-fig:container:${PSR_CONTA_VER}:psrcontainer \
		php-fig:log:${PSR_LOG_VER}:psrlog \
		symfony:cache-contracts:${SYM_CACHE_VER}:cachecontracts \
		symfony:deprecation-contracts:${SYM_DEPRE_VER}:deprecationcontracts \
		symfony:polyfill-ctype:${SYM_CTYPE_VER}:polyfillctype \
		symfony:polyfill-intl-grapheme:${SYM_IGRAP_VER}:polyfillgrapheme \
		symfony:polyfill-intl-normalizer:${SYM_INORM_VER}:polyfillintlnormalizer \
		symfony:polyfill-mbstring:${SYM_MBSTR_VER}:polyfillmbstring \
		symfony:service-contracts:${SYM_SERV_VER}:servicecontracts \
		symfony:string:${SYM_STR_VER}:string \
		symfony:var-exporter:${SYM_VAR_VER}:varexporter

MASTER_SITE_BACKUP=

do-install:
	(cd ${FILESDIR} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}${VENDORBASE}/)

	# Explicit deps
	(cd ${WRKDIR}/php-shellcommand-${SHCMD_VER} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}${VENDORBASE}/mikehaertl/php-shellcommand)
	(cd ${WRKDIR}/utils-${UTILS_VER:C/v//} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}${VENDORBASE}/nette/utils)
	(cd ${WRKDIR}/simplepie-${SIMPLEPIE_VER} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}${VENDORBASE}/simplepie/simplepie)
	(cd ${WRKDIR}/cache-${CACHE_VER:C/v//} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}${VENDORBASE}/symfony/cache)
	(cd ${WRKDIR}/console-${CONSOLE_VER:C/v//} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}${VENDORBASE}/symfony/console)

	# Implicit deps
	(cd ${WRKDIR}/cache-${PSR_CACHE_VER} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}${VENDORBASE}/psr/cache)
	(cd ${WRKDIR}/container-${PSR_CONTA_VER} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}${VENDORBASE}/psr/container)
	(cd ${WRKDIR}/log-${PSR_LOG_VER} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}${VENDORBASE}/psr/log)
	(cd ${WRKDIR}/cache-contracts-${SYM_CACHE_VER:C/v//} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}${VENDORBASE}/symfony/cache-contracts)
	(cd ${WRKDIR}/deprecation-contracts-${SYM_DEPRE_VER:C/v//} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}${VENDORBASE}/symfony/deprecation-contracts)
	(cd ${WRKDIR}/polyfill-ctype-${SYM_CTYPE_VER:C/v//} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}${VENDORBASE}/symfony/polyfill-ctype)
	(cd ${WRKDIR}/polyfill-intl-grapheme-${SYM_IGRAP_VER:C/v//} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}${VENDORBASE}/symfony/polyfill-intl-grapheme)
	(cd ${WRKDIR}/polyfill-intl-normalizer-${SYM_INORM_VER:C/v//} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}${VENDORBASE}/symfony/polyfill-intl-normalizer)
	(cd ${WRKDIR}/polyfill-mbstring-${SYM_MBSTR_VER:C/v//} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}${VENDORBASE}/symfony/polyfill-mbstring)
	(cd ${WRKDIR}/service-contracts-${SYM_SERV_VER:C/v//} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}${VENDORBASE}/symfony/service-contracts)
	(cd ${WRKDIR}/string-${SYM_STR_VER:C/v//} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}${VENDORBASE}/symfony/string)
	(cd ${WRKDIR}/var-exporter-${SYM_VAR_VER:C/v//} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}${VENDORBASE}/symfony/var-exporter)

.include <bsd.port.mk>
