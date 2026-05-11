PORTNAME=	pfSense-pkg-ciscoduo
PORTVERSION=	1.0
CATEGORIES=	security
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	kadlekr3@cvut.cz
COMMENT=	Cisco Duo MFA integration for pfSense

NO_BUILD=	 yes
NO_MTREE=	 yes
NO_ARCH= 	 yes

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}/usr/local/share/pfSense-pkg-ciscoduo
	${CP} -r ${FILESDIR}/usr/local/pkg/* ${STAGEDIR}${PREFIX}/pkg/
	${CP} -r ${FILESDIR}/usr/local/share/pfSense-pkg-ciscoduo/* ${STAGEDIR}/usr/local/share/pfSense-pkg-ciscoduo/

.include <bsd.port.mk>
