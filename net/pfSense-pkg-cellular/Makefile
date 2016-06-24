# $FreeBSD$

PORTNAME=	pfSense-pkg-cellular
PORTVERSION=	1.1.1
CATEGORIES=	net
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	tech@voleatech.de
COMMENT=	Voleatech Cellular Module Interface

RUN_DEPENDS=	${LOCALBASE}/bin/miniterm.py:comms/py-serial

LICENSE=	ESF

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

USES=		python shebangfix
SHEBANG_FILES=	${WRKSRC}${LOCALBASE}/sbin/cellular.py

post-install:
	@${PYTHON_CMD} ${PYTHON_LIBDIR}/compileall.py ${STAGEDIR}/usr/local/sbin
	${MV} ${STAGEDIR}/usr/local/sbin/cellular.pyc ${STAGEDIR}/usr/local/sbin/cellular

do-extract:
	${MKDIR} ${WRKSRC}
	${CP} -r ${FILESDIR}/usr ${WRKSRC}
	${CP} -r ${FILESDIR}/etc ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}/var/etc/cellular
	${MKDIR} ${STAGEDIR}${PREFIX}/sbin
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
	${INSTALL_DATA} ${WRKSRC}${PREFIX}/sbin/cellular.py \
		${STAGEDIR}${PREFIX}/sbin/cellular.py
	${INSTALL_DATA} ${WRKSRC}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	${INSTALL_DATA} ${WRKSRC}${PREFIX}/www/widgets/widgets/cellular.widget.php \
		${STAGEDIR}${PREFIX}/www/widgets/widgets
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${PREFIX}/pkg/cellular.xml
	(cd ${STAGEDIR}${PREFIX} \
		&& ${PYTHON_CMD} ${PYTHON_LIBDIR}/compileall.py \
		-d ${PREFIX} -f sbin)
	${INSTALL_SCRIPT} ${STAGEDIR}${PREFIX}/sbin/cellular.pyc \
		${STAGEDIR}${PREFIX}/sbin/cellular
	${RM} ${STAGEDIR}${PREFIX}/sbin/cellular.pyc

.include <bsd.port.mk>
