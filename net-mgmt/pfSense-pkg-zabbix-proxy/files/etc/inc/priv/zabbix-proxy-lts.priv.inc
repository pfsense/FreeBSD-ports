<?php
/*
 * zabbix-proxy-lts.priv.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015 Rubicon Communications, LLC (Netgate)
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

$priv_list['page-services-zabbix-proxy-lts'] = array();
$priv_list['page-services-zabbix-proxy-lts']['name'] = "WebCfg - Services: Zabbix Proxy LTS package";
$priv_list['page-services-zabbix-proxy-lts']['descr'] = "Allow access to Zabbix Proxy LTS package GUI";

$priv_list['page-services-zabbix-proxy-lts']['match'] = array();
$priv_list['page-services-zabbix-proxy-lts']['match'][] = "pkg_edit.php?xml=zabbix-proxy-lts.xml*";

?>
