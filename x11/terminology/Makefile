PORTNAME=	terminology
DISTVERSION=	1.14.0
CATEGORIES=	x11 enlightenment
MASTER_SITES=	http://download.enlightenment.org/rel/apps/${PORTNAME}/
DIST_SUBDIR=	enlightenment

MAINTAINER=	enlightenment@FreeBSD.org
COMMENT=	EFL Terminal Emulator
WWW=		https://www.enlightenment.org/

LICENSE=	BSD2CLAUSE
LICENSE_FILE=	${WRKSRC}/COPYING

LIB_DEPENDS=	libefl.so:devel/efl

USES=		compiler:c11 cpe meson pkgconfig tar:xz python
BINARY_ALIAS=	python3=${PYTHON_CMD}
CPE_VENDOR=	enlightenment

OPTIONS_DEFINE=		NLS
OPTIONS_SUB=		yes

NLS_USES=		gettext
NLS_MESON_TRUE=		nls
NLS_LDFLAGS=		-L${LOCALBASE}/lib -lintl

.include <bsd.port.mk>
