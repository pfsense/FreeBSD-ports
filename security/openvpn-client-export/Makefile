PORTNAME=	openvpn-client-export
PORTVERSION=	2.6.5
CATEGORIES=	security
MASTER_SITES=	https://files.netgate.com/packages/openvpn-client-export/

MAINTAINER=	jimp@netgate.com
COMMENT=	OpenVPN Client Export
WWW=		https://docs.netgate.com/pfsense/en/latest/packages/openvpn-client-export.html

LICENSE=	APACHE20

RUN_DEPENDS=	openvpn>=2.6.0:security/openvpn

USES=		tar:tgz

WRKSRC=		${WRKDIR}/client-export

NO_BUILD=	yes
NO_MTREE=	yes

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/share/openvpn/client-export
	${CP} -r ${WRKSRC}/* ${STAGEDIR}${PREFIX}/share/openvpn/client-export

.include <bsd.port.mk>
