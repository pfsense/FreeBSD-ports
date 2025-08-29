<?php
/*
 * andwatch.priv.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2025 Denny Page
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

$priv_list['page-services-andwatch'] = array();
$priv_list['page-services-andwatch']['name'] = "WebCfg - Services: ANDwatch";
$priv_list['page-services-andwatch']['descr'] = "Access the ANDwatch package configuration";
$priv_list['page-services-andwatch']['match'] = array();
$priv_list['page-services-andwatch']['match'][] = "andwatch.php";

$priv_list['page-status-andwatch'] = array();
$priv_list['page-status-andwatch']['name'] = "WebCfg - Status: ANDwatch Database";
$priv_list['page-status-andwatch']['descr'] = "Access the ANDwatch package database";
$priv_list['page-status-andwatch']['match'] = array();
$priv_list['page-status-andwatch']['match'][] = "status_andwatch.php*";

?>
