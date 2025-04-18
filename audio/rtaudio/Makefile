PORTNAME=	rtaudio
DISTVERSION=	6.0.1
PORTREVISION=	1
CATEGORIES=	audio

MAINTAINER=	yuri@FreeBSD.org
COMMENT=	C++ classes that provide a common API for realtime audio input/output
WWW=		https://github.com/thestk/rtaudio

LICENSE=	MIT

USES=		cmake:testing compiler:c++11-lang pkgconfig
USE_LDCONFIG=	yes

USE_GITHUB=	yes
GH_ACCOUNT=	thestk

CXXFLAGS+=	-I${STAGEDIR}${PREFIX}/include/rtaudio # hack for tests

CMAKE_TESTING_ON=	RTAUDIO_BUILD_TESTING

OPTIONS_MULTI=			AUDIO_BACKEND
OPTIONS_MULTI_AUDIO_BACKEND=	ALSA JACK OSS PULSEAUDIO
OPTIONS_DEFAULT=		PULSEAUDIO # default should probably be OSS, but it appears to be broken

AUDIO_BACKEND_DESC=		Audio backend

ALSA_CMAKE_BOOL=		RTAUDIO_API_ALSA
ALSA_LIB_DEPENDS=		libasound.so:audio/alsa-lib

JACK_CMAKE_BOOL=		RTAUDIO_API_JACK
JACK_LDFLAGS=			`pkg-config --libs jack` # workaround for https://github.com/thestk/rtaudio/issues/357
JACK_LIB_DEPENDS=		libjack.so:audio/jack

OSS_CMAKE_BOOL=			RTAUDIO_API_OSS # OSS option is possibly broken, see https://github.com/thestk/rtaudio/issues/359

PULSEAUDIO_CMAKE_BOOL=		RTAUDIO_API_PULSE # is PULSEAUFIO broken? see https://github.com/thestk/rtaudio/issues/361
PULSEAUDIO_USES=		localbase:ldflags # workaround for https://github.com/thestk/rtaudio/issues/360
PULSEAUDIO_LIB_DEPENDS=		libpulse.so:audio/pulseaudio

.include <bsd.port.mk>
