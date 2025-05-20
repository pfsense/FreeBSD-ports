PORTNAME=	repoc
DISTVERSION=	20250520
CATEGORIES=	sysutils
PKGNAMEPREFIX=	pfSense-

MAINTAINER=	luiz@netgate.com
COMMENT=	pfSense dynamic repository client

LIB_DEPENDS=	libucl.so:textproc/libucl
FETCH_DEPENDS=	curl:ftp/curl

PLIST_FILES=	sbin/pfSense-repoc \
		sbin/pfSense-repoc-static
USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	pfSense
GL_PROJECT=	${PORTNAME}
GL_TAGNAME=	${REPOC_COMMITHASH}
FETCH_CMD=	curl
FETCH_BEFORE_ARGS=	-vv --header 'PRIVATE-TOKEN: ${GITLAB_TOKEN}' -o "${DISTDIR}/${DIST_SUBDIR}/$$file"
DISABLE_SIZE=	yes
MASTER_SITE_BACKUP=
NO_CHECKSUM=	yes

.if !defined(REPOC_COMMITHASH)
.error "Environment must specify REPOC_COMMITHASH"
.endif
.if make(fetch) && !defined(GITLAB_TOKEN)
.error "Environment must specify GITLAB_TOKEN"
.endif

.include <bsd.port.mk>
