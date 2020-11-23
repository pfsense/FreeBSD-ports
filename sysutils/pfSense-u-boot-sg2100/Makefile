# $FreeBSD$

PORTNAME=	u-boot
DISTVERSION=	20200309
CATEGORIES=	sysutils
PKGNAMEPREFIX=	pfSense-
PKGNAMESUFFIX=	-sg2100

MAINTAINER=	loos@FreeBSD.org
COMMENT=	Netgate SG-2100 u-boot
LICENSE=	GPLv2

RUN_DEPENDS=	u-boot-env:sysutils/pfSense-u-boot-env

NO_BUILD=	yes
NO_ARCH=	yes

PLIST_FILES=	share/u-boot/sg2100/flash-image.bin \
		share/u-boot/sg2100/u-boot-update.sh

USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	pfSense
GL_PROJECT=	u-boot-binaries
GL_COMMIT=	8e4ab88390f325fb25da3c74f73c01e638d0af9c
FETCH_BEFORE_ARGS=	--no-verify-peer

do-install:
	${MAKE} -C ${WRKSRC}/sg2100 DESTDIR=${STAGEDIR} install

.include <bsd.port.mk>
