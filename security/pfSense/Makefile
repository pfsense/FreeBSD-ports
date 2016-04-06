# $FreeBSD$

PORTNAME=	pfSense
DISTVERSION=	2.3
CATEGORIES=	security
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	renato@pfsense.com
COMMENT?=	Meta package to install pfSense required ports

LICENSE=	ESF

RUN_DEPENDS=	${LOCALBASE}/sbin/clog:${PORTSDIR}/sysutils/clog \
		${LOCALBASE}/bin/rate:${PORTSDIR}/net-mgmt/rate \
		${LOCALBASE}/bin/dpinger:${PORTSDIR}/net/dpinger \
		${LOCALBASE}/sbin/radvd:${PORTSDIR}/net/radvd \
		${LOCALBASE}/bin/cpdup:${PORTSDIR}/sysutils/cpdup \
		${LOCALBASE}/bin/gogoc:${PORTSDIR}/net/gogoc \
		${LOCALBASE}/bin/ipmitool:${PORTSDIR}/sysutils/ipmitool \
		${LOCALBASE}/bin/minicron:${PORTSDIR}/sysutils/minicron \
		${LOCALBASE}/bin/voucher:${PORTSDIR}/sysutils/voucher \
		${LOCALBASE}/bin/links:${PORTSDIR}/www/links \
		${LOCALBASE}/sbin/expiretable:${PORTSDIR}/security/expiretable \
		${LOCALBASE}/sbin/dhclient:${PORTSDIR}/net/isc-dhcp43-client \
		${LOCALBASE}/sbin/dhcpleases:${PORTSDIR}/sysutils/dhcpleases \
		${LOCALBASE}/sbin/dhcpleases6:${PORTSDIR}/sysutils/dhcpleases6 \
		${LOCALBASE}/sbin/choparp:${PORTSDIR}/net-mgmt/choparp \
		${LOCALBASE}/sbin/dhcpd:${PORTSDIR}/net/isc-dhcp43-server \
		${LOCALBASE}/sbin/check_reload_status:${PORTSDIR}/sysutils/check_reload_status \
		${LOCALBASE}/sbin/dhcrelay:${PORTSDIR}/net/isc-dhcp43-relay \
		${LOCALBASE}/sbin/dhcp6c:${PORTSDIR}/net/dhcp6 \
		${LOCALBASE}/sbin/dnsmasq:${PORTSDIR}/dns/dnsmasq-devel \
		${LOCALBASE}/sbin/igmpproxy:${PORTSDIR}/net/igmpproxy \
		${LOCALBASE}/sbin/filterdns:${PORTSDIR}/net/filterdns \
		${LOCALBASE}/sbin/filterlog:${PORTSDIR}/sysutils/filterlog \
		${LOCALBASE}/sbin/nginx:${PORTSDIR}/www/nginx \
		${LOCALBASE}/sbin/ssh_tunnel_shell:${PORTSDIR}/sysutils/ssh_tunnel_shell \
		${LOCALBASE}/sbin/miniupnpd:${PORTSDIR}/net/miniupnpd \
		${LOCALBASE}/sbin/mpd4:${PORTSDIR}/net/mpd4 \
		${LOCALBASE}/sbin/mpd5:${PORTSDIR}/net/mpd5 \
		${LOCALBASE}/sbin/openvpn:${PORTSDIR}/security/openvpn \
		${LOCALBASE}/sbin/pftop:${PORTSDIR}/sysutils/pftop \
		${LOCALBASE}/sbin/relayctl:${PORTSDIR}/net/relayd \
		${LOCALBASE}/sbin/sshlockout_pf:${PORTSDIR}/sysutils/sshlockout_pf \
		${LOCALBASE}/sbin/qstats:${PORTSDIR}/sysutils/qstats \
		${LOCALBASE}/bin/wol:${PORTSDIR}/net/wol \
		${LOCALBASE}/sbin/smartctl:${PORTSDIR}/sysutils/smartmontools \
		${LOCALBASE}/sbin/ataidle:${PORTSDIR}/sysutils/ataidle \
		${LOCALBASE}/sbin/cpustats:${PORTSDIR}/sysutils/cpustats \
		${LOCALBASE}/bin/xmllint:${PORTSDIR}/textproc/libxml2 \
		${LOCALBASE}/sbin/ntp-keygen:${PORTSDIR}/net/ntp \
		${LOCALBASE}/lib/snmp_regex.a:${PORTSDIR}/net-mgmt/bsnmp-regex \
		${LOCALBASE}/lib/snmp_ucd.so:${PORTSDIR}/net-mgmt/bsnmp-ucd \
		${LOCALBASE}/share/certs/ca-root-nss.crt:${PORTSDIR}/security/ca_root_nss \
		${LOCALBASE}/bin/nsupdate:${PORTSDIR}/dns/bind-tools \
		${LOCALBASE}/sbin/ipsec:${PORTSDIR}/security/strongswan \
		${LOCALBASE}/sbin/unbound:${PORTSDIR}/dns/unbound \
		${KMODDIR}/bwi_v3_ucode.ko:${PORTSDIR}/net/bwi-firmware-kmod \
		${LOCALBASE}/bin/scponly:${PORTSDIR}/shells/scponly \
		${LOCALBASE}/sbin/xinetd:${PORTSDIR}/security/xinetd \
		${LOCALBASE}/www/status_monitoring.php:${PORTSDIR}/sysutils/pfSense-Status_Monitoring

LIB_DEPENDS=	libltdl.so:${PORTSDIR}/devel/libltdl

USE_PHP=	bz2 bcmath ctype curl dom filter gettext json mbstring \
		mcrypt hash opcache openssl pcntl pdo pdo_sqlite posix \
		radius readline session shmop simplexml sockets sysvmsg sysvsem \
		sysvshm sqlite3 tokenizer xml ldap zlib xmlreader xmlwriter

USES=		kmod

NO_BUILD=	yes
NO_MTREE=	yes

CONFLICTS=	pfSense-vmware-[0-9]*

.include <bsd.port.pre.mk>

.if (${ARCH} == "amd64" || ${ARCH} == "i386")
RUN_DEPENDS+=	${LOCALBASE}/sbin/dmidecode:${PORTSDIR}/sysutils/dmidecode \
		${LOCALBASE}/sbin/wrapresetbtn:${PORTSDIR}/sysutils/wrapalixresetbutton \
		${LOCALBASE}/bin/beep:${PORTSDIR}/audio/beep
.endif

RUN_DEPENDS+=	${PHPBASE}/lib/php/${PHP_EXT_DIR}/pfSense.so:${PORTSDIR}/devel/php56-pfSense-module \
		${PHPBASE}/lib/php/${PHP_EXT_DIR}/ssh2.so:${PORTSDIR}/security/pecl-ssh2 \
		${PHPBASE}/lib/php/${PHP_EXT_DIR}/suhosin.so:${PORTSDIR}/security/php-suhosin \
		${PHPBASE}/lib/php/${PHP_EXT_DIR}/xdebug.so:${PORTSDIR}/devel/php-xdebug \
		${PHPBASE}/lib/php/${PHP_EXT_DIR}/zmq.so:${PORTSDIR}/net/pecl-zmq

do-install:	# empty

.include <bsd.port.post.mk>
