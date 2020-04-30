# $FreeBSD$
#
# This file contains logic to ease porting of Go binaries using the
# `go` command.
#
# Feature:	go
# Usage:	USES=go
# Valid ARGS:	(none), modules, no_targets, run
#
# (none)	Setup GOPATH and build in GOPATH mode.
# modules	If the upstream uses Go modules, this can be set to build
#		in modules-aware mode.
# no_targets	Indicates that Go is needed at build time as a part of
#		make/CMake build.  This will setup build environment like
#		GO_ENV, GO_BUILDFLAGS but will not create post-extract and
#		do-{build,install,test} targets.
# run		Indicates that Go is needed at run time and adds it to
#		RUN_DEPENDS.
#
# You can set the following variables to control the process.
#
# GO_PKGNAME
#	The name of the package when building in GOPATH mode.  This
#	is the directory that will be created in ${GOPATH}/src.  If not set
#	explicitly and GH_SUBDIR or GL_SUBDIR is present, GO_PKGNAME will
#	be inferred from it.
#	It is not needed when building in modules-aware mode.
#
# GO_TARGET
#	The packages to build.  The default value is ${GO_PKGNAME}.
#	GO_TARGET can also be a tuple in the form package:path where path can be
#	either a simple filename or a full path starting with ${PREFIX}.
#
# GO_TESTTARGET
#	The packages to test. The default value is `./...` (the current package
#	and all subpackages).
#
# CGO_CFLAGS
#	Additional CFLAGS variables to be passed to the C compiler by the `go`
#	command
#
# CGO_LDFLAGS
#	Additional LDFLAGS variables to be passed to the C compiler by the `go`
#	command
#
# GO_BUILDFLAGS
#	Additional build arguments to be passed to the `go build` command
#
# GO_TESTFLAGS
#	Additional build arguments to be passed to the `go test` command
#
# GO_PORT
#	The Go port to use.  By default this is lang/go but can be set
#	to lang/go-devel in make.conf for testing with future Go versions.
#
#	This variable must not be set by individual ports!
#
# MAINTAINER: dmgk@FreeBSD.org

.if !defined(_INCLUDE_USES_GO_MK)
_INCLUDE_USES_GO_MK=	yes

.if !empty(go_ARGS:Nmodules:Nno_targets:Nrun)
IGNORE=	USES=go has invalid arguments: ${go_ARGS:Nmodules:Nno_targets:Nrun}
.endif

# Settable variables

.if empty(GO_PKGNAME)
.  if !empty(GH_SUBDIR)
GO_PKGNAME=	${GH_SUBDIR:S|^src/||}
.  elif !empty(GL_SUBDIR)
GO_PKGNAME=	${GL_SUBDIR:S|^src/||}
.  else
GO_PKGNAME=	${PORTNAME}
.  endif
.endif

GO_TARGET?=	${GO_PKGNAME}
GO_TESTTARGET?=	./...

GO_BUILDFLAGS+=	-v -buildmode=exe
.if !defined(WITH_DEBUG) && empty(GO_BUILDFLAGS:M-ldflags*)
GO_BUILDFLAGS+=	-ldflags=-s
.endif
GO_TESTFLAGS+=	-v

CGO_ENABLED?=	1
CGO_CFLAGS+=	-I${LOCALBASE}/include
CGO_LDFLAGS+=	-L${LOCALBASE}/lib

.if ${ARCH} == armv6 || ${ARCH} == armv7
GOARM?=		${ARCH:C/armv//}
.endif

# Read-only variables

GO_CMD=		${LOCALBASE}/bin/go
GO_WRKDIR_BIN=	${WRKDIR}/bin
GO_ENV+=	CGO_ENABLED=${CGO_ENABLED} \
		CGO_CFLAGS="${CGO_CFLAGS}" \
		CGO_LDFLAGS="${CGO_LDFLAGS}" \
		GOARM=${GOARM}

.if ${go_ARGS:Mmodules}
GO_BUILDFLAGS+=	-mod=vendor
GO_TESTFLAGS+=	-mod=vendor
GO_WRKSRC=	${WRKSRC}
GO_ENV+=	GOPATH="" \
		GOBIN="${GO_WRKDIR_BIN}" \
		GO_NO_VENDOR_CHECKS=1
.else
GO_WRKSRC=	${WRKDIR}/src/${GO_PKGNAME}
GO_ENV+=	GOPATH="${WRKDIR}" \
		GOBIN=""
.endif

GO_PORT?=	lang/go

BUILD_DEPENDS+=	${GO_CMD}:${GO_PORT}
.if ${go_ARGS:Mrun}
RUN_DEPENDS+=	${GO_CMD}:${GO_PORT}
.endif

_USES_POST+=	go
.endif # !defined(_INCLUDE_USES_GO_MK)

.if defined(_POSTMKINCLUDED) && !defined(_INCLUDE_USES_GO_POST_MK)
_INCLUDE_USES_GO_POST_MK=	yes

.if !target(post-extract) && empty(go_ARGS)
post-extract:
	@${MKDIR} ${GO_WRKSRC:H}
	@${LN} -sf ${WRKSRC} ${GO_WRKSRC}
.endif

.if !target(do-build) && empty(go_ARGS:Mno_targets)
do-build:
	(cd ${GO_WRKSRC}; \
	for t in ${GO_TARGET}; do \
		out=$$(${BASENAME} $$(${ECHO_CMD} $${t} | \
			${SED} -Ee 's/^[^:]*:([^:]+).*$$/\1/' -e 's/^\.$$/${PORTNAME}/')); \
		pkg=$$(${ECHO_CMD} $${t} | \
			${SED} -Ee 's/^([^:]*).*$$/\1/' -e 's/^${PORTNAME}$$/./'); \
		${ECHO_MSG} "===>  Building $${out} from $${pkg}"; \
		${SETENV} ${MAKE_ENV} ${GO_ENV} ${GO_CMD} build ${GO_BUILDFLAGS} \
			-o ${GO_WRKDIR_BIN}/$${out} \
			$${pkg}; \
	done)
.endif

.if !target(do-install) && empty(go_ARGS:Mno_targets)
do-install:
	for t in ${GO_TARGET}; do \
		dst=$$(${ECHO_CMD} $${t} | \
			${SED} -Ee 's/^[^:]*:([^:]+).*$$/\1/' -e 's/^\.$$/${PORTNAME}/'); \
		src=$$(${BASENAME} $${dst}); \
		case $${dst} in \
			/*) dst=${STAGEDIR}$${dst}; ${MKDIR} $$(${DIRNAME} $${dst}) ;; \
			 *) dst=${STAGEDIR}${PREFIX}/bin/$${src} ;; \
		esac; \
		${ECHO_MSG} "===>  Installing $${src} as $${dst}"; \
		${INSTALL_PROGRAM} ${GO_WRKDIR_BIN}/$${src} $${dst}; \
	done
.endif

.if !target(do-test) && empty(go_ARGS:Mno_targets)
do-test:
	(cd ${GO_WRKSRC}; \
	for t in ${GO_TESTTARGET}; do \
		${ECHO_MSG} "===>  Testing $${t}"; \
		${SETENV} ${MAKE_ENV} ${GO_ENV} ${GO_CMD} test ${GO_TESTFLAGS} $${t}; \
	done)
.endif

# Helper targets for port maintainers

.if ${go_ARGS:Mmodules}
_MODULES2TUPLE_CMD=	modules2tuple
gomod-vendor-deps:
	@if ! type ${GO_CMD} > /dev/null 2>&1; then \
		${ECHO_MSG} "===> Please install \"${GO_PORT}\""; exit 1; \
	fi; \
	if ! type ${_MODULES2TUPLE_CMD} > /dev/null 2>&1; then \
		${ECHO_MSG} "===> Please install \"ports-mgmt/modules2tuple\""; exit 1; \
	fi

gomod-vendor: gomod-vendor-deps patch
	@cd ${WRKSRC}; ${SETENV} GOPATH=${WRKDIR}/.gopath GOFLAGS=-modcacherw ${GO_CMD} mod vendor; \
	[ -r vendor/modules.txt ] && ${_MODULES2TUPLE_CMD} vendor/modules.txt

gomod-vendor-diff: gomod-vendor-deps patch
	@cd ${WRKSRC}; ${SETENV} GOPATH=${WRKDIR}/.gopath GOFLAGS=-modcacherw ${GO_CMD} mod vendor; \
	[ -r vendor/modules.txt ] && ${_MODULES2TUPLE_CMD} vendor/modules.txt | ${GREP} -v "^GH_TUPLE=" | ${SED} 's| \\$$||' > ${WRKDIR}/GH_TUPLE-new.txt && \
	echo ${GH_TUPLE} | ${TR} -s " " "\n" | ${SED} "s|^|		|" > ${WRKDIR}/GH_TUPLE-old.txt && \
	${DIFF} ${WRKDIR}/GH_TUPLE-old.txt ${WRKDIR}/GH_TUPLE-new.txt || exit 0
.endif

.endif # defined(_POSTMKINCLUDED) && !defined(_INCLUDE_USES_GO_POST_MK)
