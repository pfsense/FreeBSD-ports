PORTNAME=	pfSense-default-config-serial
DISTVERSION=	${PRODUCT_VERSION}
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	Default config.xml with serial console enabled

LICENSE=	APACHE20

NO_MTREE=	yes

PLIST_FILES=	/conf.default/config.xml /cf/conf/enableserial_force

do-build:
	${MKDIR} ${WRKSRC}
	${CP} ${FILESDIR}/config.xml ${WRKSRC}/

do-install:
	${MKDIR} ${STAGEDIR}/conf.default
	${INSTALL_DATA} ${WRKSRC}/config.xml ${STAGEDIR}/conf.default/
	${MKDIR} ${STAGEDIR}/cf/conf
	${TOUCH} ${STAGEDIR}/cf/conf/enableserial_force

.include <bsd.port.mk>
