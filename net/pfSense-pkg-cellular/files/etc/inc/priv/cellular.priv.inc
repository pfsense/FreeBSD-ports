<?php
/*
 * cellular.priv.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2017 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2016 Voleatech GmbH, Fabian Schweinfurth
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

$priv_list['page-services-lteinterface'] = array();
$priv_list['page-services-lteinterface']['name'] = "WebCfg - Status: Cellular Data module package";
$priv_list['page-services-lteinterface']['descr'] = "Allow access to Cellular package GUI";

$priv_list['page-services-lteinterface']['match'] = array();
$priv_list['page-services-lteinterface']['match'][] = "pkg.php?xml=cellular.xml*";
$priv_list['page-services-lteinterface']['match'][] = "pkg_edit.php?xml=cellular.xml*";
$priv_list['page-services-lteinterface']['match'][] = "widgets/widgets/cellular.widget.php*";

?>
