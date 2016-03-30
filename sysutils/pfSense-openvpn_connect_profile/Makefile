# $FreeBSD$

PORTNAME=	pfSense-openvpn_connect_profile
PORTVERSION=	0.2
PORTREVISION=	1
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense OpenVPN Connect Profile

LICENSE=	ESF

NO_BUILD=	yes
NO_MTREE=	yes

PLIST_SUB=	PORTNAME=${PORTNAME}

PLIST_FILES=	share/pfSense/menu/${PORTNAME}.xml \
		${DATADIR}/remote-access-openvpn.ovpn \
		www/openvpn_connect_profile.php

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/www
	${MKDIR} ${STAGEDIR}${DATADIR}
	${MKDIR} ${STAGEDIR}${PREFIX}/share/pfSense/menu
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/openvpn_connect_profile.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/remote-access-openvpn.ovpn \
		${STAGEDIR}${DATADIR}
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/menu.xml \
		${STAGEDIR}${PREFIX}/share/pfSense/menu/${PORTNAME}.xml

.include <bsd.port.mk>
