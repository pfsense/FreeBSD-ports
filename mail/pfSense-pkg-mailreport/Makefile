# $FreeBSD$

PORTNAME=	pfSense-pkg-mailreport
PORTVERSION=	3.1
CATEGORIES=	mail
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	coreteam@pfsense.org
COMMENT=	pfSense package mailreport

LICENSE=	APACHE20

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}${PREFIX}/bin
	${MKDIR} ${STAGEDIR}/etc/inc
	${MKDIR} ${STAGEDIR}/etc/inc/phpmailer
	${MKDIR} ${STAGEDIR}${PREFIX}/www
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/mailreport.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} -m 0755 ${FILESDIR}${PREFIX}/bin/mail_reports_generate.php \
		${STAGEDIR}${PREFIX}/bin
	${INSTALL_DATA} ${FILESDIR}/etc/inc/mail_reports.inc \
		${STAGEDIR}/etc/inc
	${INSTALL_DATA} ${FILESDIR}/etc/inc/phpmailer/PHPMailerAutoload.php \
		${STAGEDIR}/etc/inc/phpmailer
	${INSTALL_DATA} ${FILESDIR}/etc/inc/phpmailer/class.phpmailer.php \
		${STAGEDIR}/etc/inc/phpmailer
	${INSTALL_DATA} ${FILESDIR}/etc/inc/phpmailer/class.pop3.php \
		${STAGEDIR}/etc/inc/phpmailer
	${INSTALL_DATA} ${FILESDIR}/etc/inc/phpmailer/class.smtp.php \
		${STAGEDIR}/etc/inc/phpmailer
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/status_mail_report.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/status_mail_report_edit.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/status_mail_report_add_cmd.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/status_mail_report_add_log.php \
		${STAGEDIR}${PREFIX}/www
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
