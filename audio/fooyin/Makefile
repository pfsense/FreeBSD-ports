PORTNAME=	fooyin
DISTVERSIONPREFIX=	v
DISTVERSION=	0.10.1
PORTREVISION=	1
CATEGORIES=	audio multimedia

PATCH_SITES=	https://github.com/fooyin/fooyin/commit/
PATCHFILES=	4ac26409f891154e47ac8e7509bcbaa8c4f6828b.patch:-p1 \
		b105fe9e1946999e47fb06bc9ecf9a6a5621b4b2.patch:-p1 \
		4270f5f2262e621588625de1db0b970723746f85.patch:-p1 \
		79037324ecdcae29514bc45cdd7a931f51dd50d3.patch:-p1 \
		fbc8686eed1f6dd56aa6811bb512ef5048452e32.patch:-p1 \
		1ca41120615e29b3c167a3f5a5d710e39e011e8c.patch:-p1 \
		770425f2a1768009907e85d8cc8c900c386a4251.patch:-p1

MAINTAINER=	diizzy@FreeBSD.org
COMMENT=	foobar2000 inspired audio player
WWW=		https://github.com/fooyin/fooyin

LICENSE=	GPLv3
LICENSE_FILE=	${WRKSRC}/COPYING

NOT_FOR_ARCHS=  armv7 i386 powerpc
NOT_FOR_ARCHS_REASON=	Fails to build on 32-bit platforms with various errors

LIB_DEPENDS=	libavformat.so:multimedia/ffmpeg \
		libgme.so:audio/libgme \
		libicui18n.so:devel/icu \
		libkdsingleapplication-qt6.so:devel/kdsingleapplication \
		libQCoro6Core.so:devel/qcoro \
		libtag.so:audio/taglib

USES=		cmake compiler:c++23-lang desktop-file-utils gl libarchive \
		pkgconfig qt:6 sdl
USE_GL=		opengl
USE_QT=		base sqldriver-sqlite svg tools:build
USE_SDL=	sdl2

USE_GITHUB=	yes

CXXFLAGS+=	-fexperimental-library

CMAKE_OFF=	BUILD_ALSA \
		BUILD_CCACHE \
		CMAKE_CXX_EXTENSIONS
CMAKE_ON=	CMAKE_DISABLE_FIND_PACKAGE_ALSA \
		CMAKE_DISABLE_FIND_PACKAGE_Ebur128 \
		CMAKE_DISABLE_FIND_PACKAGE_OpenMpt \
		CMAKE_DISABLE_FIND_PACKAGE_PipeWire \
		CMAKE_DISABLE_FIND_PACKAGE_SndFile \
		CMAKE_DISABLE_FIND_PACKAGE_SoXR

.include <bsd.port.mk>
