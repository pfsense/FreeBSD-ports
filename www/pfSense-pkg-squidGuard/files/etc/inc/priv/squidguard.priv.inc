<?php
/*
 * squidguard.priv.inc
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

$priv_list['page-services-squidguard'] = array();
$priv_list['page-services-squidguard']['name'] = "WebCfg - Services: SquidGuard package";
$priv_list['page-services-squidguard']['descr'] = "Allow access to SquidGuard package GUI";
$priv_list['page-services-squidguard']['match'] = array();

$priv_list['page-services-squidguard']['match'][] = "pkg.php?xml=squidguard.xml*";
$priv_list['page-services-squidguard']['match'][] = "pkg.php?xml=squidguard_acl.xml*";
$priv_list['page-services-squidguard']['match'][] = "pkg.php?xml=squidguard_default.xml*";
$priv_list['page-services-squidguard']['match'][] = "pkg.php?xml=squidguard_dest.xml*";
$priv_list['page-services-squidguard']['match'][] = "pkg.php?xml=squidguard_sync.xml*";
$priv_list['page-services-squidguard']['match'][] = "pkg.php?xml=squidguard_time.xml*";
$priv_list['page-services-squidguard']['match'][] = "pkg.php?xml=squidguard_rewr.xml*";

$priv_list['page-services-squidguard']['match'][] = "pkg_edit.php?xml=squidguard.xml*";
$priv_list['page-services-squidguard']['match'][] = "pkg_edit.php?xml=squidguard_acl.xml*";
$priv_list['page-services-squidguard']['match'][] = "pkg_edit.php?xml=squidguard_default.xml*";
$priv_list['page-services-squidguard']['match'][] = "pkg_edit.php?xml=squidguard_dest.xml*";
$priv_list['page-services-squidguard']['match'][] = "pkg_edit.php?xml=squidguard_sync.xml*";
$priv_list['page-services-squidguard']['match'][] = "pkg_edit.php?xml=squidguard_time.xml*";
$priv_list['page-services-squidguard']['match'][] = "pkg_edit.php?xml=squidguard_rewr.xml*";

$priv_list['page-services-squidguard']['match'][] = "squidguard_blacklist.php*";
$priv_list['page-services-squidguard']['match'][] = "squidguard_log.php*";

?>
