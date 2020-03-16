# $FreeBSD$

PORTNAME=	u-boot-env
DISTVERSION=	20200316
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
GL_COMMIT=	2298ac35e04990dcd10079d06ed6f95e3414a80b
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
