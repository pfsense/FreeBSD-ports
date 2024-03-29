<?php
/*
 * freeradius.priv.inc
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

$priv_list['page-services-freeradius'] = array();
$priv_list['page-services-freeradius']['name'] = "WebCfg - Services: FreeRADIUS";
$priv_list['page-services-freeradius']['descr'] = "Allow access to FreeRADIUS package GUI";

$priv_list['page-services-freeradius']['match'] = array();
$priv_list['page-services-freeradius']['match'][] = "pkg.php?xml=freeradius.xml*";
$priv_list['page-services-freeradius']['match'][] = "pkg.php?xml=freeradiusauthorizedmacs.xml*";
$priv_list['page-services-freeradius']['match'][] = "pkg.php?xml=freeradiusclients.xml*";
$priv_list['page-services-freeradius']['match'][] = "pkg.php?xml=freeradiusinterfaces.xml*";
$priv_list['page-services-freeradius']['match'][] = "pkg.php?xml=freeradiussettings.xml*";
$priv_list['page-services-freeradius']['match'][] = "pkg.php?xml=freeradiuseapconf.xml*";
$priv_list['page-services-freeradius']['match'][] = "pkg.php?xml=freeradiussqlconf.xml*";
$priv_list['page-services-freeradius']['match'][] = "pkg.php?xml=freeradiusmodulesldap.xml*";
$priv_list['page-services-freeradius']['match'][] = "pkg.php?xml=freeradiussync.xml*";

$priv_list['page-services-freeradius']['match'][] = "pkg_edit.php?xml=freeradius.xml*";
$priv_list['page-services-freeradius']['match'][] = "pkg_edit.php?xml=freeradiusauthorizedmacs.xml*";
$priv_list['page-services-freeradius']['match'][] = "pkg_edit.php?xml=freeradiusclients.xml*";
$priv_list['page-services-freeradius']['match'][] = "pkg_edit.php?xml=freeradiusinterfaces.xml*";
$priv_list['page-services-freeradius']['match'][] = "pkg_edit.php?xml=freeradiussettings.xml*";
$priv_list['page-services-freeradius']['match'][] = "pkg_edit.php?xml=freeradiuseapconf.xml*";
$priv_list['page-services-freeradius']['match'][] = "pkg_edit.php?xml=freeradiussqlconf.xml*";
$priv_list['page-services-freeradius']['match'][] = "pkg_edit.php?xml=freeradiusmodulesldap.xml*";
$priv_list['page-services-freeradius']['match'][] = "pkg_edit.php?xml=freeradiussync.xml*";

$priv_list['page-services-freeradius']['match'][] = "freeradius_view_config.php*";

?>
