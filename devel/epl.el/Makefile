PORTNAME=	epl.el
PORTVERSION=	0.8
PORTREVISION=	19
CATEGORIES=	devel elisp
PKGNAMESUFFIX=	${EMACS_PKGNAMESUFFIX}

MAINTAINER=	hrs@FreeBSD.org
COMMENT=	Emacs Package Library
WWW=		https://github.com/cask/epl

LICENSE=	GPLv3

USES=		emacs
USE_GITHUB=	yes
GH_ACCOUNT=	cask
GH_PROJECT=	epl

NO_BUILD=	yes
PLIST_FILES=	${EMACS_SITE_LISPDIR}/epl.el

do-install:
	(cd ${WRKSRC} && ${INSTALL_DATA} ${PLIST_FILES:M*.el:T} \
	    ${STAGEDIR}${PREFIX}/${EMACS_SITE_LISPDIR})

.include <bsd.port.mk>
