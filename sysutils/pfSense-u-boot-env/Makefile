# $FreeBSD$

PORTNAME=	u-boot-env
PORTREVISION=	1
DISTVERSION=	20180902
CATEGORIES=	sysutils
PKGNAMEPREFIX=	pfSense-

MAINTAINER=	loos@FreeBSD.org
COMMENT=	Netgate u-boot env tools
LICENSE=	GPLv2

PLIST_FILES=	sbin/u-boot-env sbin/u-boot-env-read \
		sbin/u-boot-env-update sbin/u-boot-env-write

USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	luiz
GL_PROJECT=	u-boot-env-update
GL_COMMIT=	a4875acf0a24c0d7e6614efc233c18abb9e64ec0
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
