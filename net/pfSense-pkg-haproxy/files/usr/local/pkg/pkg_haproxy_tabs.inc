<?php

//require_once("guiconfig.inc"); DO NOT REQUIRE THIS!

// DO NOT REQUIRE guiconfig.inc HERE! though it contains the function display_top_tabs needed below.
// however if included it will hang filter rule generation, and might cause pf to not load any rules.
// This happens when /usr/local/pkg/*.inc files are dynamically loaded during package generation from filter.inc with function discover_pkg_rules(x).

global $haproxy_tab_array;

$haproxy_tab_array['haproxy'] = array();
$haproxy_tab_array['haproxy']['settings'] = Array(name => "Settings", url => "haproxy_global.php");
$haproxy_tab_array['haproxy']['frontend'] = Array(name => "Frontend", url => "haproxy_listeners.php");
$haproxy_tab_array['haproxy']['backend'] = Array(name => "Backend", url => "haproxy_pools.php");
$haproxy_tab_array['haproxy']['files'] = Array(name => "Files", url => "haproxy_files.php");
$haproxy_tab_array['haproxy']['stats'] = Array(name => "Stats", url => "haproxy_stats.php");
$haproxy_tab_array['haproxy']['statsfs'] = Array(name => "Stats FS", url => "haproxy_stats.php?haproxystats=1");
$haproxy_tab_array['haproxy']['templates'] = Array(name => "Templates", url => "haproxy_templates.php");

function haproxy_display_top_tabs_active($top_tabs, $activetab) {
	$tab_array = array();
	foreach($top_tabs as $key => $tab_item){
		$tab_array[] = array($tab_item['name'], $key == $activetab, $tab_item['url']);
	}
	display_top_tabs($tab_array);
}

?>
