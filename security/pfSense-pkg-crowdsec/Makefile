# $FreeBSD$

PORTNAME=	pfSense-pkg-crowdsec
PORTVERSION=	0.1.3
CATEGORIES=	security
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	marco@crowdsec.net
COMMENT=	Crowdsec package for pfSense

LICENSE=	APACHE20

RUN_DEPENDS=	crowdsec>=1.5.5:security/crowdsec \
		crowdsec-firewall-bouncer>=0.0.28_1:security/crowdsec-firewall-bouncer

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${PREFIX}/www/shortcuts
	${MKDIR} ${STAGEDIR}${PREFIX}/www/crowdsec/js
	${MKDIR} ${STAGEDIR}${PREFIX}/www/crowdsec/css
	${MKDIR} ${STAGEDIR}${PREFIX}/www/crowdsec/images
	${MKDIR} ${STAGEDIR}${PREFIX}/www/crowdsec/endpoint
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${DATADIR}
	${MKDIR} ${STAGEDIR}${PREFIX}/etc/rc.d/
	${MKDIR} ${STAGEDIR}${PREFIX}/etc/rc.conf.d/
	${MKDIR} ${STAGEDIR}${PREFIX}/etc/crowdsec/
	${MKDIR} ${STAGEDIR}${PREFIX}/etc/crowdsec/acquis.d/
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/crowdsec.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/www/crowdsec/status.php \
			${STAGEDIR}${PREFIX}/www/crowdsec
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/www/crowdsec/metrics.php \
            			${STAGEDIR}${PREFIX}/www/crowdsec
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/www/crowdsec/metrics.html \
            			${STAGEDIR}${PREFIX}/www/crowdsec
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/www/crowdsec/endpoint/api.php \
          			${STAGEDIR}${PREFIX}/www/crowdsec/endpoint
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/www/crowdsec/js/crowdsec.js \
           			${STAGEDIR}${PREFIX}/www/crowdsec/js
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/www/crowdsec/js/jquery.bootgrid.min.js \
           			${STAGEDIR}${PREFIX}/www/crowdsec/js
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/www/crowdsec/js/jquery.bootgrid.fa.min.js \
					${STAGEDIR}${PREFIX}/www/crowdsec/js
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/www/crowdsec/js/moment.min.js \
					${STAGEDIR}${PREFIX}/www/crowdsec/js
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/www/crowdsec/css/jquery.bootgrid.min.css \
				${STAGEDIR}${PREFIX}/www/crowdsec/css
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/shortcuts/crowdsec.inc \
			${STAGEDIR}${PREFIX}/www/shortcuts
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/crowdsec.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/crowdsec.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/etc/crowdsec/feature.yaml \
		${STAGEDIR}${PREFIX}/etc/crowdsec
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/etc/crowdsec/acquis.d/pfsense.yaml \
		${STAGEDIR}${PREFIX}/etc/crowdsec/acquis.d
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}

	(cd ${FILESDIR}${PREFIX}/pkg/crowdsec && \
		${COPYTREE_SHARE} . ${STAGEDIR}${PREFIX}/pkg/crowdsec)

	${INSTALL_DATA} -m 0755 ${FILESDIR}${PREFIX}/etc/rc.d/* \
		${STAGEDIR}${PREFIX}/etc/rc.d/

	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
