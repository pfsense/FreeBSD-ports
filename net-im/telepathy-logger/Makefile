PORTNAME=	telepathy-logger
PORTVERSION=	0.8.2
PORTREVISION=	4
CATEGORIES=	net-im
MASTER_SITES=	http://telepathy.freedesktop.org/releases/${PORTNAME}/

MAINTAINER=	gnome@FreeBSD.org
COMMENT=	Logger utility library for the Telepathy framework
WWW=		https://telepathy.freedesktop.org/

LICENSE=	LGPL21+
LICENSE_FILE=	${WRKSRC}/COPYING

LIB_DEPENDS=	libdbus-glib-1.so:devel/dbus-glib \
		libtelepathy-glib.so:net-im/telepathy-glib

USES=		gettext-tools gmake gnome libtool pathfix pkgconfig \
		python:build sqlite tar:bzip2
GNU_CONFIGURE=	yes
USE_LDCONFIG=	yes
USE_GNOME=	glib20 intltool libxml2 \
		libxslt:build introspection:build
GLIB_SCHEMAS=	org.freedesktop.Telepathy.Logger.gschema.xml
INSTALL_TARGET=	install-strip
MAKE_ENV=	PYTHONDONTWRITEBYTECODE=1

post-patch:
	@${REINPLACE_CMD} -e 's|/usr/bin/python|/usr/bin/env python|g' \
		${WRKSRC}/tools/*.py

.include <bsd.port.mk>
