PORTNAME=	packageurl-python
DISTVERSION=	0.16.0
PORTREVISION=	1
CATEGORIES=	textproc python
MASTER_SITES=	PYPI
PKGNAMEPREFIX=	${PYTHON_PKGNAMEPREFIX}
DISTNAME=	packageurl_python-${DISTVERSION}

MAINTAINER=	kai@FreeBSD.org
COMMENT=	Parser and builder for package URLs (aka purl)
WWW=		https://github.com/package-url/packageurl-python

LICENSE=	MIT
LICENSE_FILE=	${WRKSRC}/mit.LICENSE

USES=		python
USE_PYTHON=	autoplist distutils pytest

TEST_ENV=	PYTHONPATH=${STAGEDIR}${PYTHONPREFIX_SITELIBDIR}

NO_ARCH=	yes

.include <bsd.port.mk>
