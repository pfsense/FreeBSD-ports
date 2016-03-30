# $FreeBSD$

PORTNAME=	pfSense-ec2_setup
PORTVERSION=	0.4
PORTREVISION=	1
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense EC2 setup

LICENSE=	ESF

NO_BUILD=	yes
NO_MTREE=	yes

PLIST_SUB=	PORTNAME=${PORTNAME}
PLIST_FILES=	sbin/ec2_setup.php
USE_PHP=	yes

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	@${MKDIR} ${STAGEDIR}${PREFIX}/sbin
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/sbin/ec2_setup.php \
		${STAGEDIR}${PREFIX}/sbin

.include <bsd.port.mk>
