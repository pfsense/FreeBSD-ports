PORTNAME=	pfSense-gnid
DISTVERSION=	0.1
CATEGORIES=	security

MAINTAINER=	coreteam@pfsense.org
COMMENT=	GNID tool.

LICENSE=	APACHE20

USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	pfSense
GL_PROJECT=	gnid
GL_TAGNAME=	bb1166d497b1535098a0e9411c69786e7d4ac0ad
MASTER_SITE_BACKUP=
NO_CHECKSUM=	yes
PLIST_FILES=	/usr/sbin/gnid

do-install:
	${MKDIR} ${STAGEDIR}/usr/sbin
	${INSTALL_PROGRAM} ${WRKSRC}/gnid ${STAGEDIR}/usr/sbin/

.include <bsd.port.mk>
