PORTNAME=	pcre
PORTVERSION=	7.2.3
DISTVERSIONPREFIX=	v
PORTREVISION=	4
CATEGORIES=	devel
PKGNAMEPREFIX=	ocaml-

MAINTAINER=	hrs@FreeBSD.org
COMMENT=	Perl compatible regular expressions for Objective Caml
WWW=		http://www.ocaml.info/home/ocaml_sources.html #pcre-ocaml

LICENSE=	LGPL21

BUILD_DEPENDS=	ocamlbuild:devel/ocaml-ocamlbuild
LIB_DEPENDS=	libpcre.so:devel/pcre

USES=		ocaml:findlib,ldconfig
USE_GITHUB=	yes
GH_ACCOUNT=	mmottl
GH_PROJECT=	${PORTNAME}-ocaml

EXAMPLESDIR=	${OCAML_EXAMPLESDIR}/${PORTNAME}
PORTEXAMPLES=	cloc count_hash pcregrep subst
DOCSDIR=	${PREFIX}/share/doc/pcre-ocaml
PORTDOCS=	api

OPTIONS_DEFINE=	DOCS EXAMPLES
DOCS_CONFIGURE_OFF=	--disable-docs

.include <bsd.port.options.mk>

.if ${ARCH} == powerpc
USE_BINUTILS=	yes
.endif

post-patch:
	${REINPLACE_CMD} -e 's|$$prefix"/"share|${STAGEDIR}${PREFIX}/share|' \
	    -e '/datarootdir/s|$$pkg_name|&-ocaml|' \
	    ${WRKSRC}/setup.ml
	${REINPLACE_CMD} -e 's| safe_string,||' ${WRKSRC}/_tags

post-install:
	cd ${STAGEDIR}${PREFIX} && ${STRIP_CMD} \
	    lib/ocaml/site-lib/pcre/dllpcre_stubs.so \
	    lib/ocaml/site-lib/pcre/pcre.cmxs

post-install-EXAMPLES-on:
	@${MKDIR} ${STAGEDIR}${EXAMPLESDIR}
	cd ${WRKSRC}/examples && ${COPYTREE_SHARE} . ${STAGEDIR}${EXAMPLESDIR}

.include <bsd.port.mk>
