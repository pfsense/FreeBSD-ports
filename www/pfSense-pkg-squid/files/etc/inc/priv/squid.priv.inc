<?php
/*
 * squid.priv.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2024 Rubicon Communications, LLC (Netgate)
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

$priv_list['page-services-squid3'] = array();
$priv_list['page-services-squid3']['name'] = "WebCfg - Services: Squid package";
$priv_list['page-services-squid3']['descr'] = "Allow access to Squid package GUI";
$priv_list['page-services-squid3']['match'] = array();

$priv_list['page-services-squid3']['match'][] = "pkg.php?xml=squid_reverse_peer.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg.php?xml=squid_reverse_redir.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg.php?xml=squid_reverse_uri.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg.php?xml=squid_upstream.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg.php?xml=squid_users.xml*";

$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_antivirus.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_auth.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_cache.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_nac.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_reverse.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_reverse_general.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_reverse_peer.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_reverse_redir.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_reverse_sync.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_reverse_uri.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_sync.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_traffic.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_upstream.xml*";
$priv_list['page-services-squid3']['match'][] = "pkg_edit.php?xml=squid_users.xml*";

$priv_list['page-services-squid3']['match'][] = "shortcuts/pkg_squid.inc*";
$priv_list['page-services-squid3']['match'][] = "squid_monitor.php*";
$priv_list['page-services-squid3']['match'][] = "squid_monitor_data.php*";
$priv_list['page-services-squid3']['match'][] = "squid_log_parser.php*";

?>
