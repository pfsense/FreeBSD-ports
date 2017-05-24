<?php
/*
 * bandwidthd.priv.inc
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

$priv_list['page-diagnostics-bandwidthd-settings'] = array();
$priv_list['page-diagnostics-bandwidthd-settings']['name'] = "WebCfg - Diagnostics: BandwidthD settings";
$priv_list['page-diagnostics-bandwidthd-settings']['descr'] = "Allow access to BandwidthD package settings GUI";
$priv_list['page-diagnostics-bandwidthd-settings']['match'] = array();
$priv_list['page-diagnostics-bandwidthd-settings']['match'][] = "pkg.php?xml=bandwidthd.xml*";
$priv_list['page-diagnostics-bandwidthd-settings']['match'][] = "pkg_edit.php?xml=bandwidthd.xml*";

$priv_list['page-diagnostics-bandwidthd-access'] = array();
$priv_list['page-diagnostics-bandwidthd-access']['name'] = "WebCfg - Diagnostics: Access BandwidthD";
$priv_list['page-diagnostics-bandwidthd-access']['descr'] = "Allow access to BandwidthD statistics";
$priv_list['page-diagnostics-bandwidthd-access']['match'] = array();
$priv_list['page-diagnostics-bandwidthd-access']['match'][] = "diag_bandwidthd.php*";

?>
