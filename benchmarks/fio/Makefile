PORTNAME=	fio
DISTVERSION=	3.39
CATEGORIES=	benchmarks
MASTER_SITES=	https://brick.kernel.dk/snaps/

MAINTAINER=	krion@FreeBSD.org
COMMENT=	Flexible IO tester
WWW=		https://git.kernel.dk/cgit/fio/

LICENSE=	GPLv2
LICENSE_FILE=	${WRKSRC}/COPYING

USES=		compiler:c11 gmake pkgconfig tar:bzip2

HAS_CONFIGURE=		yes
CONFIGURE_ARGS=		--disable-http \
			--disable-native \
			--disable-optimizations
MAKE_ARGS=		DEBUGFLAGS= \
			OPTFLAGS= \
			V=1
TEST_TARGET=		test
CONFLICTS_INSTALL=	py*-Fiona

OPTIONS_DEFINE=	EXAMPLES GNUPLOT LIBNFS
GNUPLOT_DESC=	Support for plotting graphs
LIBNFS_DESC=	Enable NFS support using libnfs

GNUPLOT_BUILD_DEPENDS=	${LOCALBASE}/bin/gnuplot:math/gnuplot
LIBNFS_LIB_DEPENDS=	libnfs.so:net/libnfs

LIBNFS_CONFIGURE_ENABLE=	libnfs

do-install:
	${INSTALL_PROGRAM} ${WRKSRC}/fio ${STAGEDIR}${PREFIX}/bin
	${INSTALL_SCRIPT} ${WRKSRC}/tools/fio_generate_plots ${STAGEDIR}${PREFIX}/bin
	${INSTALL_MAN} ${WRKSRC}/fio.1 ${STAGEDIR}${PREFIX}/share/man/man1/
	${INSTALL_MAN} ${WRKSRC}/tools/fio_generate_plots.1 ${STAGEDIR}${PREFIX}/share/man/man1
	${MKDIR} ${STAGEDIR}${PREFIX}/include/${PORTNAME}
	${MKDIR} ${STAGEDIR}${PREFIX}/include/${PORTNAME}/compiler
	${MKDIR} ${STAGEDIR}${PREFIX}/include/${PORTNAME}/lib
	${MKDIR} ${STAGEDIR}${PREFIX}/include/${PORTNAME}/arch
	${MKDIR} ${STAGEDIR}${PREFIX}/include/${PORTNAME}/os
	${MKDIR} ${STAGEDIR}${PREFIX}/include/${PORTNAME}/oslib
	${INSTALL_DATA} ${WRKSRC}/compiler/compiler.h ${STAGEDIR}${PREFIX}/include/${PORTNAME}/compiler
	${INSTALL_DATA} ${WRKSRC}/os/os.h ${STAGEDIR}${PREFIX}/include/${PORTNAME}/os
	${INSTALL_DATA} ${WRKSRC}/os/os-freebsd.h ${STAGEDIR}${PREFIX}/include/${PORTNAME}/os
	${INSTALL_DATA} ${WRKSRC}/oslib/getopt.h ${STAGEDIR}${PREFIX}/include/${PORTNAME}/oslib
	${INSTALL_DATA} ${WRKSRC}/lib/*.h ${STAGEDIR}${PREFIX}/include/${PORTNAME}/lib
	@(cd ${WRKSRC}/arch/ && ${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}/include/${PORTNAME}/arch)
	${INSTALL_DATA} ${WRKSRC}/*.h ${STAGEDIR}${PREFIX}/include/${PORTNAME}

do-install-EXAMPLES-on:
	@${MKDIR} ${STAGEDIR}${EXAMPLESDIR}
	@(cd ${WRKSRC}/examples && ${COPYTREE_SHARE} \* ${STAGEDIR}${EXAMPLESDIR})

.include <bsd.port.mk>
