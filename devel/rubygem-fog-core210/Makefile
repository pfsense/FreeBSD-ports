PORTNAME=	fog-core
PORTVERSION=	2.1.0
PORTREVISION=	2
CATEGORIES=	devel rubygems
MASTER_SITES=	RG
PKGNAMESUFFIX=	210

MAINTAINER=	sunpoet@FreeBSD.org
COMMENT=	Shared classes and tests for fog providers and services
WWW=		https://github.com/fog/fog-core

LICENSE=	MIT
LICENSE_FILE=	${WRKSRC}/LICENSE.md

RUN_DEPENDS=	rubygem-builder>=0:devel/rubygem-builder \
		rubygem-excon0>=0.58<1:devel/rubygem-excon0 \
		rubygem-formatador>=0.2<2.0:devel/rubygem-formatador \
		rubygem-mime-types>=0:misc/rubygem-mime-types

USES=		gem

NO_ARCH=	yes

PORTSCOUT=	limit:^2\.1\.0

.include <bsd.port.mk>
