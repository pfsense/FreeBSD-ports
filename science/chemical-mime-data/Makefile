PORTNAME=	chemical-mime-data
PORTVERSION=	0.1.94
PORTREVISION=	11
CATEGORIES=	science
MASTER_SITES=	SF/chemical-mime/${PORTNAME}/${PORTVERSION}

MAINTAINER=	ports@FreeBSD.org
COMMENT=	Collection of chemical MIME types for UNIX desktops
WWW=		http://chemical-mime.sourceforge.net/

USES=		gettext-tools gmake gnome localbase:ldflags pathfix pkgconfig \
		shared-mime-info  tar:bzip2
USE_GNOME=	gnomemimedata intlhack librsvg2:build libxslt:build
GNU_CONFIGURE=	yes
CONFIGURE_ARGS=	--disable-update-database

post-patch:
	@${REINPLACE_CMD} -e 's|*-*-solaris*|*| ; s|rsvg|rsvg-convert|g' ${WRKSRC}/configure
	@${REINPLACE_CMD} -e 's|-f png $$< |-f png $$< > |' ${WRKSRC}/icons/hicolor/Makefile.in

.include <bsd.port.mk>
