# $FreeBSD$

PORTNAME=	pfSense-upgrade
PORTVERSION=	0.48
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense upgrade script

LICENSE=	APACHE20

NO_MTREE=	yes
NO_BUILD=	yes

.if ${ARCH} == "aarch64"
RUN_DEPENDS=	/usr/local/bin/ping-auth.sh:security/netgate-ping-auth
.endif

PLIST_FILES=	libexec/pfSense-upgrade \
		sbin/pfSense-upgrade

do-extract:
	@${MKDIR} ${WRKSRC}
	${CP} -r ${FILESDIR}/* ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/sbin
	${MKDIR} ${STAGEDIR}${PREFIX}/libexec
	${INSTALL_SCRIPT} ${WRKSRC}/pfSense-upgrade \
		${STAGEDIR}${PREFIX}/libexec
	${INSTALL_SCRIPT} ${WRKSRC}/pfSense-upgrade.wrapper \
		${STAGEDIR}${PREFIX}/sbin/pfSense-upgrade

.include <bsd.port.mk>
