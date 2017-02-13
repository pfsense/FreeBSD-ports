# $FreeBSD$

PORTNAME=	pfSense
DISTVERSION=	2.3.3
CATEGORIES=	security
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	renato@pfsense.com
COMMENT?=	Meta package to install pfSense required ports

LICENSE=	ESF

RUN_DEPENDS=	${LOCALBASE}/sbin/clog:sysutils/clog \
		${LOCALBASE}/bin/rate:net-mgmt/rate \
		${LOCALBASE}/bin/dpinger:net/dpinger \
		${LOCALBASE}/sbin/radvd:net/radvd \
		${LOCALBASE}/bin/cpdup:sysutils/cpdup \
		${LOCALBASE}/bin/gogoc:net/gogoc \
		${LOCALBASE}/bin/ipmitool:sysutils/ipmitool \
		${LOCALBASE}/bin/minicron:sysutils/minicron \
		${LOCALBASE}/bin/voucher:sysutils/voucher \
		${LOCALBASE}/bin/links:www/links \
		${LOCALBASE}/sbin/expiretable:security/expiretable \
		${LOCALBASE}/sbin/dhclient:net/isc-dhcp43-client \
		${LOCALBASE}/sbin/dhcpleases:sysutils/dhcpleases \
		${LOCALBASE}/sbin/dhcpleases6:sysutils/dhcpleases6 \
		${LOCALBASE}/sbin/choparp:net-mgmt/choparp \
		${LOCALBASE}/sbin/dhcpd:net/isc-dhcp43-server \
		${LOCALBASE}/sbin/check_reload_status:sysutils/check_reload_status \
		${LOCALBASE}/sbin/dhcrelay:net/isc-dhcp43-relay \
		${LOCALBASE}/sbin/dhcp6c:net/dhcp6 \
		${LOCALBASE}/sbin/dnsmasq:dns/dnsmasq \
		${LOCALBASE}/sbin/igmpproxy:net/igmpproxy \
		${LOCALBASE}/sbin/filterdns:net/filterdns \
		${LOCALBASE}/sbin/filterlog:sysutils/filterlog \
		${LOCALBASE}/sbin/nginx:www/nginx \
		${LOCALBASE}/sbin/ssh_tunnel_shell:sysutils/ssh_tunnel_shell \
		${LOCALBASE}/sbin/miniupnpd:net/miniupnpd \
		${LOCALBASE}/sbin/mpd4:net/mpd4 \
		${LOCALBASE}/sbin/mpd5:net/mpd5 \
		${LOCALBASE}/sbin/openvpn:security/openvpn23 \
		${LOCALBASE}/sbin/pftop:sysutils/pftop \
		${LOCALBASE}/sbin/relayctl:net/relayd \
		${LOCALBASE}/sbin/sshlockout_pf:sysutils/sshlockout_pf \
		${LOCALBASE}/sbin/qstats:sysutils/qstats \
		${LOCALBASE}/bin/wol:net/wol \
		${LOCALBASE}/sbin/smartctl:sysutils/smartmontools \
		${LOCALBASE}/sbin/ataidle:sysutils/ataidle \
		${LOCALBASE}/sbin/cpustats:sysutils/cpustats \
		${LOCALBASE}/bin/xmllint:textproc/libxml2 \
		${LOCALBASE}/sbin/ntp-keygen:net/ntp \
		${LOCALBASE}/lib/snmp_regex.a:net-mgmt/bsnmp-regex \
		${LOCALBASE}/lib/snmp_ucd.so:net-mgmt/bsnmp-ucd \
		${LOCALBASE}/share/certs/ca-root-nss.crt:security/ca_root_nss \
		${LOCALBASE}/bin/nsupdate:dns/bind-tools \
		${LOCALBASE}/sbin/ipsec:security/strongswan \
		${LOCALBASE}/sbin/unbound:dns/unbound \
		${KMODDIR}/bwi_v3_ucode.ko:net/bwi-firmware-kmod \
		${LOCALBASE}/bin/scponly:shells/scponly \
		${LOCALBASE}/sbin/xinetd:security/xinetd \
		${LOCALBASE}/www/status_monitoring.php:sysutils/pfSense-Status_Monitoring \
		${LOCALBASE}/bin/uclcmd:devel/uclcmd \
		${DATADIR}/pkg/repos/pfSense-repo.conf:sysutils/pfSense-repo

LIB_DEPENDS=	libltdl.so:devel/libltdl

USE_PHP=	bz2 bcmath ctype curl dom filter gettext json mbstring \
		mcrypt hash opcache openssl pcntl pdo pdo_sqlite posix \
		radius readline session shmop simplexml sockets sysvmsg sysvsem \
		sysvshm sqlite3 tokenizer xml ldap zlib xmlreader xmlwriter

USES=		kmod

NO_BUILD=	yes
NO_MTREE=	yes

CONFLICTS=	pfSense-vmware-[0-9]*

PLIST_FILES=	/etc/version.patch
PLIST_DIRS=	/etc

.include <bsd.port.pre.mk>

.if (${ARCH} == "amd64" || ${ARCH} == "i386")
RUN_DEPENDS+=	${LOCALBASE}/sbin/dmidecode:sysutils/dmidecode \
		${LOCALBASE}/sbin/wrapresetbtn:sysutils/wrapalixresetbutton \
		${LOCALBASE}/bin/beep:audio/beep
.endif

RUN_DEPENDS+=	${PHPBASE}/lib/php/${PHP_EXT_DIR}/pfSense.so:devel/php56-pfSense-module \
		${PHPBASE}/lib/php/${PHP_EXT_DIR}/ssh2.so:security/pecl-ssh2 \
		${PHPBASE}/lib/php/${PHP_EXT_DIR}/suhosin.so:security/php-suhosin \
		${PHPBASE}/lib/php/${PHP_EXT_DIR}/xdebug.so:devel/php-xdebug \
		${PHPBASE}/lib/php/${PHP_EXT_DIR}/zmq.so:net/pecl-zmq

do-install:
	${MKDIR} ${STAGEDIR}/etc
	${ECHO} ${PORTREVISION} > ${STAGEDIR}/etc/version.patch

.include <bsd.port.post.mk>
