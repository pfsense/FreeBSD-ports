PORTNAME=	irb
PORTVERSION=	1.15.2
CATEGORIES=	devel rubygems
MASTER_SITES=	RG

MAINTAINER=	ruby@FreeBSD.org
COMMENT=	Interactive Ruby
WWW=		https://github.com/ruby/irb

LICENSE=	BSD2CLAUSE
LICENSE_FILE=	${WRKSRC}/LICENSE.txt

RUN_DEPENDS=	rubygem-pp>=0.6.0:devel/rubygem-pp \
		rubygem-rdoc>=4.0.0:devel/rubygem-rdoc \
		rubygem-reline>=0.4.2:devel/rubygem-reline

USES=		gem

NO_ARCH=	yes

PLIST_FILES=	bin/irb \
		share/man/man1/irb.1.gz

post-install:
	${INSTALL_DATA} ${WRKSRC}/man/irb.1 ${STAGEDIR}${PREFIX}/share/man/man1

.include <bsd.port.mk>
