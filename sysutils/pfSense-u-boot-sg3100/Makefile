# $FreeBSD$

PORTNAME=	u-boot
DISTVERSION=	20180905
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
		share/u-boot/sg3100/u-boot.mmc

USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	pfSense
GL_PROJECT=	u-boot-binaries
GL_COMMIT=	838f9c20c07dc4b7ed66912a4d897ab44697160d
FETCH_BEFORE_ARGS=	--no-verify-peer

do-install:
	${MAKE} -C ${WRKSRC}/sg3100 DESTDIR=${STAGEDIR} install

.include <bsd.port.mk>
