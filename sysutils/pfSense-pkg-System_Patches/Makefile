# $FreeBSD$

PORTNAME=	pfSense-pkg-System_Patches
PORTVERSION=	2.0
PORTREVISION=	6
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense package System_Patches

LICENSE=	APACHE20

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/patches
	${MKDIR} ${STAGEDIR}${PREFIX}/www
	${MKDIR} ${STAGEDIR}${PREFIX}/bin
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/systempatches.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/system_patches.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/system_patches_edit.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} -m 755 ${FILESDIR}${PREFIX}/bin/apply_patches.php \
		${STAGEDIR}${PREFIX}/bin
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/patches.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/patches/manifest.inc \
		${STAGEDIR}${PREFIX}/pkg/patches
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/patches/12827workaround.patch \
		${STAGEDIR}${PREFIX}/pkg/patches
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/patches/3ade222beb2cae2c0681ed69d4e5a0c82c6303f9.patch \
		${STAGEDIR}${PREFIX}/pkg/patches
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/patches/3b50f7656967fbb4daa869a7ae6d18bc5ab6eec3.patch \
		${STAGEDIR}${PREFIX}/pkg/patches
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/patches/4612721800a1b25bb1fb2d4d7c4ceea6f44f208e.patch \
		${STAGEDIR}${PREFIX}/pkg/patches
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/patches/72ea2b69cc111d4bc8ebf1ccf1e1529923c5b88a.patch \
		${STAGEDIR}${PREFIX}/pkg/patches
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/patches/78ce96a9af3b2ab5159ef6623078bfc4b15f8a89.patch \
		${STAGEDIR}${PREFIX}/pkg/patches
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/patches/8ddf2b5a999772754080825f07acf9b6326f1f04.patch \
		${STAGEDIR}${PREFIX}/pkg/patches
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/patches/a9bdbd97984ff2ddefbceb2fe062fbe3a1c42d88.patch \
		${STAGEDIR}${PREFIX}/pkg/patches
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/patches/afcc0e9c97c1993ae6b95f886665fcb4375d26c7.patch \
		${STAGEDIR}${PREFIX}/pkg/patches
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/patches/b2fce958b8a2e80754b49eb3d17795757df6a76c.patch \
		${STAGEDIR}${PREFIX}/pkg/patches
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/patches/b5360f49fb3c1fdc36ebf13c20b68d4ff1e15fe6.patch \
		${STAGEDIR}${PREFIX}/pkg/patches
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/patches/bdffb77d1aa21770b23ef408ad9fba79d0825ec5.patch \
		${STAGEDIR}${PREFIX}/pkg/patches
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/patches/bf21f67bbe2d1694ad1ad72728623dded9ace426.patch \
		${STAGEDIR}${PREFIX}/pkg/patches
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/patches/c416f6fab10f149b15a352dfb609f86a98f6103d.patch \
		${STAGEDIR}${PREFIX}/pkg/patches
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/patches/fd30b9eda98900eb5f1a0c37d600ef4deaf582ad.patch \
		${STAGEDIR}${PREFIX}/pkg/patches
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
