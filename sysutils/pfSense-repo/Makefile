# $FreeBSD$

PORTNAME=	pfSense-repo
DISTVERSION=	2.4.5
PORTREVISION=	1
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	Setup pfSense pkg(8) repositories

LICENSE=	APACHE20

NO_MTREE=	yes

SUB_FILES=	pkg-install
SUB_LIST=	PORTNAME=${PORTNAME} \
		PRODUCT_NAME=${PRODUCT_NAME} \
		DEFAULT_REPO=${DEFAULT_REPO}

DATADIR=	${PREFIX}/share/pfSense

.include <bsd.port.pre.mk>

PFSENSE_REPOS?=	pfSense-repo pfSense-repo-devel
PFSENSE_REPOS_${ARCH}?=	${PFSENSE_REPOS}
PFSENSE_DEFAULT_REPO?=	pfSense-repo-devel
PFSENSE_DEFAULT_REPO_${ARCH}?=	${PFSENSE_DEFAULT_REPO}
DEFAULT_REPO=	${DATADIR}/pkg/repos/${PFSENSE_DEFAULT_REPO_${ARCH}}.conf
TMP_PLIST_FILES=${PFSENSE_REPOS_${ARCH}:C/$/.conf/} \
		${PFSENSE_REPOS_${ARCH}:C/$/.descr/} \
		${PFSENSE_REPOS_${ARCH}:C/$/.abi/} \
		${PFSENSE_REPOS_${ARCH}:C/$/.altabi/}
PLIST_FILES=	${TMP_PLIST_FILES:C/^/%%DATADIR%%\/pkg\/repos\//} \
		${DEFAULT_REPO}.default
DEFAULT_ABI=	${OPSYS}:${OSREL:C/\..*//}:${ARCH}

.if ${ARCH} == "aarch64"
ALTABI_ARCH=	aarch64:64
.elif ${ARCH} == "amd64"
ALTABI_ARCH=	x86:64
.elif ${ARCH} == "i386"
ALTABI_ARCH=	x86:32
.elif ${ARCH} == "armv6"
ALTABI_ARCH=	32:el:eabi:hardfp
.elif ${ARCH} == "armv7"
ALTABI_ARCH=	32:el:eabi:softfp
.else
.error "Invalid arch: ${ARCH}"
.endif

.if ${ARCH} == "armv6"
ARCH_NEW=	armv7
ALTABI_ARCH_NEW=32:el:eabi:softfp
.else
ARCH_NEW=	${ARCH}
ALTABI_ARCH_NEW=${ALTABI_ARCH}
.endif

DEFAULT_ALTABI=	${OPSYS:tl}:${OSREL:C/\..*//}:${ALTABI_ARCH}

.if defined(PKG_REPO_BRANCH_DEVEL_${ARCH}) && !empty(PKG_REPO_BRANCH_DEVEL_${ARCH})
PKG_REPO_BRANCH_DEVEL=${PKG_REPO_BRANCH_DEVEL_${ARCH}}
.endif
.if defined(PKG_REPO_BRANCH_RELEASE_${ARCH}) && !empty(PKG_REPO_BRANCH_RELEASE_${ARCH})
PKG_REPO_BRANCH_RELEASE=${PKG_REPO_BRANCH_RELEASE_${ARCH}}
.endif
.if defined(PKG_REPO_SERVER_DEVEL_${ARCH}) && !empty(PKG_REPO_SERVER_DEVEL_${ARCH})
PKG_REPO_SERVER_DEVEL=${PKG_REPO_SERVER_DEVEL_${ARCH}}
.endif
.if defined(PKG_REPO_SERVER_RELEASE_${ARCH}) && !empty(PKG_REPO_SERVER_RELEASE_${ARCH})
PKG_REPO_SERVER_RELEASE=${PKG_REPO_SERVER_RELEASE_${ARCH}}
.endif

do-extract:
	@${MKDIR} ${WRKSRC}
	${CP} -r ${FILESDIR}/* ${WRKSRC}

do-build:
.for f in ${PFSENSE_REPOS_${ARCH}}
	@${REINPLACE_CMD} -i '' \
		-e "s,%%ARCH%%,${ARCH},g" \
		-e "s,%%ARCH_NEW%%,${ARCH_NEW},g" \
		-e "s,%%PKG_REPO_BRANCH_DEVEL%%,${PKG_REPO_BRANCH_DEVEL},g" \
		-e "s,%%PKG_REPO_BRANCH_RELEASE%%,${PKG_REPO_BRANCH_RELEASE},g" \
		-e "s,%%PKG_REPO_SERVER_DEVEL%%,${PKG_REPO_SERVER_DEVEL},g" \
		-e "s,%%PKG_REPO_SERVER_RELEASE%%,${PKG_REPO_SERVER_RELEASE},g" \
		-e "s,%%POUDRIERE_PORTS_NAME%%,${POUDRIERE_PORTS_NAME},g" \
		-e "s,%%PRODUCT_NAME%%,${PRODUCT_NAME},g" \
		-e "s,%%REPO_BRANCH_PREFIX%%,${REPO_BRANCH_PREFIX},g" \
		${WRKSRC}/${f}.conf
	@if [ -f ${WRKSRC}/${f}.abi ]; then \
		${REINPLACE_CMD} -i '' \
			-e "s,%%ARCH%%,${ARCH},g" \
			-e "s,%%ARCH_NEW%%,${ARCH_NEW},g" \
			${WRKSRC}/${f}.abi; \
	fi
	@if [ -f ${WRKSRC}/${f}.altabi ]; then \
		${REINPLACE_CMD} -i '' \
			-e "s,%%ARCH%%,${ALTABI_ARCH},g" \
			-e "s,%%ARCH_NEW%%,${ALTABI_ARCH_NEW},g" \
			${WRKSRC}/${f}.altabi; \
	fi
.endfor

do-install:
	${MKDIR} ${STAGEDIR}${DATADIR}/pkg/repos
	${TOUCH} ${STAGEDIR}${DEFAULT_REPO}.default
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
