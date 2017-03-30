# $FreeBSD$

PORTNAME=	pfSense-pkg-Netgate_Coreboot_Upgrade
PORTVERSION=	0.9
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense package Netgate Coreboot Upgrade

LICENSE=	APACHE20

RUN_DEPENDS=	flashrom:sysutils/flashrom

NO_MTREE=	yes

COREBOOT_DFF2_ROM=ADI_DFF2-01.00.00.12-nodebug.rom
COREBOOT_RCC_ROM=ADI_RCC-01.00.00.14-nodebug-16m.rom
COREBOOT_RCCVE_ROM=ADI_RCCVE-01.00.00.12-nodebug.rom

PLIST_SUB=	COREBOOT_DFF2_ROM=${COREBOOT_DFF2_ROM} \
		COREBOOT_RCC_ROM=${COREBOOT_RCC_ROM} \
		COREBOOT_RCCVE_ROM=${COREBOOT_RCCVE_ROM}

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-build:
	${CC} ${CFLAGS} -o ${WRKSRC}/adi_flash_util \
		${FILESDIR}/adi_flash_util/adi_flash_util.c
	${CC} ${CFLAGS} -o ${WRKSRC}/adi_powercycle \
		${FILESDIR}/adi_powercycle/adi_powercycle.c

do-install:
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${DATADIR}/roms
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${PREFIX}/sbin
	${MKDIR} ${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/netgate_coreboot_upgrade.inc \
		${STAGEDIR}${PREFIX}/pkg
	@${REINPLACE_CMD} -i '' \
			-e "s|%%DATADIR%%|${DATADIR}|" \
			-e "s|%%COREBOOT_DFF2_ROM%%|${COREBOOT_DFF2_ROM}|" \
			-e "s|%%COREBOOT_RCC_ROM%%|${COREBOOT_RCC_ROM}|" \
			-e "s|%%COREBOOT_RCCVE_ROM%%|${COREBOOT_RCCVE_ROM}|" \
		${STAGEDIR}${PREFIX}/pkg/netgate_coreboot_upgrade.inc
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/netgate_coreboot_upgrade.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/netgate_coreboot_upgrade.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/netgate_coreboot_upgrade.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml
	${INSTALL_DATA} ${FILESDIR}/roms/*.rom ${STAGEDIR}${DATADIR}/roms
	${INSTALL_PROGRAM} ${WRKSRC}/adi_flash_util ${STAGEDIR}${PREFIX}/sbin
	${INSTALL_PROGRAM} ${WRKSRC}/adi_powercycle ${STAGEDIR}${PREFIX}/sbin

.include <bsd.port.mk>
