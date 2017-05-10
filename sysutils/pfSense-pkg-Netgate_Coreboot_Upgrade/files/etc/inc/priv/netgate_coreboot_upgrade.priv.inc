<?php
/*
 * netgate_coreboot_upgrade.priv.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2017 Rubicon Communications, LLC (Netgate)
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

$priv_list['page-system-netgate-coreboot-upgrade'] = array();
$priv_list['page-system-netgate-coreboot-upgrade']['name'] = "System - Netgate Coreboot Upgrade";
$priv_list['page-system-netgate-coreboot-upgrade']['descr'] = "Allow access to 'System: Netgate Coreboot Upgrade'";
$priv_list['page-system-netgate-coreboot-upgrade']['match'] = array();
$priv_list['page-system-netgate-coreboot-upgrade']['match'][] = "netgate_coreboot_upgrade.php*";

?>
