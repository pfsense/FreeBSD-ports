# Created by: Sander Peterse <sander.peterse88@gmail.com>
# $FreeBSD$

PORTNAME=	pfSense-pkg-teamspeak3
PORTVERSION=	3.0.12.3
CATEGORIES=	audio net
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	Server side of the TeamSpeak group voice chat system for PFSense

LICENSE=        ESF

RUN_DEPENDS=    ${LOCALBASE}/libexec/ts3server:audio/teamspeak3-server

NO_BUILD=       yes
NO_MTREE=       yes

SUB_FILES=      pkg-install pkg-deinstall
SUB_LIST=       PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${PREFIX}/www/shortcuts	
	${MKDIR} ${STAGEDIR}${PREFIX}/www/teamspeak3
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/teamspeak3.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/teamspeak3.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/teamspeak3.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/teamspeak3.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/shortcuts/pkg_teamspeak3.inc \
		${STAGEDIR}${PREFIX}/www/shortcuts
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/teamspeak3/teamspeak3_backup_restore.php \
		${STAGEDIR}${PREFIX}/www/teamspeak3
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/teamspeak3/teamspeak3_show_logs.php \
		${STAGEDIR}${PREFIX}/www/teamspeak3
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/teamspeak3/index.php \
		${STAGEDIR}${PREFIX}/www/teamspeak3
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
