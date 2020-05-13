# $FreeBSD$

PORTNAME=	pfSense-pkg-Netgate_Coreboot_Upgrade
PORTVERSION=	0.27
PORTREVISION=	1
CATEGORIES=	sysutils
MASTER_SITES=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense package Netgate Coreboot Upgrade

LICENSE=	APACHE20

BUILD_DEPENDS=	e2fsprogs-libuuid>=0:misc/e2fsprogs-libuuid \
		gmake:devel/gmake
RUN_DEPENDS=	flashrom:sysutils/flashrom \
		e2fsprogs-libuuid>=0:misc/e2fsprogs-libuuid

NO_MTREE=	yes
USE_GITHUB=	nodefault

# coreboot (cbfstool)
GH_ACCOUNT=	coreboot:coreboot,vboot
GH_PROJECT=	coreboot:coreboot vboot:vboot
GH_TAGNAME=	4.7:coreboot e0b3841:vboot

COREBOOT_DFF2_ROM=ADI_DFF2-01.00.00.17-nodebug.rom
COREBOOT_RCC_ROM=ADI_RCC-01.00.00.18-nodebug-16m.rom
COREBOOT_RCCVE_ROM=ADI_RCCVE-01.00.00.17-nodebug.rom
COREBOOT_PLCCB_ROM=ADI_PLCC-01.00.00.11.bin

PLIST_SUB=	COREBOOT_DFF2_ROM=${COREBOOT_DFF2_ROM} \
		COREBOOT_RCC_ROM=${COREBOOT_RCC_ROM} \
		COREBOOT_RCCVE_ROM=${COREBOOT_RCCVE_ROM} \
		COREBOOT_PLCCB_ROM=${COREBOOT_PLCCB_ROM} \
		PLCC=${PLCC}

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}
WRKSRC=		${WRKDIR}/${PORTNAME}
CBFSTOOL_PATH=	${WRKSRC}/coreboot/util/cbfstool

.include <bsd.port.options.mk>

.if ${OSVERSION} >= 1100001
PLCC=""
.else
PLCC="@comment "
.endif

pre-extract:
	${MKDIR} ${WRKSRC}

post-extract:
	${CP} -R ${FILESDIR}/adi_smbios_util ${WRKSRC}
	${REINPLACE_CMD} -e "s,%%COREBOOT_PLCCB_ROM%%,${COREBOOT_PLCCB_ROM},g" \
		${WRKSRC}/adi_smbios_util/config.h
	${LN} -sf ${WRKSRC_coreboot} ${WRKSRC}/coreboot
	${PATCH} -p0 -d ${WRKSRC_coreboot} < ${FILESDIR}/fix-build-clang10

do-build:
	${CC} ${CFLAGS} -o ${WRKSRC}/adi_flash_util \
		${FILESDIR}/adi_flash_util/adi_flash_util.c
	${CC} ${CFLAGS} -o ${WRKSRC}/adi_powercycle \
		${FILESDIR}/adi_powercycle/adi_powercycle.c
.if ${OSVERSION} >= 1100001
	${CC} ${CFLAGS} -o ${WRKSRC}/adi_smbios_util/adi_smbios_util -luuid \
		-I${LOCALBASE}/include -L${LOCALBASE}/lib \
		-include ${WRKSRC}/adi_smbios_util/config.h \
		${WRKSRC}/adi_smbios_util/adi_smbios_util.c
	${GMAKE} -C ${CBFSTOOL_PATH} HOSTCC=${CC} HOSTCXX=${CXX} \
		VBOOT_SOURCE=${WRKSRC_vboot}
.endif

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
			-e "s|%%COREBOOT_PLCCB_ROM%%|${COREBOOT_PLCCB_ROM}|" \
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
	${INSTALL_DATA} ${FILESDIR}/roms/*.bin ${STAGEDIR}${DATADIR}/roms
	${INSTALL_PROGRAM} ${WRKSRC}/adi_flash_util ${STAGEDIR}${PREFIX}/sbin
	${INSTALL_PROGRAM} ${WRKSRC}/adi_powercycle ${STAGEDIR}${PREFIX}/sbin
.if ${OSVERSION} >= 1100001
	${INSTALL_PROGRAM} ${WRKSRC}/adi_smbios_util/adi_smbios_util \
		${STAGEDIR}${PREFIX}/sbin
	${INSTALL_PROGRAM} ${CBFSTOOL_PATH}/cbfstool ${STAGEDIR}${PREFIX}/sbin
.endif

.include <bsd.port.mk>
