PORTNAME=	sawfish
DISTVERSION=	1.13.0
PORTREVISION=	3
CATEGORIES=	x11-wm
MASTER_SITES=	http://download.tuxfamily.org/sawfish/
DISTNAME=	${PORTNAME}_${PORTVERSION}

MAINTAINER=	danfe@FreeBSD.org
COMMENT=	Extensible window manager using Lisp-based scripting language
WWW=		https://sawfish.tuxfamily.org/

LICENSE=	ART20 GPLv2
LICENSE_COMB=	multi

BUILD_DEPENDS=	rep-gtk2>=0.90:x11-toolkits/rep-gtk2
LIB_DEPENDS=	libfontconfig.so:x11-fonts/fontconfig \
		libfreetype.so:print/freetype2 \
		libgmp.so:math/gmp \
		libharfbuzz.so:print/harfbuzz \
		librep.so:lang/librep
RUN_DEPENDS=	rep-gtk2>=0.90:x11-toolkits/rep-gtk2

USES=		gmake gnome libtool makeinfo pathfix pkgconfig tar:xz xorg
USE_XORG=	ice sm x11 xft xinerama xrandr xtst xrender xext
USE_GNOME=	cairo gdkpixbuf gdkpixbuf2xlib gtk20
GNU_CONFIGURE=	yes
GNU_CONFIGURE_MANPREFIX=	${PREFIX}/share
CONFIGURE_ARGS=	--localstatedir=${PREFIX}/share \
		--datadir=${PREFIX}/share

INFO=		sawfish

OPTIONS_DEFINE=	NLS
OPTIONS_SUB=	yes

NLS_USES=	gettext
NLS_CONFIGURE_OFF=	--without-nls

post-extract:
	@${RM} ${WRKSRC}/themes/StyleTab/Flat/.directory

post-patch:
	@${REINPLACE_CMD} -e \
		'/dnsdomainname/d' ${WRKSRC}/build-info
	@${REINPLACE_CMD} -e \
		's|prog_name|program_name|g' ${WRKSRC}/src/main.c
	@${REINPLACE_CMD} -e \
		'/LC_ALL=C tar/s|-c|&f -|' ${WRKSRC}/themes/Makefile.in

post-build:
	${STRIP_CMD} ${WRKSRC}/src/.libs/*.so \
		${WRKSRC}/src/gtk-style

.include <bsd.port.mk>
