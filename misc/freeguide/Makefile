PORTNAME=	freeguide
PORTVERSION=	0.11.1
PORTREVISION=	2
CATEGORIES=	misc java
MASTER_SITES=	SF/${PORTNAME}-tv/${PORTNAME}-source/${PORTVERSION}

MAINTAINER=	ports@FreeBSD.org
COMMENT=	Interactive TV guide which will create personalized TV listings
WWW=		http://freeguide-tv.sf.net/

LICENSE=	GPLv2+
LICENSE_FILE=	${WRKSRC}/doc/COPYING

RUN_DEPENDS=	tv_cat:textproc/p5-xmltv

USES=		java:ant,run
JAVA_VERSION=	8

NO_ARCH=	yes

MAKE_ARGS=	-Dinstall_share_dir=${STAGEDIR}${PREFIX}/share \
		-Dinstall_man1_dir=${STAGEDIR}${PREFIX}/share \
		-Dinstall_java_bin=${JAVA} \
		-Dinstall_real_doc_dir=${DOCSDIR} \
		-Dinstall_bin_dir=${STAGEDIR}${PREFIX}/bin \
		-Dinstall_doc_dir=${STAGEDIR}${DOCSDIR} \
		-Dinstall_java_share_dir=${STAGEDIR}${JAVASHAREDIR} \
		-Dinstall_jar_dir=${STAGEDIR}${JAVAJARDIR}/freeguide
INSTALL_TARGET=	install

post-patch:
	@${REINPLACE_CMD} 's|__ANT_INSTALL_DIRECTORY__|${JAVAJARDIR}/freeguide|; \
		s|=java|=${JAVA}|;' \
		${WRKSRC}/install/linux/freeguide

do-install:
	@(cd ${BUILD_WRKSRC} && \
		${ANT} ${MAKE_ARGS} ${INSTALL_TARGET})

.include <bsd.port.mk>
