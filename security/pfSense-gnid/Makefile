PORTNAME=	pfSense-gnid
DISTVERSION=	0.20
CATEGORIES=	security

MAINTAINER=	coreteam@pfsense.org
COMMENT=	GNID tool.

LICENSE=	APACHE20

USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	pfSense
GL_PROJECT=	gnid
GL_TAGNAME=	6dc8c653cc84d4a73e7716f99b2326c635f4d522
MASTER_SITE_BACKUP=
NO_CHECKSUM=	yes
PLIST_FILES=	/usr/sbin/gnid

do-install:
	${MKDIR} ${STAGEDIR}/usr/sbin
	${INSTALL_PROGRAM} ${WRKSRC}/gnid ${STAGEDIR}/usr/sbin/

.include <bsd.port.mk>
