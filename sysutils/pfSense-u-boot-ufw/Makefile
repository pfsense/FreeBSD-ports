# $FreeBSD$

PORTNAME=	u-boot
DISTVERSION=	20181030
CATEGORIES=	sysutils
PKGNAMEPREFIX=	pfSense-
PKGNAMESUFFIX=	-ufw

MAINTAINER=	loos@FreeBSD.org
COMMENT=	Netgate SG-1000 u-boot
LICENSE=	GPLv2

RUN_DEPENDS=	u-boot-env:sysutils/pfSense-u-boot-env

NO_BUILD=	yes
NO_ARCH=	yes

PLIST_FILES=	share/u-boot/ufw/MLO \
		share/u-boot/ufw/u-boot.img \
		share/u-boot/ufw/ubldr.bin \
		share/u-boot/ufw/u-boot-update.sh

USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	pfSense
GL_PROJECT=	u-boot-binaries
GL_COMMIT=	38e1d13fd270f92ebec422918dd9186b43d89204
FETCH_BEFORE_ARGS=	--no-verify-peer

do-install:
	${MAKE} -C ${WRKSRC}/ufw DESTDIR=${STAGEDIR} install

.include <bsd.port.mk>
