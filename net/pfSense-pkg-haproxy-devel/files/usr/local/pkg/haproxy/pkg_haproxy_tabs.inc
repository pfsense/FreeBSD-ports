<?php
/*
 * pkg_haproxy_tabs.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2016 PiBa-NL
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

//require_once("guiconfig.inc"); DO NOT REQUIRE THIS!

// DO NOT REQUIRE guiconfig.inc HERE! though it contains the function display_top_tabs needed below.
// however if included it will hang filter rule generation, and might cause pf to not load any rules.
// This happens when /usr/local/pkg/*.inc files are dynamically loaded during package generation from filter.inc with function discover_pkg_rules(x).

global $haproxy_tab_array;

$haproxy_tab_array['haproxy'] = array();
$haproxy_tab_array['haproxy']['settings'] = Array('name' => "Settings", 'url' => "/haproxy/haproxy_global.php");
$haproxy_tab_array['haproxy']['frontend'] = Array('name' => "Frontend", 'url' => "/haproxy/haproxy_listeners.php");
$haproxy_tab_array['haproxy']['backend'] = Array('name' => "Backend", 'url' => "/haproxy/haproxy_pools.php");
$haproxy_tab_array['haproxy']['files'] = Array('name' => "Files", 'url' => "/haproxy/haproxy_files.php");
$haproxy_tab_array['haproxy']['stats'] = Array('name' => "Stats", 'url' => "/haproxy/haproxy_stats.php");
$haproxy_tab_array['haproxy']['statsfs'] = Array('name' => "Stats FS", 'url' => "/haproxy/haproxy_stats.php?haproxystats=1");
$haproxy_tab_array['haproxy']['templates'] = Array('name' => "Templates", 'url' => "/haproxy/haproxy_templates.php");

function haproxy_display_top_tabs_active($top_tabs, $activetab) {
	$tab_array = array();
	foreach($top_tabs as $key => $tab_item){
		$tab_array[] = array($tab_item['name'], $key == $activetab, $tab_item['url']);
	}
	display_top_tabs($tab_array);
}
