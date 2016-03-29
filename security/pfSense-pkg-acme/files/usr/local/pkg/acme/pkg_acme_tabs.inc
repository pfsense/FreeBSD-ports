<?php

//require_once("guiconfig.inc"); DO NOT REQUIRE THIS!

// DO NOT REQUIRE guiconfig.inc HERE! though it contains the function display_top_tabs needed below.
// however if included it will hang filter rule generation, and might cause pf to not load any rules.
// This happens when /usr/local/pkg/*.inc files are dynamically loaded during package generation from filter.inc with function discover_pkg_rules(x).

namespace pfsense_pkg\acme;

global $acme_tab_array;

$acme_tab_array['acme'] = array();
$acme_tab_array['acme']['settings'] = Array(name => "General settings", url => "acme_generalsettings.php");
$acme_tab_array['acme']['certificates'] = Array(name => "Certificates", url => "acme_certificates.php");
$acme_tab_array['acme']['accountkeys'] = Array(name => "Account keys", url => "acme_accountkeys.php");

function display_top_tabs_active($top_tabs, $activetab) {
	$tab_array = array();
	foreach($top_tabs as $key => $tab_item){
		$tab_array[] = array($tab_item['name'], $key == $activetab, $tab_item['url']);
	}
	display_top_tabs($tab_array);
}

?>
