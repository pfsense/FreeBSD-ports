# $FreeBSD$

PORTNAME=	u-boot-env
DISTVERSION=	20180902
CATEGORIES=	sysutils
PKGNAMEPREFIX=	pfSense-

MAINTAINER=	loos@FreeBSD.org
COMMENT=	Netgate u-boot env tools
LICENSE=	GPLv2

PLIST_FILES=	sbin/u-boot-env sbin/u-boot-read \
		sbin/u-boot-update sbin/u-boot-write

USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	luiz
GL_PROJECT=	u-boot-env-update
GL_COMMIT=	13d7c0dcbf022d197b49cbf183210dd096e0c36c
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
