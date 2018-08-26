# $FreeBSD$

PORTNAME=	signer
DISTVERSION=	20180826
PORTREVISION=	1
CATEGORIES=	security
PKGNAMEPREFIX=	netgate-

MAINTAINER=	garga@FreeBSD.org
COMMENT=	Netgate Thoth Signer

LIB_DEPENDS=	libucl.so:textproc/libucl \
		libfcgi.so:www/fcgi

RUN_DEPENDS=	/etc/thoth/ca.pem:security/netgate-ca \
		${LOCALBASE}/bin/setuidgid:sysutils/daemontools

PLIST_FILES=	bin/signer sbin/signer-start.sh

USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	thoth
GL_PROJECT=	thoth
GL_COMMIT=	cce86eeb486fecb412aabd28e5f55b95b88711c7
FETCH_BEFORE_ARGS=	--no-verify-peer
BUILD_WRKSRC=	${WRKSRC}/${PORTNAME}
INSTALL_WRKSRC=	${WRKSRC}/${PORTNAME}

.include <bsd.port.mk>
