PORTNAME=	em
PORTVERSION=	7.7.8
PORTREVISION=	1
PORTEPOCH=	0
MASTER_SITE_SUBDIR=	17509/eng

MAINTAINER=	freebsd@intel.com
COMMENT=	Gigabit FreeBSD Base Drivers for Intel(R) Ethernet

BROKEN_armv6=		fails to compile: implicit declaration of function 'cp15_pmccntr_get' is invalid in C99

MASTERDIR=	${.CURDIR}/../intel-ixl-kmod
PKGDIR=		${.CURDIR}
DISTINFO_FILE=	${.CURDIR}/distinfo
PATCHDIR=	${.CURDIR}/files

.include "${MASTERDIR}/Makefile"
