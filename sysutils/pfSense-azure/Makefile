# $FreeBSD$

PORTNAME=	pfSense-azure
PORTVERSION=	0.1
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense Azure Tools

LICENSE=	APACHE20

USES=		python:2.7

NO_BUILD=	yes
NO_MTREE=	yes

PLIST_SUB=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/etc/rc.d
	${MKDIR} ${STAGEDIR}${PREFIX}/sbin
	${MKDIR} ${STAGEDIR}/etc
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/sbin/add-pfsense-user \
		${STAGEDIR}${PREFIX}/sbin
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/sbin/set-pfsense-* \
		${STAGEDIR}${PREFIX}/sbin
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/sbin/waagent \
		${STAGEDIR}${PREFIX}/sbin
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/etc/rc.d/waagent.sh \
		${STAGEDIR}${PREFIX}/etc/rc.d
	${INSTALL_DATA} ${FILESDIR}/etc/waagent.conf \
		${STAGEDIR}/etc

.include <bsd.port.mk>
