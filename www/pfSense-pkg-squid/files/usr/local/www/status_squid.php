<?php
/*
 * squid_monitor.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2012-2014 Marcello Coutinho
 * Copyright (C) 2012-2014 Carlos Cesario <carloscesario@gmail.com>
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

include("guiconfig.inc");

$pgtitle = array(gettext("Package"), gettext("Squid"), gettext("Status"));
$shortcut_section = "squid";
include("head.inc");

$tab_array = array();
if ($_REQUEST["menu"] == "reverse") {
	$tab_array[] = array(gettext("General"), false, "/pkg_edit.php?xml=squid_reverse_general.xml&amp;id=0");
	$tab_array[] = array(gettext("Web Servers"), false, "/pkg.php?xml=squid_reverse_peer.xml");
	$tab_array[] = array(gettext("Mappings"), false, "/pkg.php?xml=squid_reverse_uri.xml");
	$tab_array[] = array(gettext("Redirects"), false, "/pkg.php?xml=squid_reverse_redir.xml");
	$tab_array[] = array(gettext("Real Time"), true, "/squid_monitor.php?menu=reverse");
	$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=squid_reverse_sync.xml");
} else {
	$tab_array[] = array(gettext("General"), false, "/pkg_edit.php?xml=squid.xml&amp;id=0");
	$tab_array[] = array(gettext("Remote Cache"), false, "/pkg.php?xml=squid_upstream.xml");
	$tab_array[] = array(gettext("Local Cache"), false, "/pkg_edit.php?xml=squid_cache.xml&amp;id=0");
	$tab_array[] = array(gettext("Antivirus"), false, "/pkg_edit.php?xml=squid_antivirus.xml&amp;id=0");
	$tab_array[] = array(gettext("ACLs"), false, "/pkg_edit.php?xml=squid_nac.xml&amp;id=0");
	$tab_array[] = array(gettext("Traffic Mgmt"), false, "/pkg_edit.php?xml=squid_traffic.xml&amp;id=0");
	$tab_array[] = array(gettext("Authentication"), false, "/pkg_edit.php?xml=squid_auth.xml&amp;id=0");
	$tab_array[] = array(gettext("Users"), false, "/pkg.php?xml=squid_users.xml");
	$tab_array[] = array(gettext("Real Time"), false, "/squid_monitor.php");
	$tab_array[] = array(gettext("Status"), true, "/status_squid.php");
	$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=squid_sync.xml");
}
display_top_tabs($tab_array);

function squid_status() {
	if (is_service_running('squid')) {
		init_config_arr(array('installedpackages', 'squidcache','config'));
		$proxy_ifaces = explode(",", config_get_path('installedpackages/squid/config/0/active_interface', ''));
		foreach ($proxy_ifaces as $iface) {
			if (get_interface_ip($iface)) {
				$ip = get_interface_ip($iface);
				$lip = '127.0.0.1';
			} else {
				$ip = get_interface_ipv6($iface);
				$lip = '::1';
			}
			exec("/usr/local/sbin/squidclient -l " . escapeshellarg($lip) .
				" -h " . escapeshellarg($ip) . " mgr:info", $result);
		}
	} else {
		return(gettext('Squid Proxy is not running.'));
	}
	$i = 0;
	$matchbegin = "Squid Object Cache";
	foreach ($result as $line) {
		if (preg_match("/{$matchbegin}/", $line)) {
			$begin = $i;
		}
		$i++;
	}
	
	$output = "";
	$i = 0;
	
	foreach ($result as $line) {
		if ($i >= $begin) {
			$output .= $line . "\n";
		}
		$i++;
	}
	return $output;
}	

?>

<div class="panel panel-default">
        <div class="panel-heading"><h2 class="panel-title">Connection list</h2></div>
        <div class="panel-body table-responsive">
        <table class="table table-striped table-hover table-condensed">
        <tbody>
		<?php 
		print "<pre>";
		print htmlentities(squid_status());
		print "</pre>";
		?>
        </tbody>
        </table>
        </div>
</div>

<?php include("foot.inc"); ?>
