PORTNAME=	jid3lib
PORTVERSION=	0.5.4
PORTREVISION=	3
CATEGORIES=	audio java
MASTER_SITES=	SF/javamusictag/${PORTNAME}/beta-dev6

MAINTAINER=	ports@FreeBSD.org
COMMENT=	Java ID3 Tag Library
WWW=		https://javamusictag.sourceforge.net/index.html

USES=		java:ant,run

PLIST_FILES=	%%JAVAJARDIR%%/jid3lib.jar

do-install:
	@${MKDIR} ${STAGEDIR}${JAVAJARDIR}
	${INSTALL_DATA} ${WRKSRC}/dist/jid3lib-${PORTVERSION}.jar \
		${STAGEDIR}${JAVAJARDIR}/jid3lib.jar

.include <bsd.port.mk>
