PORTNAME=	pfSense-gnid
DISTVERSION=	0.21
CATEGORIES=	security

MAINTAINER=	coreteam@pfsense.org
COMMENT=	GNID tool.

LICENSE=	APACHE20

USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	pfSense
GL_PROJECT=	gnid
GL_TAGNAME=	4fc2d9b77d0aee91a2b570487ef52d6892612f40
MASTER_SITE_BACKUP=
NO_CHECKSUM=	yes
PLIST_FILES=	/usr/sbin/gnid

do-install:
	${MKDIR} ${STAGEDIR}/usr/sbin
	${INSTALL_PROGRAM} ${WRKSRC}/gnid ${STAGEDIR}/usr/sbin/

.include <bsd.port.mk>
