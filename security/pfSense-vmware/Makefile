# $FreeBSD$

PKGNAMESUFFIX=	-vmware

COMMENT=	Meta package to install pfSense required ports on VMware

MASTERDIR=	${.CURDIR}/../pfSense

CONFLICTS=	pfSense-[0-9]*

ONLY_FOR_ARCHS=	amd64 i386

.include "${MASTERDIR}/Makefile"

RUN_DEPENDS+=	${LOCALBASE}/bin/vmtoolsd:${PORTSDIR}/emulators/open-vm-tools-nox11
