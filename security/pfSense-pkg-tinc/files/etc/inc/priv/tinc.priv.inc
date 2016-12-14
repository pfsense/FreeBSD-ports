<?php
/*
 * tinc.priv.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016 Rubicon Communications, LLC (Netgate)
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

$priv_list['page-vpn-tinc'] = array();
$priv_list['page-vpn-tinc']['name'] = "WebCfg - VPN: tinc package";
$priv_list['page-vpn-tinc']['descr'] = "Allow access to tinc package GUI";
$priv_list['page-vpn-tinc']['match'] = array();

$priv_list['page-vpn-tinc']['match'][] = "pkg.php?xml=tinc.xml*";
$priv_list['page-vpn-tinc']['match'][] = "pkg.php?xml=tinc_hosts.xml*";

$priv_list['page-vpn-tinc']['match'][] = "pkg_edit.php?xml=tinc.xml*";
$priv_list['page-vpn-tinc']['match'][] = "pkg_edit.php?xml=tinc_hosts.xml*";

$priv_list['page-vpn-tinc']['match'][] = "status_tinc.php*";

?>
