# $FreeBSD$

PKGNAMEPREFIX=	pfsense-

# Define PORTREVISION in ${MASTERDIR}
MASTERDIR=	${.CURDIR}/../../dns/bind911

DESCR=		${.CURDIR}/pkg-descr
PLIST=		${.CURDIR}/pkg-plist
RUN_DEPENDS= ${LOCALBASE}/bin/nslookup:dns/bind-tools

.include "${MASTERDIR}/Makefile"

CONFLICTS=     bind9*-9.[456789].* bind9*-sdb-9.[456789].*

