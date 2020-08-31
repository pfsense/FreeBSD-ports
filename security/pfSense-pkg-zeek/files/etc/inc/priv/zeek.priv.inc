<?php
/*
* zeek.priv.inc
* part of pfSense (https://www.pfSense.org/)
* Copyright (c) 2018-2020 Prosper Doko
* Copyright (c) 2020 Mark Overholser
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

$priv_list['page-services-zeek'] = array();
$priv_list['page-services-zeek']['name'] = "WebCfg - Services: zeek package";
$priv_list['page-services-zeek']['descr'] = "Allow access to zeek package GUI";

$priv_list['page-services-zeek']['match'] = array();
$priv_list['page-services-zeek']['match'][] = "pkg_edit.php?xml=zeek_script.xml*";

$priv_list['page-services-zeek']['match'][] = "pkg_edit.php?xml=zeek.xml*";
$priv_list['page-services-zeek']['match'][] = "pkg_edit.php?xml=zeek_zeekctl.xml*";
$priv_list['page-services-zeek']['match'][] = "pkg_edit.php?xml=zeek_cluster.xml*";
$priv_list['page-services-zeek']['match'][] = "pkg_edit.php?xml=zeek_script.xml*";
$priv_list['page-services-zeek']['match'][] = "pkg_edit.php?xml=zeek_log.xml*";
$priv_list['page-services-zeek']['match'][] = "pkg_edit.php?xml=zeek_sync.xml*";

$priv_list['page-services-zeek']['match'][] = "shortcuts/pkg_zeek.inc*";
$priv_list['page-services-zeek']['match'][] = "zeek_alerts.php*";
$priv_list['page-services-zeek']['match'][] = "zeek_alert_data.php*";
$priv_list['page-services-zeek']['match'][] = "select_box_file.php*";

?>
