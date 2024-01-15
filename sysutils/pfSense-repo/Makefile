# $FreeBSD$

PORTNAME=	pfSense-repo
DISTVERSION=	${PRODUCT_VERSION}
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

# Flag used to detect when we want to reinstall all packages during upgrade
PFSENSE_PKG_SET_VERSION?=	${DISTVERSION}

.include <bsd.port.pre.mk>

MIRROR_TYPE?=	srv
PFSENSE_DEFAULT_REPO?=	pfSense-repo-devel
PFSENSE_DEFAULT_REPO_${ARCH}?=	${PFSENSE_DEFAULT_REPO}
SIGNATURE_TYPE?=	fingerprints
DEFAULT_REPO=	${DATADIR}/pkg/repos/${PFSENSE_DEFAULT_REPO_${ARCH}}.conf
PLIST_FILES=	${DEFAULT_REPO}.default \
		${DATADIR}/next_pkg_set_version

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

.for b in DEVEL NEXT RELEASE PREVIOUS
. if defined(PKG_REPO_BRANCH_${b}) && !empty(PKG_REPO_BRANCH_${b})
PKG_REPO_FILENAME_${b}=	${PORTNAME}-${b:tl}
PKG_REPO_DESCR_${b}=	${b} version (${PKG_REPO_BRANCH_${b}:S/_/./g})
PKG_REPO_OSVER_${b}=	${PKG_REPO_BRANCH_${b}}
.  if ${b} != "DEVEL" && ${b} != "RELEASE"
PKG_REPO_SERVER_${b}=	${PKG_REPO_SERVER_RELEASE}
.  endif
.  if ${b} == "RELEASE"
PKG_REPO_FILENAME_${b}=	${PORTNAME}
PKG_REPO_DESCR_${b}=	Latest stable version (${PKG_REPO_BRANCH_${b}:S/_/./g})
.  endif
.  if ${b} == "DEVEL"
PKG_REPO_OSVER_${b}=	master
.  endif
PLIST_FILES+=		${DATADIR}/pkg/repos/${PKG_REPO_FILENAME_${b}}.conf ${DATADIR}/pkg/repos/${PKG_REPO_FILENAME_${b}}.descr
PLIST_FILES+=		${DATADIR}/pkg/repos/${PKG_REPO_FILENAME_${b}}.abi ${DATADIR}/pkg/repos/${PKG_REPO_FILENAME_${b}}.altabi
. endif
.endfor

do-extract:
	@${MKDIR} ${WRKSRC}

do-build:
.for b in DEVEL NEXT RELEASE PREVIOUS
. if defined(PKG_REPO_BRANCH_${b}) && !empty(PKG_REPO_BRANCH_${b})
	${CP} ${FILESDIR}/${PORTNAME}.conf ${WRKSRC}/${PKG_REPO_FILENAME_${b}}.conf
	${REINPLACE_CMD} \
		-e "s,%%ARCH%%,${ARCH},g" \
		-e "s,%%ARCH_OLD%%,${ARCH_OLD},g" \
		-e "s/%%MIRROR_TYPE%%/${MIRROR_TYPE}/" \
		-e "s,%%PKG_REPO_BRANCH_DEVEL%%,${PKG_REPO_BRANCH_DEVEL},g" \
		-e "s,%%PKG_REPO_BRANCH_RELEASE%%,${PKG_REPO_BRANCH_RELEASE},g" \
		-e "s,%%PKG_REPO_SERVER_RELEASE%%,${PKG_REPO_SERVER_${b}},g" \
		-e "s,%%POUDRIERE_PORTS_NAME%%,${POUDRIERE_PORTS_NAME},g" \
		-e "s,%%PRODUCT_NAME%%,${PRODUCT_NAME},g" \
		-e "s,%%REPO_BRANCH_PREFIX%%,${REPO_BRANCH_PREFIX},g" \
		-e "s,%%SIGNATURE_TYPE%%,${SIGNATURE_TYPE},g" \
		-e "s,%%OSVERSION%%,${PKG_REPO_OSVER_${b}},g" \
		-e "s,%%VERSION%%,${REPO_BRANCH_PREFIX}${PKG_REPO_BRANCH_${b}},g" \
		${WRKSRC}/${PKG_REPO_FILENAME_${b}}.conf
	echo "${PKG_REPO_DESCR_${b}}" > ${WRKSRC}/${PKG_REPO_FILENAME_${b}}.descr
. endif
. if defined(PKG_REPO_MAJORVER_${b}) && !empty(PKG_REPO_MAJORVER_${b})
	echo "${OPSYS}:${PKG_REPO_MAJORVER_${b}}:${ARCH}" > ${WRKSRC}/${PKG_REPO_FILENAME_${b}}.abi
	echo "${OPSYS}:${PKG_REPO_MAJORVER_${b}}:${ARCH}" > ${WRKSRC}/${PKG_REPO_FILENAME_${b}}.altabi
. else
	echo "${OPSYS}:${OSREL:C/\..*//}:${ARCH}" > ${WRKSRC}/${PKG_REPO_FILENAME_${b}}.abi
	echo "${OPSYS}:${OSREL:C/\..*//}:${ARCH}" > ${WRKSRC}/${PKG_REPO_FILENAME_${b}}.altabi
. endif
.endfor

do-install:
	${MKDIR} ${STAGEDIR}${DATADIR}/pkg/repos
	${TOUCH} ${STAGEDIR}${DEFAULT_REPO}.default
.for b in DEVEL NEXT RELEASE PREVIOUS
. if defined(PKG_REPO_BRANCH_${b}) && !empty(PKG_REPO_BRANCH_${b})
	${INSTALL_DATA} ${WRKSRC}/${PKG_REPO_FILENAME_${b}}.conf ${STAGEDIR}${DATADIR}/pkg/repos
	${INSTALL_DATA} ${WRKSRC}/${PKG_REPO_FILENAME_${b}}.descr ${STAGEDIR}${DATADIR}/pkg/repos
	${INSTALL_DATA} ${WRKSRC}/${PKG_REPO_FILENAME_${b}}.abi ${STAGEDIR}${DATADIR}/pkg/repos
	${INSTALL_DATA} ${WRKSRC}/${PKG_REPO_FILENAME_${b}}.altabi ${STAGEDIR}${DATADIR}/pkg/repos
. endif
.endfor
	${ECHO_CMD} ${PFSENSE_PKG_SET_VERSION} \
		> ${STAGEDIR}${DATADIR}/next_pkg_set_version

.include <bsd.port.post.mk>
