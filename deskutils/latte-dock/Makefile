PORTNAME=	latte-dock
DISTVERSION=	0.10.9
PORTREVISION=	1
CATEGORIES=	deskutils kde
MASTER_SITES=	KDE/stable/latte-dock

MAINTAINER=	kde@FreeBSD.org
COMMENT=	Launcher for Qt and Plasma desktops
WWW=		https://invent.kde.org/plasma/latte-dock

LICENSE_COMB=	multi
LICENSE=	GPLv2+ LGPL20+ LGPL21 LGPL3

LIB_DEPENDS=	libxcb-util.so:x11/xcb-util \
		libksgrd.so:sysutils/plasma5-libksysguard

USES=		cmake compiler:c++11-lang gettext kde:5 qt:5 tar:xz xorg
USE_KDE=	activities archive attica auth codecs completion config \
		configwidgets coreaddons crash dbusaddons globalaccel \
		guiaddons i18n iconthemes jobwidgets kdeclarative kio \
		newstuff notifications package plasma-framework \
		service solid wayland widgetsaddons windowsystem xmlgui \
		ecm:build
USE_QT=		concurrent core dbus declarative gui network widgets \
		x11extras xml \
		buildtools:build qmake:build
USE_XORG=	ice sm x11 xcb xext

.include <bsd.port.mk>
