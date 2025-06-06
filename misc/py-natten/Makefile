PORTNAME=	natten
DISTVERSIONPREFIX=	v
DISTVERSION=	0.17.5
CATEGORIES=	misc python
#MASTER_SITES=	PYPI # no tests
PKGNAMEPREFIX=	${PYTHON_PKGNAMEPREFIX}

MAINTAINER=	yuri@FreeBSD.org
COMMENT=	Neighborhood Attention Extension for PyTorch
WWW=		https://shi-labs.com/natten/ \
		https://github.com/SHI-Labs/NATTEN

LICENSE=	MIT
LICENSE_FILE=	${WRKSRC}/LICENSE

BUILD_DEPENDS=	cmake:devel/cmake-core \
		pybind11>0:devel/pybind11 \
		${PYTHON_PKGNAMEPREFIX}pytorch>=2.0.0:misc/py-pytorch@${PY_FLAVOR}
RUN_DEPENDS=	${PYTHON_PKGNAMEPREFIX}packaging>=0:devel/py-packaging@${PY_FLAVOR} \
		${PYTHON_PKGNAMEPREFIX}pytorch>=2.0.0:misc/py-pytorch@${PY_FLAVOR}

USES=		localbase python
USE_PYTHON=	distutils autoplist pytest

USE_GITHUB=	yes
GH_ACCOUNT=	SHI-Labs
GH_PROJECT=	NATTEN

TEST_ENV=	${MAKE_ENV} PYTHONPATH=${STAGEDIR}${PYTHONPREFIX_SITELIBDIR}

post-install:
	# strip binary
	@${STRIP_CMD} ${STAGEDIR}${PYTHON_SITELIBDIR}/natten/libnatten${PYTHON_EXT_SUFFIX}.so
	# delete stray files, see https://github.com/SHI-Labs/NATTEN/issues/152
	@${RM} -r \
		${STAGEDIR}${PYTHON_SITELIBDIR}/CMakeFiles \
		${STAGEDIR}${PYTHON_SITELIBDIR}/CMakeCache.txt \
		${STAGEDIR}${PYTHON_SITELIBDIR}/cmake_install.cmake \
		${STAGEDIR}${PYTHON_SITELIBDIR}/Makefile

# tests as of 0.17.4:
# Results (35.54s):
#       24 passed
#        2 failed
#          - .:?
#          - .:?
#       12 xfailed
#       59 skipped

.include <bsd.port.mk>
