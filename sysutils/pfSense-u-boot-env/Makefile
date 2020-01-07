# $FreeBSD$

PORTNAME=	u-boot-env
DISTVERSION=	20200107
PORTREVISION=	1
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
GL_COMMIT=	e96d6e2d26900266bf0b0d6893b483a5ce45b1c5
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
