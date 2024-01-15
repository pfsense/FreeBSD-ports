PORTNAME=	pfSense-pkg-Tailscale
PORTVERSION=	0.1.4
CATEGORIES=	security
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense package Tailscale

LICENSE=	APACHE20

RUN_DEPENDS=	tailscale>0:security/tailscale

NO_ARCH=	yes
NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${DATADIR}
	${MKDIR} ${STAGEDIR}${PREFIX}/etc/rc.conf.d/
	${MKDIR} ${STAGEDIR}${PREFIX}/etc/rc.d/
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/tailscale/state
	${MKDIR} ${STAGEDIR}${PREFIX}/www/shortcuts

	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}

	${INSTALL_DATA} -m 0755 ${FILESDIR}${PREFIX}/etc/rc.conf.d/* \
		${STAGEDIR}${PREFIX}/etc/rc.conf.d

	${INSTALL_DATA} -m 0755 ${FILESDIR}${PREFIX}/etc/rc.d/* \
		${STAGEDIR}${PREFIX}/etc/rc.d

	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/*.xml \
		${STAGEDIR}${PREFIX}/pkg

	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/tailscale/*.inc \
		${STAGEDIR}${PREFIX}/pkg/tailscale

	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/tailscale/*.xml \
		${STAGEDIR}${PREFIX}/pkg/tailscale

	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/*.php \
		${STAGEDIR}${PREFIX}/www

	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/shortcuts/*.inc \
		${STAGEDIR}${PREFIX}/www/shortcuts

	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml \
		${STAGEDIR}${PREFIX}/pkg/tailscale.xml \
		${STAGEDIR}${PREFIX}/pkg/tailscale/tailscale_auth.xml

.include <bsd.port.mk>
