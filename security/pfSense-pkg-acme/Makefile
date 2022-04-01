# $FreeBSD$

PORTNAME=	pfSense-pkg-acme
PORTVERSION=	0.7.1
PORTREVISION=	1
CATEGORIES=	security
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	PiBa.NL.dev@gmail.com
COMMENT=	ACME package for pfSense

LICENSE=	APACHE20

RUN_DEPENDS=	${LOCALBASE}/bin/socat:net/socat \
		${PHP_PKGNAMEPREFIX}pecl-ssh2>=0:security/pecl-ssh2@${PHP_FLAVOR}

IGNORE_WITH_PHP=	56 70 71
USES=		php:flavors
USE_PHP=	ftp

NO_BUILD=	yes
NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${MKDIR} ${STAGEDIR}${PREFIX}/www/acme
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/acme.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/acme/acme.inc \
		${STAGEDIR}${PREFIX}/pkg/acme
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/acme.sh \
		${STAGEDIR}${PREFIX}/pkg/acme
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/acme/acme_sh.inc \
		${STAGEDIR}${PREFIX}/pkg/acme
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/acme_command.sh \
		${STAGEDIR}${PREFIX}/pkg/acme
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/acme/acme_gui.inc \
		${STAGEDIR}${PREFIX}/pkg/acme
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/acme/acme_htmllist.inc \
		${STAGEDIR}${PREFIX}/pkg/acme
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/acme/acme_serverconnectors.inc \
		${STAGEDIR}${PREFIX}/pkg/acme
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/acme/acme_utils.inc \
		${STAGEDIR}${PREFIX}/pkg/acme
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/acme/pkg_acme_tabs.inc \
		${STAGEDIR}${PREFIX}/pkg/acme
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_1984hosting.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_acmedns.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_acmeproxy.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_active24.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_ad.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_ali.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_anx.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_arvan.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_aurora.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_autodns.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_aws.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_azion.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_azure.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_cf.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_clouddns.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_cloudns.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_cn.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_conoha.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_constellix.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_cpanel.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_curanet.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_cx.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_cyon.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_da.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_ddnss.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_desec.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_df.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_dgon.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_dnshome.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_dnsimple.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_doapi.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_domeneshop.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_do.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_dpi.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_dp.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_dreamhost.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_duckdns.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_durabledns.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_dyn.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_dynu.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_easydns.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_edgedns.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_euserv.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_exoscale.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_freedns.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_gandi_livedns.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_gcloud.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_gdnsdk.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_gd.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_geoscaling.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_he.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_hetzner.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_hexonet.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_hostingde.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_huaweicloud.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_infoblox.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_infomaniak.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_internetbs.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_inwx.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_ionos.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_ispconfig.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_jd.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_joker.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_kappernet.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_kas.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_kinghost.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_knot.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_leaseweb.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_lexicon.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_linode.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_linode_v4.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_loopia.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_lua.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_me.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_miab.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_misaka.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_myapi.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_mydnsjp.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_mythic_beasts.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_namecheap.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_namecom.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_namesilo.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_nederhost.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_neodigit.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_netcup.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_netlify.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_nic.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_njalla.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_nm.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_nsone.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_nsupdate.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_nw.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_oci.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_one.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_online.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_openprovider.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_ovh.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_pdns.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_pleskxml.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_pointhq.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_porkbun.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_rackcorp.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_rackspace.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_rcode0.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_regru.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_scaleway.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_schlundtech.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_selectel.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_simply.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_servercow.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_tele3.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_udr.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_ultra.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_unoeuro.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_variomedia.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_veesp.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_vscale.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_vultr.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_websupport.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_world4you.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_yandex.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_zilore.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_zone.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/pkg/acme/dnsapi/dns_zonomi.sh \
		${STAGEDIR}${PREFIX}/pkg/acme/dnsapi
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/acme/acme_accountkeys.php \
		${STAGEDIR}${PREFIX}/www/acme
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/acme/acme_accountkeys_edit.php \
		${STAGEDIR}${PREFIX}/www/acme
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/acme/acme_certificates.php \
		${STAGEDIR}${PREFIX}/www/acme
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/acme/acme_certificates_edit.php \
		${STAGEDIR}${PREFIX}/www/acme
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/acme/acme_generalsettings.php \
		${STAGEDIR}${PREFIX}/www/acme
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/acme.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
