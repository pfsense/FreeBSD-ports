# $FreeBSD$

PORTNAME=	pfSense-pkg-cellular
PORTVERSION=	1.1.1
#PORTREVISION=	1
CATEGORIES=	net
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

RUN_DEPENDS=	${LOCALBASE}/bin/miniterm.py:comms/py-serial

MAINTAINER=	tech@voleatech.de
COMMENT=	Voleatech Cellular Module Interface

LICENSE=	ESF

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

USES=		python

post-install:
	@${PYTHON_CMD} ${PYTHON_LIBDIR}/compileall.py ${STAGEDIR}/usr/local/sbin
	${MV} ${STAGEDIR}/usr/local/sbin/cellular.pyc ${STAGEDIR}/usr/local/sbin/cellular

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}/var/etc/cellular
	${MKDIR} ${STAGEDIR}/usr/local/sbin
		${MKDIR} ${STAGEDIR}${DATADIR}
	${MKDIR} ${STAGEDIR}${PREFIX}/www/widgets/widgets
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/cellular.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/cellular.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/pkg_cellular.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/cellular.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}/usr/local/sbin/cellular.py \
		${STAGEDIR}/usr/local/sbin/cellular.py
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/widgets/widgets/cellular.widget.php \
		${STAGEDIR}${PREFIX}/www/widgets/widgets
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${PREFIX}/pkg/cellular.xml

.include <bsd.port.mk>
