# $FreeBSD$

PORTNAME=	pfSense-azure
PORTVERSION=	0.12
PORTREVISION=	2
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense Azure Tools

LICENSE=	APACHE20

RUN_DEPENDS=	azure-agent>=2.2.13:sysutils/azure-agent

NO_BUILD=	yes
NO_MTREE=	yes

PLIST_SUB=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/etc/rc.d
	${MKDIR} ${STAGEDIR}${PREFIX}/sbin
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/sbin/*-pfsense-* \
		${STAGEDIR}${PREFIX}/sbin
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/etc/waagent.conf \
		${STAGEDIR}${PREFIX}/etc
	( cd ${STAGEDIR}${PREFIX}/etc/rc.d && ${LN} -sf waagent waagent.sh )

.include <bsd.port.mk>
