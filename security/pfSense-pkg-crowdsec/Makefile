# $FreeBSD$

PORTNAME=	pfSense-pkg-crowdsec
PORTVERSION=	0.0.1
PORTREVISION=	3
CATEGORIES=	security
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	marco@crowdsec.net
COMMENT=	Crowdsec package for pfSense

LICENSE=	APACHE20

RUN_DEPENDS=	crowdsec>=1.5.1:security/crowdsec \
		crowdsec-blocklist-mirror>=0.0.1:security/crowdsec-blocklist-mirror \
		crowdsec-firewall-bouncer:security/crowdsec-firewall-bouncer

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/crowdsec.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/crowdsec.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
