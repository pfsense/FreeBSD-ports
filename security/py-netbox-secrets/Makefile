PORTNAME=	netbox-secrets
DISTVERSION=	2.3.0
CATEGORIES=	security python
MASTER_SITES=	PYPI
PKGNAMEPREFIX=	${PYTHON_PKGNAMEPREFIX}
DISTNAME=	netbox_secrets-${DISTVERSION}

MAINTAINER=	kai@FreeBSD.org
COMMENT=	Secret store for NetBox
WWW=		https://github.com/Onemind-Services-LLC/netbox-secrets

LICENSE=	APACHE20
LICENSE_FILE=	${WRKSRC}/LICENSE.md

BUILD_DEPENDS=	${PY_SETUPTOOLS} \
		${PYTHON_PKGNAMEPREFIX}wheel>0:devel/py-wheel@${PY_FLAVOR}
RUN_DEPENDS=	${PYTHON_PKGNAMEPREFIX}pycryptodome>0:security/py-pycryptodome@${PY_FLAVOR}

USES=		python
USE_PYTHON=	autoplist pep517

NO_ARCH=	yes

SUB_FILES=	pkg-message
SUB_LIST=	PYTHON_VER=${PYTHON_VER}

.include <bsd.port.mk>
