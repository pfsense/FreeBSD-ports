PORTNAME=	pynn
PORTVERSION=	0.12.3
PORTREVISION=	1
CATEGORIES=	science python
MASTER_SITES=	PYPI
PKGNAMEPREFIX=	${PYTHON_PKGNAMEPREFIX}
DISTNAME=	PyNN-${PORTVERSION}

MAINTAINER=	wen@FreeBSD.org
COMMENT=	Simulator-independent language for building neuronal network models
WWW=		https://neuralensemble.org/PyNN

LICENSE=	CeCILL
LICENSE_NAME=	CeCILL Free Software License Version 2.0
LICENSE_FILE=	${WRKSRC}/LICENSE
LICENSE_PERMS=	dist-mirror dist-sell pkg-mirror pkg-sell auto-accept

BUILD_DEPENDS=	${PYNUMPY}
RUN_DEPENDS=	${PYNUMPY}

WRKSRC=		${WRKDIR}/${PORTNAME}-${PORTVERSION}

NO_ARCH=	yes

USES=		python
USE_PYTHON=	autoplist distutils
PYDISTUTILS_PKGNAME=	PyNN

.include <bsd.port.mk>
