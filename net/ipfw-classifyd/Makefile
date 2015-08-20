PORTNAME=	ipfw-classifyd
PORTVERSION=	0.1
CATEGORIES=	net
MASTER_SITES=	#
DISTFILES=	#

MAINTAINER=	ermal@pfsense.com
COMMENT=	application classifier

PLIST_FILES=	sbin/ipfw-classifyd
PLIST_DIRSTRY=	etc/ipfw-classifyd/protocols etc/ipfw-classifyd

extract:
	mkdir -p ${WRKSRC}
	cp ${FILESDIR}/* ${WRKSRC}

.include <bsd.port.mk>
