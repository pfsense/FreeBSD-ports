# Created by: Steve Thames <sthames42@gmail.com>
# $FreeBSD$
#*******************************************************************************
# vHosts package makefile.
# see: https://github.com/pfsense/FreeBSD-ports/blob/devel/Mk/bsd.port.mk
# and: https://www.freebsd.org/doc/en/books/porters-handbook/
# ------------------------------------------------------------------------------
# Part of pfSense 2.3 and later (https://www.pfSense.org/).
# Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
#
# Inspired by vhosts package originally written by Mark Crane.
# Copyright (C) 2008 Mark J Crane
# Copyright (C) 2015 ESF, LLC
# Copyright (C) 2016 Softlife Consulting
# All rights reserved.
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
# http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.
#*******************************************************************************

PORTNAME=		pfSense-pkg-vHosts
PORTVERSION=	1.0.0
#PORTREVISION=	1
CATEGORIES=	 	www
MASTER_SITES=	# empty
DISTFILES=		# empty
EXTRACT_ONLY=	# empty

MAINTAINER=		sthames42@gmail.com
COMMENT=	    Web Server Package for pfSense

LICENSE=	    APACHE20

NO_BUILD=	    yes
NO_MTREE=	    yes

SUB_FILES=		pkg-install pkg-deinstall
SUB_LIST=		PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/shortcuts
	${MKDIR} ${STAGEDIR}${PREFIX}/www/packages
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/vhosts.inc 			${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/vhosts.xml 			${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/vhosts.inc 			${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/shortcuts/vhosts.inc	${STAGEDIR}${PREFIX}/pkg/shortcuts
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/vhosts.php 	${STAGEDIR}${PREFIX}/www/packages
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml 					${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" ${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
