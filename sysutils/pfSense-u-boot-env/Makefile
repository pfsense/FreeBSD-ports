# $FreeBSD$

PORTNAME=	u-boot-env
DISTVERSION=	20200110
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
GL_COMMIT=	0f7896ab8d4d9f0ba3443e3cff752bc2da0d1ad7
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
