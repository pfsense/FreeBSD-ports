PORTNAME=	gtk-engines2
PORTVERSION=	2.20.2
PORTREVISION=	4
CATEGORIES=	x11-themes
MASTER_SITES=	GNOME
DISTNAME=	${PORTNAME:S/2$//}-${PORTVERSION}
DIST_SUBDIR=	gnome2

MAINTAINER=	gnome@FreeBSD.org
COMMENT=	Theme engine for the GTK-2.0 toolkit
WWW=		https://www.gtk.org/

CONFLICTS=	gtk-smooth-engine clearlooks

USE_GNOME=	gtk20 intlhack
USES=		gettext gmake gnome libtool pathfix pkgconfig tar:bzip2
USE_LDCONFIG=	yes
CPPFLAGS+=	-I${LOCALBASE}/include
LDFLAGS+=	-L${LOCALBASE}/lib
GNU_CONFIGURE=	yes
INSTALL_TARGET=	install-strip

OPTIONS_DEFINE=	ANIMATION
ANIMATION_DESC=	Experimental animated progressbars

ANIMATION_CONFIGURE_ENABLE=	animation

post-patch:
	@${REINPLACE_CMD} -e 's|glib.*\.h>|glib.h>|g' \
		${WRKSRC}/engines/clearlooks/src/animation.c

post-install:
	@(cd ${WRKSRC}/po && ${MAKE_CMD} install DESTDIR=${STAGEDIR})

.include <bsd.port.mk>
