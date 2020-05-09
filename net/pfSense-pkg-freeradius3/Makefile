# $FreeBSD$

PORTNAME=	pfSense-pkg-freeradius3
PORTVERSION=	0.15.7
PORTREVISION=	16
CATEGORIES=	net
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	FreeRADIUS 3.x package for pfSense

LICENSE=	APACHE20

RUN_DEPENDS=	${LOCALBASE}/sbin/radiusd:net/freeradius3 \
		${LOCALBASE}/bin/bash:shells/bash

USES=		python shebangfix

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

SHEBANG_FILES=	${WRKSRC}${PREFIX}/pkg/googleauth.py

do-extract:
	${MKDIR} ${WRKSRC}
	${CP} -R ${FILESDIR}/ ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${PREFIX}/www/freeradius/qrcode/
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} -m 0644 ${WRKSRC}${PREFIX}/pkg/freeradius.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${WRKSRC}${PREFIX}/pkg/freeradius.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${WRKSRC}/etc/inc/priv/freeradius.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${WRKSRC}${PREFIX}/www/freeradius_view_config.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${WRKSRC}${PREFIX}/www/freeradius/qrcode/LICENSE \
		${STAGEDIR}${PREFIX}/www/freeradius/qrcode
	${INSTALL_DATA} ${WRKSRC}${PREFIX}/www/freeradius/qrcode/qrcode.js \
		${STAGEDIR}${PREFIX}/www/freeradius/qrcode
	${INSTALL_DATA} ${WRKSRC}${PREFIX}/pkg/freeradiusclients.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${WRKSRC}${PREFIX}/pkg/freeradiussettings.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${WRKSRC}${PREFIX}/pkg/freeradiuseapconf.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${WRKSRC}${PREFIX}/pkg/freeradiussqlconf.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${WRKSRC}${PREFIX}/pkg/freeradiusinterfaces.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${WRKSRC}${PREFIX}/pkg/freeradiussync.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${WRKSRC}${PREFIX}/pkg/freeradiusmodulesldap.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${WRKSRC}${PREFIX}/pkg/freeradiusauthorizedmacs.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${WRKSRC}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	${INSTALL_SCRIPT} ${WRKSRC}${PREFIX}/pkg/googleauth.py \
		${STAGEDIR}${PREFIX}/pkg
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
