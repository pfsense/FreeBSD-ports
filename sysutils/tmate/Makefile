PORTNAME=	tmate
PORTVERSION=	2.4.0
PORTREVISION=	5
CATEGORIES=	sysutils

MAINTAINER=	bofh@FreeBSD.org
COMMENT=	Instant terminal sharing
WWW=		https://tmate.io/

LICENSE=	ISCL
LICENSE_FILE=	${WRKSRC}/COPYING

DEPRECATED=            Upstream unmaintained
EXPIRATION_DATE=       2026-03-31

LIB_DEPENDS=	libevent.so:devel/libevent \
		libmsgpackc.so:devel/msgpack-c \
		libssh.so:security/libssh

USES=		autoreconf compiler:c11 ncurses pkgconfig
USE_GITHUB=	yes
GH_ACCOUNT=	tmate-io
USE_LDCONFIG=	yes

GNU_CONFIGURE=	yes

LDFLAGS+=	-lexecinfo

PLIST_FILES=	bin/tmate share/man/man1/tmate.1.gz

OPTIONS_DEFINE=	BACKSPACE

BACKSPACE_DESC=	Build with tty/keys patch

BACKSPACE_EXTRA_PATCHES=	${PATCHDIR}/extra-patch-tty-keys.c

.include <bsd.port.mk>
