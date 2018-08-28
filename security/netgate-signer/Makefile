# $FreeBSD$

PORTNAME=	signer
DISTVERSION=	20180827
PORTREVISION=	1
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
GL_COMMIT=	ce94a676d47fcfdb190e47c484a1fe991d3485b3
FETCH_BEFORE_ARGS=	--no-verify-peer
BUILD_WRKSRC=	${WRKSRC}/${PORTNAME}
INSTALL_WRKSRC=	${WRKSRC}/${PORTNAME}

.include <bsd.port.mk>
