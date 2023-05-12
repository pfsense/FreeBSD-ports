# $FreeBSD$

PORTNAME=	pfSense-pkg-crowdsec
PORTVERSION=	0.0.1
CATEGORIES=	security
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	marco@crowdsec.net
COMMENT=	Crowdsec package for pfSense

LICENSE=	APACHE20

RUN_DEPENDS=	crowdsec>=1.4.6:security/crowdsec \
		crowdsec-blocklist-mirror>=0.0.1:security/crowdsec-blocklist-mirror \
		crowdsec-firewall-bouncer:security/crowdsec-firewall-bouncer

NO_BUILD=	yes
NO_MTREE=	yes

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} ${FILESDIR}/crowdsec.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}/crowdsec.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${WRKSRC}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}

.include <bsd.port.mk>

