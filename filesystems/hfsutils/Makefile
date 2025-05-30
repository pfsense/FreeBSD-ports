PORTNAME=	hfsutils
PORTVERSION=	3.2.6
PORTREVISION=	4
CATEGORIES=	filesystems sysutils
MASTER_SITES=	ftp://ftp.mars.org/pub/hfs/

MAINTAINER=	ports@FreeBSD.org
COMMENT=	Utilities for accessing Apple's HFS volumes
WWW=		https://www.mars.org/home/rob/proj/hfs/

LICENSE=	GPLv2+
LICENSE_FILE=	${WRKSRC}/COPYING

CONFLICTS=	hfs

USES=		gmake			# passing -jX breaks BSD make(1)
GNU_CONFIGURE=	yes
GNU_CONFIGURE_MANPREFIX=${PREFIX}/share
ALL_TARGET=	all hfsck/hfsck

PORTDOCS=	*

OPTIONS_DEFINE=	TCLTK DEVLIBS DOCS
OPTIONS_SUB=	yes

TCLTK_DESC=	Enable Tcl interface (HFS "shell") and Tk-based GUI
DEVLIBS_DESC=	Install developer libraries and header files

TCLTK_CATEGORIES=	tcl tk
TCLTK_USES=		tk
TCLTK_CPPFLAGS=		-I${LOCALBASE}/include
TCLTK_CONFIGURE_ON=	--with-tcl=${TCL_LIBDIR} --with-tk=${TK_LIBDIR}
TCLTK_CONFIGURE_OFF=	--without-tcl --without-tk

DEVLIBS_CONFIGURE_ON=	--enable-devlibs

post-patch:
# Unbreak against Tcl 8.6
	@${ECHO_CMD} '#define USE_INTERP_RESULT 1' >> ${WRKSRC}/config.h.in
# Allow staging (support DESTDIR)
	@${FIND} ${WRKSRC} -type f -name Makefile.in | ${XARGS} \
		${REINPLACE_CMD} -e '/^...DEST =/s,@,$$(DESTDIR)&,'

post-install:
	${INSTALL_PROGRAM} ${WRKSRC}/hfsck/hfsck ${STAGEDIR}${PREFIX}/bin

post-install-TCLTK-on:
	@${CHMOD} 755 ${STAGEDIR}${PREFIX}/bin/hfs

post-install-DOCS-on:
	@${MKDIR} ${STAGEDIR}${DOCSDIR}
.for f in BLURB CHANGES README TODO doc/*.txt
	${INSTALL_DATA} ${WRKSRC}/${f} ${STAGEDIR}${DOCSDIR}
.endfor

.include <bsd.port.mk>
