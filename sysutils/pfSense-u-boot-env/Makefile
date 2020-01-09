# $FreeBSD$

PORTNAME=	u-boot-env
DISTVERSION=	20200109
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
GL_COMMIT=	a5aaa63cd8d52a82d039c67a27dadef63f17d478
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
