# $FreeBSD$

PORTNAME=	pfSense-pkg-node_exporter
PORTVERSION=	0.18.1
PORTREVISION=	6
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense package node_exporter

LICENSE=	APACHE20

RUN_DEPENDS=	node_exporter>0:sysutils/node_exporter

USES=		python shebangfix

SHEBANG_FILES=	${WRKSRC}${DATADIR}/interface-collector.py

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}${DATADIR}
	${INSTALL_SCRIPT} ${FILESDIR}${DATADIR}/interface-collector.py \
		${WRKSRC}${DATADIR}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/node_exporter.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/node_exporter.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml ${STAGEDIR}${DATADIR}
	${INSTALL_SCRIPT} ${WRKSRC}${DATADIR}/interface-collector.py \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
