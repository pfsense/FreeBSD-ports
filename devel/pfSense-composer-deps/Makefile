PORTNAME=	pfSense-composer-deps
DISTVERSION=	0.1
CATEGORIES=	devel

MAINTAINER=	coreteam@pfSense.org
COMMENT=	pfSense deps from composer
WWW=

NO_BUILD=	yes
SHCMD_VER=	1.6.4
CACHE_VER=	v5.3.14
UTILS_VER=	v3.2.10
SIMPLEPIE_VER=	1.7.0

USE_GITHUB=	nodefault
GH_TUPLE=	mikehaertl:php-shellcommand:${SHCMD_VER}:shellcommand \
		symfony:cache:${CACHE_VER}:cache \
		nette:utils:${UTILS_VER}:utils \
		simplepie:simplepie:${SIMPLEPIE_VER}:simplepie

# Extra deps
PSR_CACHE_VER=	2.0.0
PSR_LOG_VER=	3.0.0
SYM_CACHE_VER=	v2.2.0
SYM_DEPRE_VER=	v2.1.3
SYM_POLY80_VER=	v1.16.0
SYM_POLY73_VER=	v1.9.0
SYM_SERV_VER=	v2.2.0
SYM_VAR_VER=	v5.0.11
GH_TUPLE+=	php-fig:cache:${PSR_CACHE_VER}:psrcache \
		php-fig:log:${PSR_LOG_VER}:psrlog \
		symfony:cache-contracts:${SYM_CACHE_VER}:cachecontracts \
		symfony:deprecation-contracts:${SYM_DEPRE_VER}:deprecationcontracts \
		symfony:polyfill-php80:${SYM_POLY80_VER}:polyfillphp80 \
		symfony:polyfill-php73:${SYM_POLY73_VER}:polyfillphp73 \
		symfony:service-contracts:${SYM_SERV_VER}:servicecontracts \
		symfony:var-exporter:${SYM_VAR_VER}:varexporter

MASTER_SITE_BACKUP=

do-install:
	(cd ${FILESDIR} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}/pfSense/include/vendor/)
	(cd ${WRKDIR}/php-shellcommand-${SHCMD_VER} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}/pfSense/include/vendor/mikehaertl/php-shellcommand)
	(cd ${WRKDIR}/cache-${CACHE_VER:C/v//} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}/pfSense/include/vendor/symfony/cache)
	(cd ${WRKDIR}/utils-${UTILS_VER:C/v//} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}/pfSense/include/vendor/nette/utils)
	(cd ${WRKDIR}/simplepie-${SIMPLEPIE_VER} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}/pfSense/include/vendor/simplepie/simplepie)
	(cd ${WRKDIR}/cache-${PSR_CACHE_VER} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}/pfSense/include/vendor/psr/cache)
	(cd ${WRKDIR}/log-${PSR_LOG_VER} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}/pfSense/include/vendor/psr/log)
	(cd ${WRKDIR}/cache-contracts-${SYM_CACHE_VER:C/v//} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}/pfSense/include/vendor/symfony/cache-contracts)
	(cd ${WRKDIR}/deprecation-contracts-${SYM_DEPRE_VER:C/v//} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}/pfSense/include/vendor/symfony/deprecation-contracts)
	(cd ${WRKDIR}/polyfill-php80-${SYM_POLY80_VER:C/v//} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}/pfSense/include/vendor/symfony/polyfill-php80)
	(cd ${WRKDIR}/polyfill-php73-${SYM_POLY73_VER:C/v//} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}/pfSense/include/vendor/symfony/polyfill-php73)
	(cd ${WRKDIR}/service-contracts-${SYM_SERV_VER:C/v//} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}/pfSense/include/vendor/symfony/service-contracts)
	(cd ${WRKDIR}/var-exporter-${SYM_VAR_VER:C/v//} && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}/pfSense/include/vendor/symfony/var-exporter)

.include <bsd.port.mk>
