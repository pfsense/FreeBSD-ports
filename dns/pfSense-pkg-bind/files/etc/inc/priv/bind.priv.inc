<?php
/*
 * bind.priv.inc
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

$priv_list['page-services-binddns'] = array();
$priv_list['page-services-binddns']['name'] = "WebCfg - Services: BIND package";
$priv_list['page-services-binddns']['descr'] = "Allow access to BIND package GUI";

$priv_list['page-services-binddns']['match'] = array();
$priv_list['page-services-binddns']['match'][] = "pkg.php?xml=bind.xml*";
$priv_list['page-services-binddns']['match'][] = "pkg.php?xml=bind_acls.xml*";
$priv_list['page-services-binddns']['match'][] = "pkg.php?xml=bind_sync.xml*";
$priv_list['page-services-binddns']['match'][] = "pkg.php?xml=bind_views.xml*";
$priv_list['page-services-binddns']['match'][] = "pkg.php?xml=bind_zones.xml*";

$priv_list['page-services-binddns']['match'][] = "pkg_edit.php?xml=bind.xml*";
$priv_list['page-services-binddns']['match'][] = "pkg_edit.php?xml=bind_acls.xml*";
$priv_list['page-services-binddns']['match'][] = "pkg_edit.php?xml=bind_sync.xml*";
$priv_list['page-services-binddns']['match'][] = "pkg_edit.php?xml=bind_views.xml*";
$priv_list['page-services-binddns']['match'][] = "pkg_edit.php?xml=bind_zones.xml*";

$priv_list['page-services-binddns']['match'][] = "widgets/widgets/bind.widget.php*";

?>
