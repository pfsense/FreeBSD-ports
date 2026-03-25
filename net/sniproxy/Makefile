PORTNAME=	sniproxy
DISTVERSION=	0.7.0
CATEGORIES=	net

MAINTAINER=	krion@FreeBSD.org
COMMENT=	Proxy that routes based on TLS server name extension
WWW=		https://github.com/dlundquist/sniproxy

LICENSE=	BSD2CLAUSE
LICENSE_FILE=	${WRKSRC}/COPYING

LIB_DEPENDS=	libev.so:devel/libev \
		libpcre2-8.so:devel/pcre2 \
		libudns.so:dns/udns

USES=		autoreconf localbase pkgconfig

SUB_FILES=	pkg-message
USE_RC_SUBR=	sniproxy

USE_GITHUB=	yes
GH_ACCOUNT=	dlundquist

GNU_CONFIGURE=	yes
USE_LDCONFIG=	yes

PLIST_FILES=	share/man/man5/sniproxy.conf.5.gz \
		share/man/man8/sniproxy.8.gz \
		sbin/sniproxy \
		"@sample etc/sniproxy.conf.sample"

post-patch:
	@${REINPLACE_CMD} -e 's|/var/tmp/|/var/run/|' ${WRKSRC}/${PORTNAME}.conf

post-install::
	${INSTALL_DATA} ${WRKSRC}/sniproxy.conf ${STAGEDIR}${PREFIX}/etc/sniproxy.conf.sample

.include <bsd.port.mk>
