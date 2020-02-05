# $FreeBSD$

PORTNAME=	u-boot-env
DISTVERSION=	20200205
CATEGORIES=	sysutils
PKGNAMEPREFIX=	pfSense-

MAINTAINER=	loos@FreeBSD.org
COMMENT=	Netgate u-boot env tools

PLIST_FILES=	sbin/u-boot-env sbin/u-boot-env-read \
		sbin/u-boot-env-update sbin/u-boot-env-write

USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	pfSense
GL_PROJECT=	u-boot-env-update
GL_COMMIT=	0a85752aa39671c2c5169495add6cc2e39006ff1
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
