# $FreeBSD$

PORTNAME=	pfSense-pkg-cellular
PORTVERSION=	1.2.3
PORTREVISION=	2
CATEGORIES=	net
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	tech@voleatech.de
COMMENT=	Voleatech Cellular Module Interface

LICENSE=	APACHE20

RUN_DEPENDS=	${PYTHON_PKGNAMEPREFIX}pyserial>0:comms/py-pyserial@${PY_FLAVOR}

USES=		python shebangfix

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

SHEBANG_FILES=	${WRKSRC}${PREFIX}/sbin/cellular.py \
		${WRKSRC}${PREFIX}/bin/cellular_dev.py

do-extract:
	${MKDIR} ${WRKSRC}
	${CP} -r ${FILESDIR}/usr ${WRKSRC}
	${CP} -r ${FILESDIR}/etc ${WRKSRC}
	@${SED} -i '' -e "s,%%PYTHON_CMD%%,${PYTHON_CMD},g" \
		${WRKSRC}/usr/local/www/widgets/widgets/cellular.widget.php \
		${WRKSRC}/usr/local/pkg/cellular.inc \
		${WRKSRC}/etc/devd/cellular.conf

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${PREFIX}/sbin
	${MKDIR} ${STAGEDIR}${PREFIX}/bin
	${MKDIR} ${STAGEDIR}/etc/devd
	${MKDIR} ${STAGEDIR}${DATADIR}
	${MKDIR} ${STAGEDIR}${PREFIX}/www/widgets/widgets
	${INSTALL_DATA} ${WRKSRC}${PREFIX}/pkg/cellular.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${WRKSRC}${PREFIX}/pkg/cellular.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${WRKSRC}${PREFIX}/pkg/pkg_cellular.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${WRKSRC}/etc/inc/priv/cellular.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${WRKSRC}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	${INSTALL_DATA} ${WRKSRC}${PREFIX}/www/widgets/widgets/cellular.widget.php \
		${STAGEDIR}${PREFIX}/www/widgets/widgets
	${INSTALL_DATA} ${WRKSRC}/etc/devd/cellular.conf \
		${STAGEDIR}/etc/devd
	${INSTALL_DATA} ${WRKSRC}${PREFIX}/sbin/cellular_check.php \
		${STAGEDIR}${PREFIX}/sbin
	${INSTALL_DATA} ${WRKSRC}${PREFIX}/bin/cellular_dev.py \
		${STAGEDIR}${PREFIX}/bin
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml \
		${STAGEDIR}${PREFIX}/pkg/cellular.xml
	(cd ${WRKSRC}${PREFIX} \
		&& ${PYTHON_CMD} ${PYTHON_LIBDIR}/compileall.py \
		-b -d ${PREFIX} -f sbin)
	${INSTALL_SCRIPT} ${WRKSRC}${PREFIX}/sbin/cellular.pyc \
		${STAGEDIR}${PREFIX}/sbin/cellular

.include <bsd.port.mk>
