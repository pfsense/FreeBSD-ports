# $FreeBSD$

PORTNAME=	pfSense-pkg-aws-wizard
PORTVERSION=	0.5
PORTREVISION=	1
CATEGORIES=	net
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	PfSense package AWS VPC VPN Connection Wizard

LICENSE=	ESF

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

.include <bsd.port.pre.mk>

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${PREFIX}/www/wizards
	${MKDIR} ${STAGEDIR}/etc/inc/aws
	${MKDIR} ${STAGEDIR}${DATADIR}
	(cd ${FILESDIR}/etc/inc/aws && ${COPYTREE_SHARE} . \
		${STAGEDIR}/etc/inc/aws)
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/aws_wizard.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/wizards/vpc_vpn_wizard.xml \
		${STAGEDIR}${PREFIX}/www/wizards
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/wizards/vpc_vpn_wizard.inc \
		${STAGEDIR}${PREFIX}/www/wizards
	 ${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
