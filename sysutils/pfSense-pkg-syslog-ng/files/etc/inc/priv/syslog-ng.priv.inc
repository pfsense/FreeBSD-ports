<?php
/*
 * syslog-ng.priv.inc
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

$priv_list['page-services-syslogng'] = array();
$priv_list['page-services-syslogng']['name'] = "WebCfg - Services: syslog-ng package";
$priv_list['page-services-syslogng']['descr'] = "Allow access to syslog-ng package GUI";
$priv_list['page-services-syslogng']['match'] = array();

$priv_list['page-services-syslogng']['match'][] = "pkg.php?xml=syslogng.xml*";
$priv_list['page-services-syslogng']['match'][] = "pkg.php?xml=syslog-ng_advanced.xml*";

$priv_list['page-services-syslogng']['match'][] = "pkg_edit.php?xml=syslogng.xml*";
$priv_list['page-services-syslogng']['match'][] = "pkg_edit.php?xml=syslog-ng_advanced.xml*";

$priv_list['page-services-syslogng']['match'][] = "syslog-ng_log_viewer.php*";

?>
