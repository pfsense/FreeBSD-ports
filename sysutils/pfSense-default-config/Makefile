PORTNAME=	pfSense-default-config
DISTVERSION=	${PRODUCT_VERSION}
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	Default config.xml

LICENSE=	APACHE20

NO_MTREE=	yes

PLIST_FILES=	/conf.default/config.xml \
		"@dir /cf" \
		"@dir /cf/conf"

do-build:
	${MKDIR} ${WRKSRC}
	${CP} ${FILESDIR}/config.xml ${WRKSRC}/

do-install:
	${MKDIR} "${STAGEDIR}/conf.default" "${STAGEDIR}/cf/conf"
	${INSTALL_DATA} ${WRKSRC}/config.xml ${STAGEDIR}/conf.default/

.include <bsd.port.mk>
