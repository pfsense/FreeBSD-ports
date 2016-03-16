# $FreeBSD$

PORTNAME=	pfSense-pkg-ipsec-profile
PORTVERSION=	0.4
PORTREVISION=	0
CATEGORIES=	net
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	PfSense package IPSec Profile Generation for IOS devices

NO_BUILD=	yes
NO_MTREE=	yes
#USE_PHP=        yes

SUB_FILES=      pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

.include <bsd.port.pre.mk>

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/www
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	#${MKDIR} ${STAGEDIR}${PREFIX}/share/pfSense/menu
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/ipsec-profile.priv.inc \
		${STAGEDIR}/etc/inc/priv/
	#(cd ${FILESDIR}/etc/inc/aws && ${COPYTREE_SHARE} . \
	#	${STAGEDIR}/etc/inc/aws)
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/ipsec-profile.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/vpn_ipsec_profile.php \
		${STAGEDIR}${PREFIX}/www/
	#${INSTALL_DATA} ${FILESDIR}${DATADIR}/menu.xml \
	#        ${STAGEDIR}${PREFIX}/share/pfSense/menu/${PORTNAME}.xml
	 ${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
