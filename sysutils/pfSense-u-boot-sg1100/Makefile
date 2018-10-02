# $FreeBSD$

PORTNAME=	u-boot
DISTVERSION=	20180902
CATEGORIES=	sysutils
PKGNAMEPREFIX=	pfSense-
PKGNAMESUFFIX=	-sg1100

MAINTAINER=	loos@FreeBSD.org
COMMENT=	Netgate SG-1100 u-boot
LICENSE=	GPLv2

RUN_DEPENDS=	u-boot-env:sysutils/pfSense-u-boot-env

PLIST_FILES=	share/u-boot/sg1100/flash-image.bin

USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	luiz
GL_PROJECT=	u-boot-sg1100
GL_COMMIT=	bf6c2ff3c90a658e47f454cda00817b9d9131823
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
