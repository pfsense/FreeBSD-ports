PORTNAME=	nanocoder
DISTVERSION=	1.24.1
CATEGORIES=	misc # machine-learning
DISTFILES=	${DISTNAME}${EXTRACT_SUFX}
EXTRACT_ONLY=	${DISTNAME}${EXTRACT_SUFX}

MAINTAINER=	yuri@FreeBSD.org
COMMENT=	Local-first coding agent running in your terminal
WWW=		https://github.com/Nano-Collective/nanocoder

LICENSE=	MIT

FETCH_DEPENDS=	npm:www/npm \
		jq:textproc/jq \
		${LOCALBASE}/share/certs/ca-root-nss.crt:security/ca_root_nss
BUILD_DEPENDS=	npm:www/npm

USES=		cmake:indirect nodejs:run

PACKAGE_NAME=	@nanocollective/nanocoder

LLAMA_CPP_RELEASE=	b8390
MASTER_SITES_llama_cpp=	https://github.com/ggml-org/llama.cpp/archive/refs/tags/
DISTFILES+=		${LLAMA_CPP_RELEASE}${EXTRACT_SUFX}:llama_cpp

NODE_LLAMA_CPP_DIR=	${WRKSRC}/node_modules/@nanocollective/nanocoder/node_modules/node-llama-cpp

FETCH_SCRIPT=	${PORTSDIR}/Tools/scripts/npmjs-fetch-with-dependencies.sh

do-fetch:
	@if ! [ -f ${DISTDIR}/${PORTNAME}-${DISTVERSION}${EXTRACT_SUFX} ]; then \
		${MKDIR} ${DISTDIR} && \
		${ECHO} "====> Fetching ${PORTNAME}-${DISTVERSION}${EXTRACT_SUFX}" && \
		${SETENV} TMPDIR=${WRKDIR} LOCALBASE=${LOCALBASE} ${FETCH_SCRIPT} \
			${PACKAGE_NAME} ${DISTVERSION} \
			${FILESDIR}/package-lock.json \
			${DISTDIR}/${PORTNAME}-${DISTVERSION}${EXTRACT_SUFX}; \
	fi

post-fetch:
	@if ! [ -f ${DISTDIR}/${LLAMA_CPP_RELEASE}${EXTRACT_SUFX} ]; then \
		cd ${DISTDIR} && \
		${FETCH_CMD} \
			${MASTER_SITES_llama_cpp}${LLAMA_CPP_RELEASE}${EXTRACT_SUFX}; \
	fi

post-extract:
	@tar -xzf ${DISTDIR}/${LLAMA_CPP_RELEASE}${EXTRACT_SUFX} \
		-C ${NODE_LLAMA_CPP_DIR}/llama/
	@${MV} ${NODE_LLAMA_CPP_DIR}/llama/llama.cpp-${LLAMA_CPP_RELEASE} \
		${NODE_LLAMA_CPP_DIR}/llama/llama.cpp

do-build:
	@cd ${WRKSRC} && \
		npm rebuild --nodedir=${LOCALBASE}

x:
	${FIND} ${STAGEDIR}${PREFIX}/lib/node_modules/@nanocollective/nanocoder/node_modules/node-llama-cpp/ -name 'CMake*'

do-install:
	# install files
	cd ${WRKSRC} && \
		${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}/lib
	# remove unnecessary files
	@${RM} -rf ${STAGEDIR}${PREFIX}/lib/*.json
	# update shebang to use system node
	@${REINPLACE_CMD} -i '' \
		-e "s|#!/usr/bin/env node|#!${PREFIX}/bin/node|" \
		${STAGEDIR}${PREFIX}/lib/node_modules/@nanocollective/nanocoder/node_modules/cli-highlight/dist/cli.js
	# delete cmake files: CMake*
	${FIND} ${STAGEDIR}${PREFIX}/lib/node_modules/@nanocollective/nanocoder/node_modules/node-llama-cpp/ -name 'CMake*' | ${XARGS} ${RM} -r
	# set exec bit
	@${CHMOD} +x ${STAGEDIR}${PREFIX}/lib/node_modules/.bin/${PORTNAME}
	# create symlink in bin
	@${RLN} -s ${STAGEDIR}${PREFIX}/lib/node_modules/.bin/${PORTNAME} ${STAGEDIR}${PREFIX}/bin/${PORTNAME}

.include <bsd.port.mk>
