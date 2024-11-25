PORTNAME=	if_pppoe-kmod
DISTVERSION=	${PRODUCT_VERSION}
DISTVERSIONPREFIX=plus-v
CATEGORIES=	net

MAINTAINER=	coreteam@pfSense.org
COMMENT=	PPPoE Kernel Driver

FETCH_DEPENDS=	curl:ftp/curl

USES=		kmod

USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	pfSense
GL_PROJECT=	if_pppoe
GL_COMMIT=	main
FETCH_CMD=	curl
FETCH_BEFORE_ARGS=	-vv --header 'PRIVATE-TOKEN: ${GITLAB_TOKEN}'
FETCH_AFTER_ARGS=	-o "${DISTDIR}/${DIST_SUBDIR}/$$file"
MASTER_SITE_BACKUP=
NO_CHECKSUM=yes

PLIST_FILES= \
	/sbin/pppcfg \
	/boot/modules/if_pppoe.ko

MAKE_ARGS=MK_TESTS=no

pre-install:
	${MKDIR} -p ${STAGEDIR}/sbin
	${MKDIR} -p ${STAGEDIR}/usr/tests/sys/net/if_pppoe

.include <bsd.port.mk>
