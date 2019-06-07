# $FreeBSD$

PORTNAME=	ng_etf-kmod
PORTVERSION=	0.1
CATEGORIES=	net
MASTER_SITES=	# empty
DISTFILES=	# none
EXTRACT_ONLY=	# empty

MAINTAINER=	renato@pfsense.org
COMMENT=	ng_etf kernel module

NO_BUILD=	yes

PLIST_FILES=	${KMODDIR}/ng_etf.ko

do-install:
	@${MKDIR} ${STAGEDIR}${KMODDIR}
	${INSTALL_DATA} ${FILESDIR}/${ARCH}/ng_etf.ko ${STAGEDIR}${KMODDIR}

.include <bsd.port.mk>
