# $FreeBSD$

PORTNAME=	u-boot
DISTVERSION=	20181031
CATEGORIES=	sysutils
PKGNAMEPREFIX=	pfSense-
PKGNAMESUFFIX=	-sg3100

MAINTAINER=	loos@FreeBSD.org
COMMENT=	Netgate SG-3100 u-boot
LICENSE=	GPLv2

RUN_DEPENDS=	u-boot-env:sysutils/pfSense-u-boot-env

NO_BUILD=	yes
NO_ARCH=	yes

PLIST_FILES=	share/u-boot/sg3100/u-boot.bin \
		share/u-boot/sg3100/u-boot.mmc \
		share/u-boot/sg3100/u-boot-update.sh \
		share/u-boot/sg3100/ubldr.bin

USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	pfSense
GL_PROJECT=	u-boot-binaries
GL_COMMIT=	f0d992b7587da9577060764912d89f2b5302c6e5
FETCH_BEFORE_ARGS=	--no-verify-peer

do-install:
	${MAKE} -C ${WRKSRC}/sg3100 DESTDIR=${STAGEDIR} install

.include <bsd.port.mk>
