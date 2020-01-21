# $FreeBSD$

PORTNAME=	u-boot
DISTVERSION=	20200121
CATEGORIES=	sysutils
PKGNAMEPREFIX=	pfSense-
PKGNAMESUFFIX=	-sg3200

MAINTAINER=	loos@FreeBSD.org
COMMENT=	Netgate SG-3200 u-boot
LICENSE=	GPLv2

RUN_DEPENDS=	u-boot-env:sysutils/pfSense-u-boot-env

NO_BUILD=	yes
NO_ARCH=	yes

PLIST_FILES=	share/u-boot/sg3200/flash-image.bin \
		share/u-boot/sg3200/u-boot-update.sh

USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	pfSense
GL_PROJECT=	u-boot-binaries
GL_COMMIT=	0272cf034053755e44980ecb2cf25fa6f2eef01d
FETCH_BEFORE_ARGS=	--no-verify-peer

do-install:
	${MAKE} -C ${WRKSRC}/sg3200 DESTDIR=${STAGEDIR} install

.include <bsd.port.mk>
