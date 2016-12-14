# $FreeBSD$

PORTNAME=	pfSense-repo
DISTVERSION=	2.4.0
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	Setup pfSense pkg(8) repositories

LICENSE=	APACHE20

RUN_DEPENDS=	${LOCALBASE}/sbin/pfSense-upgrade:sysutils/pfSense-upgrade

NO_MTREE=	yes

SUB_FILES=	pkg-install
SUB_LIST=	PORTNAME=${PORTNAME} \
		PRODUCT_NAME=${PRODUCT_NAME}

DATADIR=	${PREFIX}/share/pfSense

.include <bsd.port.pre.mk>

PFSENSE_REPOS?=	pfSense-repo pfSense-repo-devel
PFSENSE_REPOS_${ARCH}?=	${PFSENSE_REPOS}
TMP_PLIST_FILES=${PFSENSE_REPOS_${ARCH}:C/$/.conf/} \
		${PFSENSE_REPOS_${ARCH}:C/$/.descr/} \
		${PFSENSE_REPOS_${ARCH}:C/$/.abi/} \
		${PFSENSE_REPOS_${ARCH}:C/$/.altabi/}
PLIST_FILES=	${TMP_PLIST_FILES:C/^/%%DATADIR%%\/pkg\/repos\//}
DEFAULT_ABI=	${OPSYS}:${OSREL:C/\..*//}:${ARCH}

.if ${ARCH} == "amd64"
ALTABI_ARCH=	x86:64
.elif ${ARCH} == "i386"
ALTABI_ARCH=	x86:32
.elif ${ARCH} == "armv6"
ALTABI_ARCH=	32:el:eabi:hardfp
.else
.error "Invalid arch: ${ARCH}"
.endif

DEFAULT_ALTABI=	${OPSYS:tl}:${OSREL:C/\..*//}:${ALTABI_ARCH}

do-extract:
	@${MKDIR} ${WRKSRC}
	${CP} -r ${FILESDIR}/* ${WRKSRC}

do-build:
.for f in ${PFSENSE_REPOS_${ARCH}}
	@${REINPLACE_CMD} -i '' \
		-e "s,%%ARCH%%,${ARCH},g" \
		-e "s,%%PKG_REPO_BRANCH_DEVEL%%,${PKG_REPO_BRANCH_DEVEL},g" \
		-e "s,%%PKG_REPO_BRANCH_RELEASE%%,${PKG_REPO_BRANCH_RELEASE},g" \
		-e "s,%%PKG_REPO_SERVER_DEVEL%%,${PKG_REPO_SERVER_DEVEL},g" \
		-e "s,%%PKG_REPO_SERVER_RELEASE%%,${PKG_REPO_SERVER_RELEASE},g" \
		-e "s,%%POUDRIERE_PORTS_NAME%%,${POUDRIERE_PORTS_NAME},g" \
		-e "s,%%PRODUCT_NAME%%,${PRODUCT_NAME},g" \
		${WRKSRC}/${f}.conf
	@if [ -f ${WRKSRC}/${f}.abi ]; then \
		${REINPLACE_CMD} -i '' \
			-e "s,%%ARCH%%,${ARCH},g" \
			${WRKSRC}/${f}.abi; \
	fi
	@if [ -f ${WRKSRC}/${f}.altabi ]; then \
		${REINPLACE_CMD} -i '' \
			-e "s,%%ARCH%%,${ALTABI_ARCH},g" \
			${WRKSRC}/${f}.altabi; \
	fi
.endfor

do-install:
	${MKDIR} ${STAGEDIR}${DATADIR}/pkg/repos
.for f in ${PFSENSE_REPOS_${ARCH}}
	${INSTALL_DATA} ${WRKSRC}/${f}.conf ${STAGEDIR}${DATADIR}/pkg/repos
	${INSTALL_DATA} ${WRKSRC}/${f}.descr ${STAGEDIR}${DATADIR}/pkg/repos
	if [ -f ${WRKSRC}/${f}.abi ]; then \
		${INSTALL_DATA} ${WRKSRC}/${f}.abi ${STAGEDIR}${DATADIR}/pkg/repos; \
	else \
		${ECHO_CMD} ${DEFAULT_ABI} > ${STAGEDIR}${DATADIR}/pkg/repos/${f}.abi; \
	fi
	if [ -f ${WRKSRC}/${f}.altabi ]; then \
		${INSTALL_DATA} ${WRKSRC}/${f}.altabi ${STAGEDIR}${DATADIR}/pkg/repos; \
	else \
		${ECHO_CMD} ${DEFAULT_ALTABI} > ${STAGEDIR}${DATADIR}/pkg/repos/${f}.altabi; \
	fi
.endfor

.include <bsd.port.post.mk>
