# $FreeBSD$

PORTNAME=	u-boot-env
DISTVERSION=	20181018
CATEGORIES=	sysutils
PKGNAMEPREFIX=	pfSense-

MAINTAINER=	loos@FreeBSD.org
COMMENT=	Netgate u-boot env tools
LICENSE=	GPLv2

PLIST_FILES=	sbin/u-boot-env sbin/u-boot-env-read \
		sbin/u-boot-env-update sbin/u-boot-env-write

USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	pfSense
GL_PROJECT=	u-boot-env-update
GL_COMMIT=	40589afe806216bb38eb519259abb60a647cd729
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
