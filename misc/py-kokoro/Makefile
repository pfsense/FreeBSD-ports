PORTNAME=	kokoro
DISTVERSION=	0.9.4
CATEGORIES=	misc python # machine-learning
MASTER_SITES=	PYPI
PKGNAMEPREFIX=	${PYTHON_PKGNAMEPREFIX}

MAINTAINER=	yuri@FreeBSD.org
COMMENT=	Text-to-speech inference library for Kokoro-82M model
WWW=		https://github.com/hexgrad/kokoro

LICENSE=	APACHE20
LICENSE_FILE=	${WRKSRC}/LICENSE

BUILD_DEPENDS=	${PYTHON_PKGNAMEPREFIX}hatchling>0:devel/py-hatchling@${PY_FLAVOR}
RUN_DEPENDS=	${PYTHON_PKGNAMEPREFIX}huggingface-hub>0:misc/py-huggingface-hub@${PY_FLAVOR} \
		${PYTHON_PKGNAMEPREFIX}loguru>0:devel/py-loguru@${PY_FLAVOR} \
		${PYTHON_PKGNAMEPREFIX}misaki>=0.9.4:misc/py-misaki@${PY_FLAVOR} \
		${PYTHON_PKGNAMEPREFIX}num2words>0:devel/py-num2words@${PY_FLAVOR} \
		${PYNUMPY} \
		${PYTHON_PKGNAMEPREFIX}pytorch>0:misc/py-pytorch@${PY_FLAVOR} \
		${PYTHON_PKGNAMEPREFIX}spacy>0:textproc/py-spacy@${PY_FLAVOR} \
		${PYTHON_PKGNAMEPREFIX}transformers>=0:misc/py-transformers@${PY_FLAVOR}
TEST_DEPENDS=	mpv:multimedia/mpv

USES=		python
USE_PYTHON=	autoplist concurrent pep517

NO_ARCH=	yes

TEST_ENV=	${MAKE_ENV} PYTHONPATH=${STAGEDIR}${PYTHONPREFIX_SITELIBDIR}

do-test:
	@cd ${WRKSRC} && \
		${SETENV} ${TEST_ENV} ${PYTHON_CMD} ${FILESDIR}/example.py && \
		mpv 0.wav

.include <bsd.port.mk>
