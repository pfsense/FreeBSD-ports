<?php
/*
 * ladvd.priv.inc
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

$priv_list['page-services-ladvd'] = array();
$priv_list['page-services-ladvd']['name'] = "WebCfg - Services: ladvd";
$priv_list['page-services-ladvd']['descr'] = "Allow access to ladvd package GUI";
$priv_list['page-services-ladvd']['match'] = array();
$priv_list['page-services-ladvd']['match'][] = "pkg_edit.php?xml=ladvd.xml*";

$priv_list['page-services-ladvd-status'] = array();
$priv_list['page-services-ladvd-status']['name'] = "WebCfg - Status: ladvd";
$priv_list['page-services-ladvd-status']['descr'] = "Allow access to ladvd status page";
$priv_list['page-services-ladvd-status']['match'] = array();
$priv_list['page-services-ladvd-status']['match'][] = "status_ladvd.php*";

?>
