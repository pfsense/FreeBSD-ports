<?php
/*
 * haproxy.priv.inc
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

$priv_list['page-service-haproxy'] = array();
$priv_list['page-service-haproxy']['name'] = "WebCfg - Services: HAProxy package";
$priv_list['page-service-haproxy']['descr'] = "Allow access to HAProxy package GUI";
$priv_list['page-service-haproxy']['match'] = array();

$priv_list['page-service-haproxy']['match'][] = "haproxy/haproxy_files.php*";
$priv_list['page-service-haproxy']['match'][] = "haproxy/haproxy_global.php*";
$priv_list['page-service-haproxy']['match'][] = "haproxy/haproxy_listeners.php*";
$priv_list['page-service-haproxy']['match'][] = "haproxy/haproxy_listeners_edit.php*";
$priv_list['page-service-haproxy']['match'][] = "haproxy/haproxy_pool_edit.php*";
$priv_list['page-service-haproxy']['match'][] = "haproxy/haproxy_pools.php*";
$priv_list['page-service-haproxy']['match'][] = "haproxy/haproxy_templates.php*";

$priv_list['page-service-haproxy-stats'] = array();
$priv_list['page-service-haproxy-stats']['name'] = "WebCfg - Services: HAProxy package stats";
$priv_list['page-service-haproxy-stats']['descr'] = "Allow access to HAProxy package GUI stats";
$priv_list['page-service-haproxy-stats']['match'] = array();
$priv_list['page-service-haproxy-stats']['match'][] = "haproxy/haproxy_stats.php*";

?>
