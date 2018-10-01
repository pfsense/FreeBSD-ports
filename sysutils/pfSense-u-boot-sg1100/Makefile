# $FreeBSD$

PORTNAME=	u-boot
PORTREVISION=	1
DISTVERSION=	20180828
CATEGORIES=	sysutils
PKGNAMEPREFIX=	pfSense-
PKGNAMESUFFIX=	-sg1100

MAINTAINER=	loos@FreeBSD.org
COMMENT=	Netgate SG-1100 u-boot
LICENSE=	GPLv2

PLIST_FILES=	share/u-boot/sg1100/flash-image.bin

USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	luiz
GL_PROJECT=	u-boot-sg1100
GL_COMMIT=	bf6c2ff3c90a658e47f454cda00817b9d9131823
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
