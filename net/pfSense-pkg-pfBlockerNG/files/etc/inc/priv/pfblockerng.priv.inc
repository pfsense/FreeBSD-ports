<?php
/*
 * pfblockerng.priv.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2014-2019 BBcan177 <bbcan177@gmail.com>
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
$priv_list['page-firewall-pfblockerng']['name'] = "WebCfg - Firewall: pfBlockerNG";
$priv_list['page-firewall-pfblockerng']['descr'] = "Allow access to pfBlockerNG package gui";
$priv_list['page-firewall-pfblockerng']['match'] = array();
$priv_list['page-firewall-pfblockerng']['match'][] = "pkg_edit.php?xml=pfblockerng.xml*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pkg_edit.php?xml=pfblockerng/pfblockerng_reputation.xml*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pkg_edit.php?xml=pfblockerng/pfblockerng_TopSpammers.xml*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pkg_edit.php?xml=pfblockerng/pfblockerng_Africa.xml*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pkg_edit.php?xml=pfblockerng/pfblockerng_Antarctica.xml*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pkg_edit.php?xml=pfblockerng/pfblockerng_Asia.xml*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pkg_edit.php?xml=pfblockerng/pfblockerng_Europe.xml*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pkg_edit.php?xml=pfblockerng/pfblockerng_North_America.xml*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pkg_edit.php?xml=pfblockerng/pfblockerng_Oceania.xml*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pkg_edit.php?xml=pfblockerng/pfblockerng_South_America.xml*";
$priv_list['page-firewall-pfblockerng']['match'][] = "pkg_edit.php?xml=pfblockerng/pfblockerng_Proxy_and_Satellite.xml*";
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
