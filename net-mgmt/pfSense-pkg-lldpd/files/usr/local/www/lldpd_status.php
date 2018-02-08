<?php
/*
 * lldpd_status.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2018 Denny Page
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


require_once("guiconfig.inc");
require_once("interfaces.inc");
require_once("/usr/local/pkg/lldpd/lldpd.inc");


$pgtitle = array(gettext("Services"), gettext("LLDP"), gettext("Status"));
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("LLDP Status"), true, "/lldpd_status.php");
$tab_array[] = array(gettext("LLDP Settings"), false, "/lldpd_settings.php");
display_top_tabs($tab_array);
?>

<div class="panel panel-default">
	<div class="panel-heading">
	<h2 class="panel-title"><?=gettext("LLDP Status")?></h2>
	</div>
	<div class="panel-body">
	<div class="container-fluid">
		<br>
		<pre>
<?php
if (is_service_running('lldpd')) {
	$pipe = popen(LLDPD_CLIENT . ' show chassis detail', "r");
	while ($line = fgets($pipe)) {
		echo htmlspecialchars($line);
	}
	pclose($pipe);
} else {
	echo "\n", gettext("The LLDP service is not running"), "\n";
}
?>
		</pre>
		<br>
		<pre>
<?php
if (is_service_running('lldpd')) {
	$pipe = popen(LLDPD_CLIENT . ' show neighbors detail', "r");
	while ($line = fgets($pipe)) {
		echo htmlspecialchars($line);
	}
	pclose($pipe);
} else {
	echo "\n", gettext("The LLDP service is not running"), "\n";
}
?>
		</pre>
		</div>
	</div>
</div>

<?php include("foot.inc");
