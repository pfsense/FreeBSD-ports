PORTNAME=	twiggy
PORTVERSION=	0.5.1
PORTREVISION=	1
CATEGORIES=	devel python
MASTER_SITES=	PYPI
PKGNAMEPREFIX=	${PYTHON_PKGNAMEPREFIX}
DISTNAME=	Twiggy-${PORTVERSION}

MAINTAINER=	bofh@FreeBSD.org
COMMENT=	Pythonic logger
WWW=		https://github.com/wearpants/twiggy/

LICENSE=	BSD3CLAUSE
LICENSE_FILE=	${WRKSRC}/LICENSE

TEST_DEPENDS=	${PYTHON_PKGNAMEPREFIX}pytest>0:devel/py-pytest@${PY_FLAVOR}

USES=		python
USE_PYTHON=	autoplist distutils
NO_ARCH=	yes

do-test:
	@cd ${WRKSRC} && ${SETENV} TWIGGY_UNDER_TEST=1 \
		${PYTHON_CMD} ${PYDISTUTILS_SETUP} test

.include <bsd.port.mk>
