COMMENT=	The ISC Dynamic Host Configuration Protocol relay

SUBSYS=		relay
MASTERDIR=	${.CURDIR}/../isc-dhcp44-server

EXTRA_PATCHES=  ${.CURDIR}/files/patch-common__bpf.c \
                ${.CURDIR}/files/patch-common__packet.c \
                ${.CURDIR}/files/patch-includes__dhcp.h

.include "${MASTERDIR}/Makefile"
