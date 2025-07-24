PORTNAME=	if_pppoe-kmod
DISTVERSION=	${IFPPPOE_COMMITDATE}
DISTVERSIONPREFIX=plus-v
CATEGORIES=	net

MAINTAINER=	coreteam@pfSense.org
COMMENT=	PPPoE Kernel Driver

FETCH_DEPENDS=	curl:ftp/curl

USES=		kmod fakeroot

USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	pfSense
GL_PROJECT=	if_pppoe
GL_TAGNAME=	${IFPPPOE_COMMITHASH}
FETCH_CMD=	curl
FETCH_BEFORE_ARGS=	-vv --header 'PRIVATE-TOKEN: ${GITLAB_TOKEN}' -o "${DISTDIR}/${DIST_SUBDIR}/$$file"
DISABLE_SIZE=	yes
MASTER_SITE_BACKUP=
NO_CHECKSUM=	yes

PLIST_FILES= \
	/sbin/pppcfg \
	/boot/modules/if_pppoe.ko

MAKE_ARGS=MK_TESTS=no IFPPPOE_COMMITHASH=${IFPPPOE_COMMITHASH}

.if !defined(IFPPPOE_COMMITHASH)
.error "Environment must specify IFPPPOE_COMMITHASH"
.endif
.if make(fetch) && !defined(GITLAB_TOKEN)
.error "Environment must specify GITLAB_TOKEN"
.endif

pre-install:
	${MKDIR} -p ${STAGEDIR}/sbin
	${MKDIR} -p ${STAGEDIR}/usr/tests/sys/net/if_pppoe

.include <bsd.port.mk>
