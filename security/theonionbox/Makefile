PORTNAME=	theonionbox
DISTVERSION=	4.3.1
PORTREVISION=	4
CATEGORIES=	security net python
MASTER_SITES=	PYPI

MAINTAINER=	yuri@FreeBSD.org
COMMENT=	Web interface to monitor Tor node operation
WWW=		https://github.com/ralphwetzel/theonionbox

LICENSE=	MIT

RUN_DEPENDS=	${PYTHON_PKGNAMEPREFIX}APScheduler>=3.4:devel/py-apscheduler@${PY_FLAVOR} \
		${PYTHON_PKGNAMEPREFIX}bottle>=0.12.13:www/py-bottle@${PY_FLAVOR} \
		${PYTHON_PKGNAMEPREFIX}psutil>=5.4.0:sysutils/py-psutil@${PY_FLAVOR} \
		${PYTHON_PKGNAMEPREFIX}pysocks>=1.6.7:net/py-pysocks@${PY_FLAVOR} \
		${PYTHON_PKGNAMEPREFIX}requests>2.18:www/py-requests@${PY_FLAVOR} \
		${PYTHON_PKGNAMEPREFIX}stem>=1.5.4:security/py-stem@${PY_FLAVOR} \
		${PYTHON_PKGNAMEPREFIX}tzlocal>=1.5:devel/py-tzlocal@${PY_FLAVOR} \
		${PYTHON_PKGNAMEPREFIX}urllib3>=1.21.1,1<1.27,1:net/py-urllib3@${PY_FLAVOR}
RUN_DEPENDS+=	${PYTHON_PKGNAMEPREFIX}sqlite3>=0:databases/py-sqlite3@${PY_FLAVOR} # workaround for bug#230613: missing internal sqlite3 module in python

USES=		python
USE_PYTHON=	distutils autoplist noflavors

NO_ARCH=	yes

POST_PLIST=	correct-plist

OPTIONS_DEFINE=	DOCS

post-patch:
	@${MV} ${WRKSRC}/FreeBSD/${PORTNAME}.sh ${WRKSRC}/FreeBSD/${PORTNAME}
	@${REINPLACE_CMD} ' \
		s|"/your/path/to/${PORTNAME}"|"${PREFIX}"|; \
		s|${PORTNAME}_dir}/${PORTNAME}\.py|${PORTNAME}_dir}/bin/${PORTNAME}|; \
		s|/config/${PORTNAME}.cfg|/etc/${PORTNAME}/${PORTNAME}|; \
		s|"/usr/local/bin/python"|"${PYTHON_CMD}"|' \
		${WRKSRC}/FreeBSD/${PORTNAME}

post-patch-DOCS-off:
	@${REINPLACE_CMD} "s|('share/doc/${PORTNAME}', \['docs/\*\.\*'\]),||" ${WRKSRC}/setup.py

post-install:
	@${CHMOD} +x ${STAGEDIR}${PREFIX}/etc/rc.d/${PORTNAME}

correct-plist:
	@${REINPLACE_CMD} ' \
		s|^etc/${PORTNAME}/${PORTNAME}.example$$|@sample & etc/${PORTNAME}/${PORTNAME}|; \
		s|^etc/${PORTNAME}$$|@dir &|; \
		s|^etc/rc.d$$|@dir &|; \
		s|^share/doc/${PORTNAME}$$|@dir &|' \
		${TMPPLIST}

.include <bsd.port.mk>
