<?php
/*
 * pfblockerng.priv.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008-2022 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2022 BBcan177@gmail.com
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

global $priv_list;

$priv_list['page-firewall-pfblockerng'] = array();
$priv_list['page-firewall-pfblockerng']['name'] = gettext("WebCfg - Firewall: pfBlockerNG");
$priv_list['page-firewall-pfblockerng']['descr'] = gettext("Allow access to pfBlockerNG package gui");
$priv_list['page-firewall-pfblockerng']['match'] = array();
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_general.php";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_ip.php*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_dnsbl.php*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_update.php";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_alerts.php*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_feeds.php";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_log.php";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_sync.php";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_category.php*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_category_edit.php*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_reputation.php";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_Top_Spammers.php";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_Africa.php";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_Antarctica.php";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_Asia.php";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_Europe.php";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_North_America.php";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_Oceania.php";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_South_America.php";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_Proxy_and_Satellite.php";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_reputation.php";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_blacklist.php*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_threats.php*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/pfblockerng_safesearch.php";
$priv_list['page-firewall-pfblockerng']['match'][] = "pfblockerng/www/index.php";
$priv_list['page-firewall-pfblockerng']['match'][] = "wizards/pfblockerng_wizard.xml";
$priv_list['page-firewall-pfblockerng']['match'][] = "widgets/widgets/pfblockerng.widget.php";

$priv_list['page-dashboard-pfblockerng'] = array();
$priv_list['page-dashboard-pfblockerng']['name'] = gettext("WebCfg - Dashboard Widget (pfBlockerNG)");
$priv_list['page-dashboard-pfblockerng']['descr'] = gettext("Allow access to dashboard (pfBlockerNG)");
$priv_list['page-dashboard-pfblockerng']['match'] = array();
$priv_list['page-dashboard-pfblockerng']['match'][] = "widgets/widgets/pfblockerng.widget.php";
?>
