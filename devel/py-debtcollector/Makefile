PORTNAME=	debtcollector
PORTVERSION=	3.0.0
CATEGORIES=	devel python
MASTER_SITES=	PYPI
PKGNAMEPREFIX=	${PYTHON_PKGNAMEPREFIX}

MAINTAINER=	dave@gufi.org
COMMENT=	Collection of Python deprecation patterns and strategies
WWW=		https://docs.openstack.org/debtcollector/latest/

LICENSE=	APACHE20
LICENSE_FILE=	${WRKSRC}/LICENSE

BUILD_DEPENDS=	${PYTHON_PKGNAMEPREFIX}pbr>=1.6:devel/py-pbr@${PY_FLAVOR}
RUN_DEPENDS=	${PYTHON_PKGNAMEPREFIX}six>0:devel/py-six@${PY_FLAVOR} \
		${PYTHON_PKGNAMEPREFIX}wrapt>0:devel/py-wrapt@${PY_FLAVOR} \
		${PYTHON_PKGNAMEPREFIX}funcsigs>0:devel/py-funcsigs@${PY_FLAVOR}

USES=		python
USE_PYTHON=	autoplist concurrent distutils

NO_ARCH=	yes

.include <bsd.port.mk>
