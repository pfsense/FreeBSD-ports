# $FreeBSD$

PORTNAME=	u-boot
PORTVERSION=	2016.03
PORTREVISION=	1
CATEGORIES=	sysutils
MASTER_SITES=	ftp://ftp.denx.de/pub/u-boot/
PKGNAMESUFFIX?=	-ufw

MAINTAINER=	loos@FreeBSD.org
COMMENT?=	Cross-build U-Boot loader for uFW

LICENSE=	GPLv2

BUILD_DEPENDS=	arm-none-eabi-gcc:${PORTSDIR}/devel/arm-none-eabi-gcc

CONF_TARGET?=	am335x_ufw_defconfig

NO_ARCH=	yes

WRKSRC=		${WRKDIR}/u-boot-${DISTVERSION}
USES=		gmake tar:bzip2
SSP_UNSAFE=	yes # cross-LD does not support -fstack-protector

U_BOOT_DIR=	share/u-boot/${PORTNAME}${PKGNAMESUFFIX}
PLIST_FILES=	${U_BOOT_DIR}/u-boot.img \
		${U_BOOT_DIR}/u-boot-spl.bin \
		${U_BOOT_DIR}/MLO \
		${U_BOOT_DIR}/README

MAKE_ARGS+=	ARCH=arm \
		CROSS_COMPILE=arm-none-eabi-

do-configure:
	(cd ${WRKSRC}; ${GMAKE} ${CONF_TARGET})

do-install:
	${MKDIR} ${STAGEDIR}/${PREFIX}/${U_BOOT_DIR}
	${CP} ${WRKSRC}/spl/u-boot-spl.bin ${STAGEDIR}/${PREFIX}/${U_BOOT_DIR}
	${CP} ${WRKSRC}/u-boot.img ${STAGEDIR}/${PREFIX}/${U_BOOT_DIR}
	${CP} ${WRKSRC}/MLO ${STAGEDIR}/${PREFIX}/${U_BOOT_DIR}
	${CP} ${.CURDIR}/pkg-descr ${STAGEDIR}/${PREFIX}/${U_BOOT_DIR}/README

.include <bsd.port.mk>
