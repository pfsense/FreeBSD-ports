PORTNAME=	alive-progress
PORTVERSION=	3.2.0
CATEGORIES=	misc
MASTER_SITES=	PYPI
PKGNAMEPREFIX=	${PYTHON_PKGNAMEPREFIX}

MAINTAINER=	sunpoet@FreeBSD.org
COMMENT=	Enhanced Python Progress Bar
WWW=		https://github.com/rsalmei/alive-progress

LICENSE=	MIT
LICENSE_FILE=	${WRKSRC}/LICENSE

DEPRECATED=	Depends on expired textproc/py-grapheme
EXPIRATION_DATE=2025-06-01

BUILD_DEPENDS=	${PYTHON_PKGNAMEPREFIX}setuptools>=0:devel/py-setuptools@${PY_FLAVOR} \
		${PYTHON_PKGNAMEPREFIX}wheel>=0:devel/py-wheel@${PY_FLAVOR}
RUN_DEPENDS=	${PYTHON_PKGNAMEPREFIX}about-time>=4.2.1:devel/py-about-time@${PY_FLAVOR} \
		${PYTHON_PKGNAMEPREFIX}grapheme>=0.6.0<0.6.0_99:textproc/py-grapheme@${PY_FLAVOR}

USES=		python:3.9+
USE_PYTHON=	autoplist concurrent pep517

NO_ARCH=	yes

.include <bsd.port.mk>
