<?php
/*
 * quagga_ospfd.priv.inc
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

$priv_list['page-services-quagga'] = array();
$priv_list['page-services-quagga']['name'] = "WebCfg - Services: Quagga OSPFd package";
$priv_list['page-services-quagga']['descr'] = "Allow access to Quagga OSPFd package GUI";

$priv_list['page-services-quagga']['match'] = array();
$priv_list['page-services-quagga']['match'][] = "pkg.php?xml=quagga_ospfd.xml*";
$priv_list['page-services-quagga']['match'][] = "pkg.php?xml=quagga_ospfd_interfaces.xml*";
$priv_list['page-services-quagga']['match'][] = "pkg.php?xml=quagga_ospfd_raw.xml*";

$priv_list['page-services-quagga']['match'][] = "pkg_edit.php?xml=quagga_ospfd.xml*";
$priv_list['page-services-quagga']['match'][] = "pkg_edit.php?xml=quagga_ospfd_interfaces.xml*";
$priv_list['page-services-quagga']['match'][] = "pkg_edit.php?xml=quagga_ospfd_raw.xml*";

$priv_list['page-services-quagga']['match'][] = "status_ospfd.php*";

?>
