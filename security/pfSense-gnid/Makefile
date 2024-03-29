PORTNAME=	pfSense-gnid
DISTVERSION=	0.19
CATEGORIES=	security

MAINTAINER=	coreteam@pfsense.org
COMMENT=	GNID tool.

LICENSE=	APACHE20

USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	pfSense
GL_PROJECT=	gnid
GL_TAGNAME=	52db51d82fe4f6fd2f1a53589509b90290af7fb7
MASTER_SITE_BACKUP=
NO_CHECKSUM=	yes
PLIST_FILES=	/usr/sbin/gnid

do-install:
	${MKDIR} ${STAGEDIR}/usr/sbin
	${INSTALL_PROGRAM} ${WRKSRC}/gnid ${STAGEDIR}/usr/sbin/

.include <bsd.port.mk>
