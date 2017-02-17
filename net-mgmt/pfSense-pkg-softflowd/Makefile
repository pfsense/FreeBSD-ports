# $FreeBSD$

PORTNAME=	pfSense-pkg-softflowd
PORTVERSION=	1.2.1
PORTREVISION=	2
CATEGORIES=	net-mgmt
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense package softflowd

LICENSE=	APACHE20

RUN_DEPENDS=	${LOCALBASE}/sbin/softflowd:net-mgmt/softflowd

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/softflowd.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/softflowd.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
