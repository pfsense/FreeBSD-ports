# $FreeBSD$

PORTNAME=	signer
DISTVERSION=	20201202
CATEGORIES=	security
PKGNAMEPREFIX=	netgate-

MAINTAINER=	garga@FreeBSD.org
COMMENT=	Netgate Thoth Signer

LIB_DEPENDS=	libucl.so:textproc/libucl \
		libfcgi.so:www/fcgi

RUN_DEPENDS=	/etc/thoth/ca.pem:security/netgate-ca \
		/usr/local/bin/curl:ftp/curl \
		${LOCALBASE}/bin/setuidgid:sysutils/daemontools

PLIST_FILES=	bin/signer sbin/signer-start.sh

USE_GITLAB=	yes
GL_SITE=	https://gitlab.netgate.com
GL_ACCOUNT=	thoth
GL_PROJECT=	thoth
GL_COMMIT=	52278a3232af225572001d310c9a8e81b719d108
FETCH_BEFORE_ARGS=	--no-verify-peer
BUILD_WRKSRC=	${WRKSRC}/${PORTNAME}
INSTALL_WRKSRC=	${WRKSRC}/${PORTNAME}

.include <bsd.port.mk>
