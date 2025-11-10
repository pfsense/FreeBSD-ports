PORTNAME=	pfSense-upgrade
PORTVERSION=	1.3.15
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense upgrade script

LICENSE=	APACHE20

NO_MTREE=	yes
NO_BUILD=	yes

PLIST_FILES=	libexec/install-boot.sh \
		libexec/pfSense-upgrade \
		sbin/install-boot \
		sbin/pfSense-repo-setup \
		sbin/pfSense-upgrade

.include <bsd.port.pre.mk>

RUN_DEPENDS+=	pfSense-repoc>=0:sysutils/pfSense-repoc

do-extract:
	@${MKDIR} ${WRKSRC}
	${CP} -r ${FILESDIR}/* ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/sbin
	${MKDIR} ${STAGEDIR}${PREFIX}/libexec
	${MKDIR} ${STAGEDIR}${PREFIX}/share/pfSense
	${INSTALL_SCRIPT} ${WRKSRC}/pfSense-repo-setup \
		${STAGEDIR}${PREFIX}/sbin
	${INSTALL_SCRIPT} ${WRKSRC}/pfSense-upgrade \
		${STAGEDIR}${PREFIX}/libexec
	${INSTALL_SCRIPT} ${WRKSRC}/pfSense-upgrade.wrapper \
		${STAGEDIR}${PREFIX}/sbin/pfSense-upgrade
	${INSTALL_SCRIPT} ${WRKSRC}/install-boot \
		${STAGEDIR}${PREFIX}/sbin
	${INSTALL_SCRIPT} ${WRKSRC}/install-boot.sh \
		${STAGEDIR}${PREFIX}/libexec

.include <bsd.port.post.mk>
