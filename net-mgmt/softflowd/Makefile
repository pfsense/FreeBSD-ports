PORTNAME=	softflowd
PORTVERSION=	1.0.0
PORTREVISION=   1
DISTVERSIONPREFIX=	softflowd-
CATEGORIES=	net-mgmt

MAINTAINER=	jmohacsi@bsd.hu
COMMENT=	Flow-based network traffic analyzer and exporter
WWW=		http://www.mindrot.org/softflowd.html

LICENSE=	BSD2CLAUSE

USES=		gmake autoreconf
GNU_CONFIGURE=	yes
GNU_CONFIGURE_MANPREFIX=${PREFIX}/share
USE_GITHUB=	yes
GH_ACCOUNT=	irino
USE_RC_SUBR=	${PORTNAME}

PLIST_FILES=	bin/softflowd bin/softflowctl \
		share/man/man8/softflowd.8.gz \
		share/man/man8/softflowctl.8.gz
PORTDOCS=	README

OPTIONS_DEFINE=	DOCS

post-install-DOCS-on:
	@${MKDIR} ${STAGEDIR}${DOCSDIR}
	${INSTALL_DATA} ${WRKSRC}/${PORTDOCS} ${STAGEDIR}${DOCSDIR}

.include <bsd.port.mk>
