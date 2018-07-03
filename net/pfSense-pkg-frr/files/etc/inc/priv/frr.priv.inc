<?php
/*
 * frr.priv.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2016 Rubicon Communications, LLC (Netgate)
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

$priv_list['page-services-frr'] = array();
$priv_list['page-services-frr']['name'] = "WebCfg - Services: FRR package";
$priv_list['page-services-frr']['descr'] = "Allow access to FRR package GUI (All)";
$priv_list['page-services-frr']['match'] = array();
$priv_list['page-services-frr']['match'][] = "pkg_edit.php?xml=frr/frr_bgp_advanced.xml*";
$priv_list['page-services-frr']['match'][] = "pkg_edit.php?xml=frr/frr_bgp_aspaths.xml*";
$priv_list['page-services-frr']['match'][] = "pkg_edit.php?xml=frr/frr_bgp_communities.xml*";
$priv_list['page-services-frr']['match'][] = "pkg_edit.php?xml=frr/frr_bgp_neighbors.xml*";
$priv_list['page-services-frr']['match'][] = "pkg_edit.php?xml=frr/frr_bgp.xml*";
$priv_list['page-services-frr']['match'][] = "pkg_edit.php?xml=frr/frr_global_acls.xml*";
$priv_list['page-services-frr']['match'][] = "pkg_edit.php?xml=frr/frr_global_prefixes.xml*";
$priv_list['page-services-frr']['match'][] = "pkg_edit.php?xml=frr/frr_global_raw.xml*";
$priv_list['page-services-frr']['match'][] = "pkg_edit.php?xml=frr/frr_global_routemaps.xml*";
$priv_list['page-services-frr']['match'][] = "pkg_edit.php?xml=frr/frr_ospf6_interfaces.xml*";
$priv_list['page-services-frr']['match'][] = "pkg_edit.php?xml=frr/frr_ospf6.xml*";
$priv_list['page-services-frr']['match'][] = "pkg_edit.php?xml=frr/frr_ospf_interfaces.xml*";
$priv_list['page-services-frr']['match'][] = "pkg_edit.php?xml=frr/frr_ospf.xml*";
$priv_list['page-services-frr']['match'][] = "pkg_edit.php?xml=frr.xml*";
$priv_list['page-services-frr']['match'][] = "pkg.php?xml=frr/frr_bgp_aspaths.xml*";
$priv_list['page-services-frr']['match'][] = "pkg.php?xml=frr/frr_bgp_communities.xml*";
$priv_list['page-services-frr']['match'][] = "pkg.php?xml=frr/frr_bgp_neighbors.xml*";
$priv_list['page-services-frr']['match'][] = "pkg.php?xml=frr/frr_global_acls.xml*";
$priv_list['page-services-frr']['match'][] = "pkg.php?xml=frr/frr_global_prefixes.xml*";
$priv_list['page-services-frr']['match'][] = "pkg.php?xml=frr/frr_global_routemaps.xml*";
$priv_list['page-services-frr']['match'][] = "pkg.php?xml=frr/frr_ospf6_interfaces.xml*";
$priv_list['page-services-frr']['match'][] = "pkg.php?xml=frr/frr_ospf_interfaces.xml*";
$priv_list['page-services-frr']['match'][] = "status_frr.php*";

$priv_list['page-status-frr'] = array();
$priv_list['page-status-frr']['name'] = "WebCfg - Status: FRR package";
$priv_list['page-status-frr']['descr'] = "Allow access to FRR package Status Page";
$priv_list['page-status-frr']['match'] = array();
$priv_list['page-status-frr']['match'][] = "status_frr.php*";

?>
