# $FreeBSD$

PORTNAME=	pfSense-builder
PORTVERSION=	2.3.4
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	renato@pfsense.com
COMMENT?=	Meta package to install pfSense builder required ports

LICENSE=	ESF

RUN_DEPENDS=	${LOCALBASE}/bin/git:devel/git \
		${LOCALBASE}/bin/curl:ftp/curl \
		${LOCALBASE}/bin/rsync:net/rsync \
		${LOCALBASE}/bin/screen:sysutils/screen \
		${LOCALBASE}/bin/vmdktool:sysutils/vmdktool \
		${LOCALBASE}/bin/salt:sysutils/py-salt \
		${LOCALBASE}/bin/sudo:security/sudo \
		${LOCALBASE}/sbin/nginx:www/nginx \
		${LOCALBASE}/bin/qemu-arm-static:emulators/qemu-user-static \
		${LOCALBASE}/sbin/sssd:security/sssd \
		${LOCALBASE}/lib/pam_ldap.so:security/pam_ldap \
		${LOCALBASE}/lib/pam_mkhomedir.so:security/pam_mkhomedir \
		${LOCALBASE}/bin/gtar:archivers/gtar \
		${LOCALBASE}/bin/xml:textproc/xmlstarlet

LIB_DEPENDS=	libpcre.so:devel/pcre

ONLY_FOR_ARCHS=	amd64 i386

NO_BUILD=	yes
NO_MTREE=	yes

.include <bsd.port.pre.mk>

do-install:	# empty

.include <bsd.port.post.mk>
