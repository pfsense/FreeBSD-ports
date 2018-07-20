# $FreeBSD$

PORTNAME=	openvpn-client-export
PORTVERSION=	2.4.6
CATEGORIES=	security
MASTER_SITES=	http://files.pfsense.org/packages/openvpn-client-export/

MAINTAINER=	jimp@pfsense.org
COMMENT=	OpenVPN Client Export

LICENSE=	APACHE20

RUN_DEPENDS=	openvpn>=2.4.3:security/openvpn

USES=		tar:tgz

WRKSRC=		${WRKDIR}/client-export

NO_BUILD=	yes
NO_MTREE=	yes

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/share/openvpn/client-export
	${CP} -r ${WRKSRC}/* ${STAGEDIR}${PREFIX}/share/openvpn/client-export

.include <bsd.port.mk>
