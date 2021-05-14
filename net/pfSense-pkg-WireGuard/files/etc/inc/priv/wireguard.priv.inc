<?php
/*
 * wireguard.priv.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2021 R. Christian McDonald
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

$priv_list['page-vpn-wireguard'] = array();
$priv_list['page-vpn-wireguard']['name'] = "WebCfg - VPN: WireGuard";
$priv_list['page-vpn-wireguard']['descr'] = "Allow access to the 'VPN: WireGuard' page.";
$priv_list['page-vpn-wireguard']['match'] = array();

$priv_list['page-vpn-wireguard']['match'][] = "wg/vpn_wg_tunnels.php";
$priv_list['page-vpn-wireguard']['match'][] = "wg/vpn_wg_tunnels_edit.php";
$priv_list['page-vpn-wireguard']['match'][] = "wg/vpn_wg_peers.php";
$priv_list['page-vpn-wireguard']['match'][] = "wg/vpn_wg_peers_edit.php";
$priv_list['page-vpn-wireguard']['match'][] = "wg/vpn_wg_settings.php";

$priv_list['page-status-wireguard'] = array();
$priv_list['page-status-wireguard']['name'] = "WebCfg - Status: WireGuard Status";
$priv_list['page-status-wireguard']['descr'] = "Allow access to the 'Status: WireGuard Status' page.";
$priv_list['page-status-wireguard']['match'] = array();

$priv_list['page-status-wireguard']['match'][] = "wg/status_wireguard.php";

?>
