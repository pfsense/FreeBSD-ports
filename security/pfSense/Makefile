# $FreeBSD$

PORTNAME=	pfSense
DISTVERSION=	2.3.6
CATEGORIES=	security
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	renato@pfsense.com
COMMENT?=	Meta package to install pfSense required ports

LICENSE=	ESF

RUN_DEPENDS=	ataidle>=0:sysutils/ataidle \
		bind-tools>=0:dns/bind-tools \
		bsnmp-regex>=0:net-mgmt/bsnmp-regex \
		bsnmp-ucd>=0:net-mgmt/bsnmp-ucd \
		bwi-firmware-kmod>=0:net/bwi-firmware-kmod \
		ca_root_nss>=0:security/ca_root_nss \
		check_reload_status>=0:sysutils/check_reload_status \
		choparp>=0:net-mgmt/choparp \
		clog>=0:sysutils/clog \
		cpdup>=0:sysutils/cpdup \
		cpustats>=0:sysutils/cpustats \
		dhcp6>=0:net/dhcp6 \
		dhcpleases6>=0:sysutils/dhcpleases6 \
		dhcpleases>=0:sysutils/dhcpleases \
		dnsmasq>=0:dns/dnsmasq \
		dpinger>=0:net/dpinger \
		expiretable>=0:security/expiretable \
		filterdns>=0:net/filterdns \
		filterlog>=0:sysutils/filterlog \
		hostapd>=0:net/hostapd \
		igmpproxy>=0:net/igmpproxy \
		ipmitool>=0:sysutils/ipmitool \
		isc-dhcp43-client>=0:net/isc-dhcp43-client \
		isc-dhcp43-relay>=0:net/isc-dhcp43-relay \
		isc-dhcp43-server>=0:net/isc-dhcp43-server \
		libxml2>=0:textproc/libxml2 \
		links>=0:www/links \
		minicron>=0:sysutils/minicron \
		miniupnpd>=0:net/miniupnpd \
		mpd4>=0:net/mpd4 \
		mpd5>=0:net/mpd5 \
		nginx>=0:www/nginx \
		ntp>=0:net/ntp \
		openvpn23>=0:security/openvpn23 \
		pecl-zmq>=0:net/pecl-zmq \
		pfSense-Status_Monitoring>=0:sysutils/pfSense-Status_Monitoring \
		pfSense-repo>=0:sysutils/pfSense-repo \
		pftop>=0:sysutils/pftop \
		php-suhosin>=0:security/php-suhosin \
		php56-pfSense-module>=0:devel/php56-pfSense-module \
		qstats>=0:sysutils/qstats \
		radvd>=0:net/radvd \
		rate>=0:net-mgmt/rate \
		relayd>=0:net/relayd \
		scponly>=0:shells/scponly \
		smartmontools>=0:sysutils/smartmontools \
		ssh_tunnel_shell>=0:sysutils/ssh_tunnel_shell \
		sshlockout_pf>=0:sysutils/sshlockout_pf \
		strongswan>=0:security/strongswan \
		uclcmd>=0:devel/uclcmd \
		unbound>=0:dns/unbound \
		voucher>=0:sysutils/voucher \
		wol>=0:net/wol \
		wpa_supplicant>=0:security/wpa_supplicant \
		xinetd>=0:security/xinetd
LIB_DEPENDS=	libltdl.so:devel/libltdl

USES=		kmod

USE_PHP=	bz2 bcmath ctype curl dom filter gettext json mbstring \
		mcrypt hash opcache openssl pcntl pdo pdo_sqlite posix \
		radius readline session shmop simplexml sockets sysvmsg sysvsem \
		sysvshm sqlite3 tokenizer xml ldap zlib xmlreader xmlwriter

NO_BUILD=	yes
NO_MTREE=	yes

CONFLICTS=	pfSense-vmware-[0-9]*

PLIST_FILES=	/etc/version.patch
PLIST_DIRS=	/etc

.include <bsd.port.pre.mk>

.if (${ARCH} == "amd64" || ${ARCH} == "i386")
RUN_DEPENDS+=	dmidecode>=0:sysutils/dmidecode \
		wrapalixresetbutton>=0:sysutils/wrapalixresetbutton \
		beep>=0:audio/beep
.endif

do-install:
	${MKDIR} ${STAGEDIR}/etc
	${ECHO} ${PORTREVISION} > ${STAGEDIR}/etc/version.patch

.include <bsd.port.post.mk>
