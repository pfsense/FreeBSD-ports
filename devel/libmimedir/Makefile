PORTNAME=	libmimedir
PORTVERSION=	0.5.1
PORTREVISION=	2
CATEGORIES=	devel
MASTER_SITES=	SF/${PORTNAME}/${PORTNAME}/${PORTNAME}-${PORTVERSION}

MAINTAINER=	ports@FreeBSD.org
COMMENT=	Library for parsing RFC2425 MIME Directory Profile information

LICENSE=	BSD2CLAUSE
LICENSE_FILE=	${WRKSRC}/COPYING

DEPRECATED=	Last release in 2008, no consumers in tree and unmaintained for years
EXPIRATION_DATE=2025-06-30

USES=		cpe desthack libtool
CPE_VENDOR=	${PORTNAME}_project
GNU_CONFIGURE=	yes
CONFIGURE_ENV=	YACC=byacc
USE_LDCONFIG=	yes

MAKE_JOBS_UNSAFE=yes

post-patch:
	@${REINPLACE_CMD} -e 's/444/644/g' ${WRKSRC}/Makefile.in

post-install:
	@${STRIP_CMD} ${STAGEDIR}${PREFIX}/lib/libmimedir.so

do-test:
	cd ${WRKSRC} && ./test

.include <bsd.port.mk>
