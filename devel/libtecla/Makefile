PORTNAME=	libtecla
DISTVERSION=	1.6.3
PORTREVISION=	2
CATEGORIES=	devel
MASTER_SITES=	http://www.astro.caltech.edu/~mcs/tecla/

MAINTAINER=	k@stereochro.me
COMMENT=	Interactive command line editing facilities
WWW=		https://www.astro.caltech.edu/~mcs/tecla/

LICENSE=	MIT

USES=		autoreconf
USE_LDCONFIG=	yes
USE_SUBMAKE=	yes

GNU_CONFIGURE=	yes
GNU_CONFIGURE_MANPREFIX=${PREFIX}/share

MAKE_JOBS_UNSAFE=	yes
ALL_TARGET=	default

SSP_UNSAFE=	yes

WRKSRC=		${WRKDIR}/${PORTNAME}

.include <bsd.port.pre.mk>

.if ${OPSYS} == FreeBSD
CFLAGS+=       -Wno-error=int-conversion
.endif

post-patch:
	@${REINPLACE_CMD} -e 's,^OPT = -O,OPT =,' \
		${WRKSRC}/Makefile.in

do-install:
	${INSTALL_PROGRAM} ${WRKSRC}/enhance ${STAGEDIR}${PREFIX}/bin
	${INSTALL_DATA} ${WRKSRC}/libtecla.h ${STAGEDIR}${PREFIX}/include
.for l in libtecla libtecla_r
	${INSTALL_LIB} ${WRKSRC}/${l}.so.1 ${STAGEDIR}${PREFIX}/lib
	${INSTALL_DATA} ${WRKSRC}/${l}.a ${STAGEDIR}${PREFIX}/lib
	(cd ${STAGEDIR}${PREFIX}/lib; ${LN} -sf ${l}.so.1 ${l}.so)
.endfor
	for n in ${WRKSRC}/man/prog/*.in; do \
		d=$${n##*/}; \
		${INSTALL_MAN} $${n%.in} ${STAGEDIR}${PREFIX}/share/man/man1/$${d%.in}.1; \
	done
	for n in ${WRKSRC}/man/libr/*.in ${WRKSRC}/man/func/*.in; do \
		d=$${n##*/}; \
		${INSTALL_MAN} $${n%.in} ${STAGEDIR}${PREFIX}/share/man/man3/$${d%.in}.3; \
	done
	for n in ${WRKSRC}/man/file/*.in; do \
		d=$${n##*/}; \
		${INSTALL_MAN} $${n%.in} ${STAGEDIR}${PREFIX}/share/man/man5/$${d%.in}.5; \
	done
	for n in ${WRKSRC}/man/misc/*.in; do \
		d=$${n##*/}; \
		${INSTALL_MAN} $${n%.in} ${STAGEDIR}${PREFIX}/share/man/man7/$${d%.in}.7; \
	done

.include <bsd.port.post.mk>
