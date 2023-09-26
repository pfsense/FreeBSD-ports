PORTNAME=	pfSense-default-config-serial
DISTVERSION=	${PRODUCT_VERSION}
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	Default config.xml with serial console enabled

LICENSE=	APACHE20

BUILD_DEPENDS=	xmlstarlet>0:textproc/xmlstarlet
NO_MTREE=	yes

PLIST_FILES=	/conf.default/config.xml \
		/cf/conf/enableserial_force \
		"@dir /cf" \
		"@dir /cf/conf"

XML_ROOTOBJ=	pfsense

do-build:
	${MKDIR} ${WRKSRC}
	${CP} ${.CURDIR}/../pfSense-default-config/files/config.xml ${WRKSRC}/
	xml ed -L -P -d "${XML_ROOTOBJ}/system/serialspeed" "${WRKSRC}/config.xml"
	xml ed -L -P -s "${XML_ROOTOBJ}/system" -t elem -n "serialspeed" -v "115200" "${WRKSRC}/config.xml"
	xml ed -L -P -d "${XML_ROOTOBJ}/system/enableserial" "${WRKSRC}/config.xml"
	xml ed -L -P -s "${XML_ROOTOBJ}/system" -t elem -n "enableserial" "${WRKSRC}/config.xml"
	# Format
	xml fo -t "${WRKSRC}/config.xml" > "${WRKSRC}/config.xml.tmp"
	${MV} "${WRKSRC}/config.xml.tmp" "${WRKSRC}/config.xml"

do-install:
	${MKDIR} "${STAGEDIR}/conf.default" "${STAGEDIR}/cf/conf"
	${INSTALL_DATA} ${WRKSRC}/config.xml ${STAGEDIR}/conf.default/
	${TOUCH} ${STAGEDIR}/cf/conf/enableserial_force

.include <bsd.port.mk>
