PORTNAME=	multimc
DISTVERSION=	0.6.11
CATEGORIES=	games java

MAINTAINER=	me@tsundoku.ne.jp
COMMENT=	Minecraft launcher with the ability to manage multiple instances
WWW=		https://multimc.org

LICENSE=	APACHE20
LICENSE_FILE=	${WRKSRC}/COPYING.md

RUN_DEPENDS=	lwjgl>=2.9.3:games/lwjgl

USES=		cmake compiler:c++14-lang java qt:5
USE_GITHUB=	yes
GH_ACCOUNT=	MultiMC
GH_PROJECT=	MultiMC5
GH_TUPLE=	MultiMC:libnbtplusplus:508eda8:libnbtplusplus/libraries/libnbtplusplus \
		MultiMC:quazip:3691d57:quazip/libraries/quazip
USE_LDCONFIG=	yes
USE_QT=		core concurrent gui network testlib:build widgets xml \
		buildtools:build qmake:build

CMAKE_ARGS=	-DMultiMC_LAYOUT="lin-system"
CMAKE_OFF=	MultiMC_UPDATER
CXXFLAGS+=	-Wno-inconsistent-missing-override \
		-Wno-delete-non-virtual-dtor \
		-DLWJGL_DIR='\"${LOCALBASE}/lib/lwjgl\"'

post-install:
	${STRIP_CMD} ${STAGEDIR}${PREFIX}/lib/libMultiMC_*.so

.include <bsd.port.mk>
