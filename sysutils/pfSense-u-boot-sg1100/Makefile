# $FreeBSD$

PORTNAME=	u-boot
DISTVERSION=	20180913
PORTREVISION=	2
CATEGORIES=	sysutils
PKGNAMEPREFIX=	pfSense-
PKGNAMESUFFIX=	-sg1100

MAINTAINER=	loos@FreeBSD.org
COMMENT=	Netgate SG-1100 u-boot
LICENSE=	GPLv2

RUN_DEPENDS=	u-boot-env:sysutils/pfSense-u-boot-env

NO_BUILD=	yes
NO_ARCH=	yes

PLIST_FILES=	share/u-boot/sg1100/flash-image.bin \
		share/u-boot/sg1100/u-boot-update.sh

USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	pfSense
GL_PROJECT=	u-boot-binaries
GL_COMMIT=	34960cae699f3b778c337b9ed3d3d250084b4fb8
FETCH_BEFORE_ARGS=	--no-verify-peer

do-install:
	${MAKE} -C ${WRKSRC}/sg1100 DESTDIR=${STAGEDIR} install

.include <bsd.port.mk>
