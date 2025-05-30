PORTNAME=	enchant
PORTVERSION=	1.6.0
PORTREVISION=	12
CATEGORIES=	textproc gnome
MASTER_SITES=	http://www.nl.abisource.com/downloads/${PORTNAME}/${PORTVERSION}/
DIST_SUBDIR=	gnome2

MAINTAINER=	gnome@FreeBSD.org
COMMENT=	Dictionary/spellchecking framework
WWW=		https://abiword.github.io/enchant/

LICENSE=	LGPL21
LICENSE_FILE=	${WRKSRC}/COPYING.LIB

USES=		gmake gnome libtool pathfix pkgconfig
USE_CXXSTD=	c++11
USE_GNOME=	glib20
USE_LDCONFIG=	yes
GNU_CONFIGURE=	yes
CONFIGURE_ARGS=	--disable-uspell \
		--disable-voikko
INSTALL_TARGET=	install-strip
GNU_CONFIGURE_MANPREFIX=${PREFIX}/share

CPPFLAGS+=	-I${LOCALBASE}/include
LDFLAGS+=	-L${LOCALBASE}/lib

OPTIONS_DEFAULT=HUNSPELL
OPTIONS_MULTI=	SPELL
OPTIONS_MULTI_SPELL=ASPELL HSPELL HUNSPELL ISPELL ZEMBEREK
OPTIONS_SUB=	yes
HSPELL_DESC=	Spell checking via Hspell
HUNSPELL_DESC=	Spell checking via Hunspell
ZEMBEREK_DESC=	Spell checking via Zemberek

ASPELL_LIB_DEPENDS=	libaspell.so:textproc/aspell
ASPELL_CONFIGURE_ON=	--with-aspell-prefix=${LOCALBASE}
ASPELL_CONFIGURE_ENABLE=	aspell

HSPELL_BUILD_DEPENDS=	${LOCALBASE}/lib/libhspell.a:hebrew/hspell
HSPELL_CONFIGURE_ON=	--with-hspell-prefix=${LOCALBASE}
HSPELL_CONFIGURE_ENABLE=	hspell

HUNSPELL_LIB_DEPENDS=	libhunspell-1.7.so:textproc/hunspell
HUNSPELL_CONFIGURE_ON=	--with-system-myspell=yes \
			--with-myspell-dir=${LOCALBASE}/share/hunspell
HUNSPELL_CONFIGURE_ENABLE=	myspell

ISPELL_RUN_DEPENDS=	ispell:textproc/aspell-ispell
ISPELL_CONFIGURE_ENABLE=	ispell

ZEMBEREK_LIB_DEPENDS=	libdbus-1.so:devel/dbus \
			libdbus-glib-1.so:devel/dbus-glib
ZEMBEREK_CONFIGURE_ENABLE=	zemberek

post-patch:
	@${REINPLACE_CMD} -e 's|MKDIRPROG-mkdir|MKDIRPROG-mkdir -p|g' \
		${WRKSRC}/install-sh

post-install:
	@cd ${STAGEDIR}${PREFIX}/share/enchant/ && \
		${MV} enchant.ordering enchant1.ordering

.include <bsd.port.mk>
