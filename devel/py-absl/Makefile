PORTNAME=	absl
DISTVERSION=	0.7.1
PORTREVISION=	2
CATEGORIES=	devel python
MASTER_SITES=	PYPI
PKGNAMEPREFIX=	${PYTHON_PKGNAMEPREFIX}
DISTNAME=	absl-py-${PORTVERSION}

MAINTAINER=	amzo1337@gmail.com
COMMENT=	Abseil Python Common Libraries
WWW=		https://github.com/abseil/abseil-py

LICENSE=	APACHE20
LICENSE_FILE=	${WRKSRC}/LICENSE

DEPRECATED=	Use devel/py-absl-py
EXPIRATION_DATE=2025-03-31

RUN_DEPENDS=	${PYTHON_PKGNAMEPREFIX}six>0:devel/py-six@${PY_FLAVOR}

USES=		python
USE_PYTHON=	distutils autoplist

NO_ARCH=	yes

.include <bsd.port.mk>
