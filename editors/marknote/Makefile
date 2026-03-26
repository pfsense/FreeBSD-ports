PORTNAME=	marknote
PORTVERSION=	1.5.2
CATEGORIES=	editors kde
MASTER_SITES=	KDE/stable/marknote/

MAINTAINER=	kde@FreeBSD
COMMENT=	Simple markdown note management app
WWW=		https://apps.kde.org/marknote/

LIB_DEPENDS=	libmd4c.so:textproc/md4c

USES=		cmake gettext-tools kde:6 pkgconfig qt:6 tar:xz
USE_KDE=	colorscheme config coreaddons crash i18n iconthemes \
		kirigami2 kirigami-addons notifications windowsystem \
		syntaxhighlighting:run \
		ecm:build
# KDE PIM
USE_KDE+=	mime
USE_QT=		base declarative svg

.include <bsd.port.mk>
