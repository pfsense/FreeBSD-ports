PORTNAME=	tmate-ssh-server
PORTVERSION=	g2020071601
PORTREVISION=	2
PORTEPOCH=	1
CATEGORIES=	sysutils

MAINTAINER=	bofh@FreeBSD.org
COMMENT=	Instant terminal sharing server
WWW=		https://tmate.io/

DEPRECATED=		Upstream unmaintained
EXPIRATION_DATE=	2026-03-30

LIB_DEPENDS=	libevent.so:devel/libevent \
		libmsgpackc.so:devel/msgpack-c \
		libssh.so:security/libssh

USES=		autoreconf compiler:c11 ncurses pkgconfig
USE_GITHUB=	yes
GH_ACCOUNT=	tmate-io
GH_TAGNAME=	97d2024
USE_LDCONFIG=	yes

GNU_CONFIGURE=	yes

LDFLAGS+=	-lexecinfo

PORTSCOUT=	skipv:2.3.0

PLIST_FILES=	bin/tmate-ssh-server

.include <bsd.port.mk>
