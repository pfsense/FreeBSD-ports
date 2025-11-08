<?php
/*
 * pkg_haproxy.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013 Marcello Coutinho <marcellocoutinho@gmail.com>
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

global $shortcuts;

$shortcuts['haproxy'] = array();
$shortcuts['haproxy']['main'] = "/haproxy/haproxy_global.php";
$shortcuts['haproxy']['log'] = "/status_logs_packages.php?pkg=haproxy-devel";
$shortcuts['haproxy']['status'] = "/haproxy/haproxy_stats.php?haproxystats=1";
$shortcuts['haproxy']['service'] = "haproxy";
