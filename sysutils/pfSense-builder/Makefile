# $FreeBSD$

PORTNAME=	pfSense-builder
DISTVERSION=	2.3-20150512
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	renato@pfsense.com
COMMENT?=	Meta package to install pfSense builder required ports

RUN_DEPENDS=	${LOCALBASE}/bin/git:${PORTSDIR}/devel/git-lite \
		${LOCALBASE}/bin/curl:${PORTSDIR}/ftp/curl \
		${LOCALBASE}/bin/rsync:${PORTSDIR}/net/rsync \
		${LOCALBASE}/bin/cpdup:${PORTSDIR}/sysutils/cpdup \
		${LOCALBASE}/bin/screen:${PORTSDIR}/sysutils/screen \
		${LOCALBASE}/bin/vmdktool:${PORTSDIR}/sysutils/vmdktool \
		${LOCALBASE}/bin/salt:${PORTSDIR}/sysutils/py-salt \
		${LOCALBASE}/bin/sudo:${PORTSDIR}/security/sudo \
		${LOCALBASE}/sbin/nginx:${PORTSDIR}/www/nginx \
		${LOCALBASE}/bin/qemu-arm-static:${PORTSDIR}/emulators/qemu-user-static \
		${LOCALBASE}/sbin/sssd:${PORTSDIR}/security/sssd \
		${LOCALBASE}/lib/pam_ldap.so:${PORTSDIR}/security/pam_ldap \
		${LOCALBASE}/lib/pam_mkhomedir.so:${PORTSDIR}/security/pam_mkhomedir \
		${LOCALBASE}/bin/gtar:${PORTSDIR}/archivers/gtar

LIB_DEPENDS=	libpcre.so:${PORTSDIR}/devel/pcre

ONLY_FOR_ARCHS=	amd64 i386

NO_BUILD=	yes
NO_MTREE=	yes

.include <bsd.port.pre.mk>

do-install:	# empty

.include <bsd.port.post.mk>
