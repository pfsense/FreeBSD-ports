# $FreeBSD$

PORTNAME=	u-boot-env
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
GL_COMMIT=	5e7a67c89cf743512014fbb808208b32c03e6a90
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
