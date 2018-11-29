# $FreeBSD$

PORTNAME=	u-boot-env
DISTVERSION=	20181129
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
GL_COMMIT=	f638b8d2bb5541a68da50771dfe6a2f149b24a78
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
