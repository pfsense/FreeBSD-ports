# $FreeBSD$

PORTNAME=	u-boot
DISTVERSION=	20180904
CATEGORIES=	sysutils
PKGNAMEPREFIX=	pfSense-
PKGNAMESUFFIX=	-sg1100

MAINTAINER=	loos@FreeBSD.org
COMMENT=	Netgate SG-1100 u-boot
LICENSE=	GPLv2

RUN_DEPENDS=	u-boot-env:sysutils/pfSense-u-boot-env

PLIST_FILES=	share/u-boot/sg1100/flash-image.bin \
		share/u-boot/sg1100/u-boot-update.sh

USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	luiz
GL_PROJECT=	u-boot-sg1100
GL_COMMIT=	3b675aca6e27e2c916953878f8a535179381d6d4
FETCH_BEFORE_ARGS=	--no-verify-peer

.include <bsd.port.mk>
