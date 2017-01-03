# $FreeBSD$

PORTNAME=	pfSense-pkg-Lightsquid
PORTVERSION=	3.0.4
PORTREVISION=	2
CATEGORIES=	www
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense package Lightsquid

LICENSE=	ESF

RUN_DEPENDS=	${LOCALBASE}/libexec/lightsquid/ip2name.list:www/lightsquid \
		${LOCALBASE}/sbin/lighttpd:www/lighttpd

CONFLICTS=	pfSense-base-nanobsd-[0-9]*

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${PREFIX}/www/sqstat
	${MKDIR} ${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novopf
	${MKDIR} ${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novopf/images
	${MKDIR} ${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novosea
	${MKDIR} ${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novosea/images
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/lightsquid.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/lightsquid.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/lightsquid.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/sqstat/sqstat.class.php \
		${STAGEDIR}${PREFIX}/www/sqstat
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/sqstat/sqstat.php \
		${STAGEDIR}${PREFIX}/www/sqstat
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/sqstat/sqstat.css \
		${STAGEDIR}${PREFIX}/www/sqstat
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/sqstat/zhabascript.js \
		${STAGEDIR}${PREFIX}/www/sqstat
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novopf/bigfiles.html \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novopf
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novopf/day_detail.html \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novopf
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novopf/graph.html \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novopf
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novopf/group_detail.html \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novopf
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novopf/images/datetime.png \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novopf/images
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novopf/images/flag_red.png \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novopf/images
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novopf/images/graph.png \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novopf/images
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novopf/images/groups.png \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novopf/images
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novopf/images/printer.png \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novopf/images
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novopf/images/users.png \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novopf/images
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novopf/index.html \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novopf
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novopf/month_detail.html \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novopf
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novopf/print.css \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novopf
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novopf/screen.css \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novopf
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novopf/topsites.html \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novopf
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novopf/user_detail.html \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novopf
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novopf/user_month.html \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novopf
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novopf/user_time.html \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novopf
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novopf/whousesite.html \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novopf
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novosea/bigfiles.html \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novosea
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novosea/day_detail.html \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novosea
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novosea/graph.html \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novosea
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novosea/group_detail.html \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novosea
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novosea/images/datetime.png \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novosea/images
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novosea/images/flag_red.png \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novosea/images
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novosea/images/graph.png \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novosea/images
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novosea/images/groups.png \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novosea/images
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novosea/images/printer.png \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novosea/images
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novosea/images/users.png \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novosea/images
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novosea/index.html \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novosea
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novosea/month_detail.html \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novosea
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novosea/print.css \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novosea
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novosea/screen.css \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novosea
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novosea/topsites.html \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novosea
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novosea/user_detail.html \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novosea
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novosea/user_month.html \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novosea
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novosea/user_time.html \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novosea
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/share/lightsquid/tpl/novosea/whousesite.html \
		${STAGEDIR}${PREFIX}/share/lightsquid/tpl/novosea
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
