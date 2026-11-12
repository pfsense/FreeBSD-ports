<?php
/*
 * dhcp_lease_manager.priv.inc
 *
 * Copyright (c) 2026 Branson Matheson
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

$priv_list['page-status-dhcpleasemanager'] = array();
$priv_list['page-status-dhcpleasemanager']['name'] = "WebCfg - Status: DHCP Lease Manager";
$priv_list['page-status-dhcpleasemanager']['descr'] = "Allow access to the DHCP Lease Manager page.";
$priv_list['page-status-dhcpleasemanager']['match'] = array();
$priv_list['page-status-dhcpleasemanager']['match'][] = "status_dhcp_lease_manager.php*";
$priv_list['page-status-dhcpleasemanager']['match'][] = "dhcp_lease_manager/*";
