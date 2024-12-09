PORTNAME=	pfSense
DISTVERSION=	${PRODUCT_VERSION}
DISTVERSIONPREFIX=	v
CATEGORIES=	security
MASTER_SITES=
DISTFILES=
EXTRACT_ONLY=

MAINTAINER=	coreteam@pfsense.org
COMMENT?=	Main pfSense package

LICENSE=	APACHE20

RUN_DEPENDS=	pfSense-system>=0:security/pfSense-system

NO_BUILD=	yes
NO_MTREE=	yes

CONFLICTS=	pfSense-vmware-[0-9]*

PLIST_FILES=	/etc/platform \
		/etc/version.buildtime \
		/etc/version.lastcommit \
		/etc/version.patch

do-install:
	${MKDIR} ${STAGEDIR}/etc
	${ECHO} "${PRODUCT_NAME}" > ${STAGEDIR}/etc/platform
	${ECHO} ${PORTREVISION} > ${STAGEDIR}/etc/version.patch
	${ECHO} "${PFSENSE_COMMITHASH}" > ${STAGEDIR}/etc/version.lastcommit
	${ECHO} "${PFSENSE_DATESTRING}" > ${STAGEDIR}/etc/version.buildtime

.include <bsd.port.mk>
