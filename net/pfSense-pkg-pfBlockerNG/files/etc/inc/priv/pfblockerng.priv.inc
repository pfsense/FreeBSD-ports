<?php

global $priv_list;

$priv_list['page-firewall-pfblockerng'] = array();
$priv_list['page-firewall-pfblockerng']['name'] = "WebCfg - Firewall: pfBlockerNG";
$priv_list['page-firewall-pfblockerng']['descr'] = "Allow access to pfBlockerNG package gui";
$priv_list['page-firewall-pfblockerng']['match'] = array();
$priv_list['page-firewall-pfblockerng']['match'][] = "pkg_edit.php?xml=pfblockerng.xml*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pkg_edit.php?xml=pfblockerng/pfblockerng_reputation.xml*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pkg_edit.php?xml=pfblockerng/pfblockerng_top20.xml*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pkg_edit.php?xml=pfblockerng/pfblockerng_Africa.xml*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pkg_edit.php?xml=pfblockerng/pfblockerng_Asia.xml*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pkg_edit.php?xml=pfblockerng/pfblockerng_Europe.xml*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pkg_edit.php?xml=pfblockerng/pfblockerng_NorthAmerica.xml*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pkg_edit.php?xml=pfblockerng/pfblockerng_Oceania.xml*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pkg_edit.php?xml=pfblockerng/pfblockerng_SouthAmerica.xml*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pkg_edit.php?xml=pfblockerng/pfblockerng_ProxyandSatellite.xml*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pkg_edit.php?xml=pfblockerng/pfblockerng_sync.xml*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pkg_edit.php?xml=pfblockerng/pfblockerng_dnsbl.xml*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pkg_edit.php?xml=pfblockerng/pfblockerng_dnsbl_easylist.xml*";

$priv_list['page-firewall-pfblockerng']['match'][] = "pkg.php?xml=pfblockerng/pfblockerng_v4lists.xml*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pkg.php?xml=pfblockerng/pfblockerng_v6lists.xml*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pkg.php?xml=pfblockerng/pfblockerng_dnsbl_lists.xml*";

$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_update.php*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_alerts.php*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_alerts_ar.php*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_log.php*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_threats.php*";
$priv_list['page-firewall-pfblockerng']['match'][] = "widgets/javascript/pfblockerng.js*";
$priv_list['page-firewall-pfblockerng']['match'][] = "widgets/include/widget-pfblockerng.inc*";
$priv_list['page-firewall-pfblockerng']['match'][] = "widgets/widgets/pfblockerng.widget.php*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng.inc*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_extra.inc*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_install.inc*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng.inc*";
?>