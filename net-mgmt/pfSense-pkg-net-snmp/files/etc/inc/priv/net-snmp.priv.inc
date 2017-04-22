<?php
/*
 * net-snmp.priv.inc
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

$priv_list['page-services-net-snmp'] = array();
$priv_list['page-services-net-snmp']['name'] = "WebCfg - Services: NET-SNMP package";
$priv_list['page-services-net-snmp']['descr'] = "Allow access to NET-SNMP package GUI";

$priv_list['page-services-net-snmp']['match'] = array();
$priv_list['page-services-net-snmp']['match'][] = "pkg*.php?xml=net-snmp.xml*";
$priv_list['page-services-net-snmp']['match'][] = "pkg*.php?xml=net-snmp_communities.xml*";
$priv_list['page-services-net-snmp']['match'][] = "pkg*.php?xml=net-snmp_hostinfo.xml*";
$priv_list['page-services-net-snmp']['match'][] = "pkg*.php?xml=net-snmp_trapgen.xml*";
$priv_list['page-services-net-snmp']['match'][] = "pkg*.php?xml=net-snmp_users.xml*";
$priv_list['page-services-net-snmp']['match'][] = "pkg*.php?xml=net-snmptrapd.xml*";
$priv_list['page-services-net-snmp']['match'][] = "pkg*.php?xml=net-snmptrapd_communities.xml*";
$priv_list['page-services-net-snmp']['match'][] = "pkg*.php?xml=net-snmptrapd_formats.xml*";
$priv_list['page-services-net-snmp']['match'][] = "pkg*.php?xml=net-snmptrapd_forwards.xml*";
$priv_list['page-services-net-snmp']['match'][] = "pkg*.php?xml=net-snmptrapd_traphandles.xml*";
$priv_list['page-services-net-snmp']['match'][] = "pkg*.php?xml=net-snmptrapd_users.xml*";

?>
