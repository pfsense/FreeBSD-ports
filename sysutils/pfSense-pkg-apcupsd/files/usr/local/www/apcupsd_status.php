<?php
/*
 * apcupsd_status.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013-2016 Danilo G. Baio <dbaio@bsd.com.br>
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

require("guiconfig.inc");
require_once("/usr/local/pkg/apcupsd.inc");

$shortcut_section = 'apcupsd';

$pgtitle = array(gettext('Status'), gettext('Apcupsd'));
include("head.inc");

function puts($arg) {
	echo "$arg\n";
}

$tab_array = array();
$tab_array[] = array(gettext("General"), false, "/pkg_edit.php?xml=apcupsd.xml&amp;id=0");
$tab_array[] = array(gettext("Status"), true, "/apcupsd_status.php");
display_top_tabs($tab_array);

$nis_server = check_nis_running_apcupsd();

if ( $_POST['strapcaccess'] ) {
	if (is_hostname($_POST['strapcaccess'])) {
		puts("<div class=\"panel panel-success responsive\"><div class=\"panel-heading\"><h2 class=\"panel-title\">Status information from apcupsd</h2></div>");
		puts("<pre>");
		puts("Running: apcaccess -h " . htmlspecialchars($_POST['strapcaccess']) . " <br />");
		putenv("PATH=/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin");
		$ph = popen("apcaccess -h " . escapeshellarg($_POST['strapcaccess']) . " 2>&1", "r" );
		while ($line = fgets($ph)) {
			echo htmlspecialchars($line);
		}
		pclose($ph);
		puts("</pre>");
		puts("</div>");
	} else {
		print_input_errors(array(gettext("Invalid hostname or IP address")));
	}
} elseif ($nis_server) {
	$nisip = (check_nis_ip_apcupsd() != ''? check_nis_ip_apcupsd() : "localhost");
	$nisport = (check_nis_port_apcupsd() != '' ? check_nis_port_apcupsd() : "3551");

	puts("<div class=\"panel panel-success responsive\"><div class=\"panel-heading\"><h2 class=\"panel-title\">Status information from apcupsd</h2></div>");
	puts("<pre>");
	puts("Running: apcaccess -h {$nisip}:{$nisport} <br />");
	putenv("PATH=/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin");
	$ph = popen("apcaccess -h {$nisip}:{$nisport} 2>&1", "r" );
	while ($line = fgets($ph)) {
		echo htmlspecialchars($line);
	}
	pclose($ph);
	puts("</pre>");
	puts("</div>");
} else {
	puts("<div class=\"panel panel-success responsive\"><div class=\"panel-heading\"><h2 class=\"panel-title\">Status information from apcupsd</h2></div>");
	puts("<pre>");
	puts("Network Information Server (NIS) not running, in order to run apcaccess on localhost, you need to enable it on APCupsd General settings. <br />");
	puts("</pre>");
	puts("</div>");
}

?>

<form action="apcupsd_status.php" method="post" enctype="multipart/form-data" name="frm_apcupsd_status">
	<div class="panel panel-default responsive">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('APC UPS Daemon Status Information')?></h2></div>
		<div class="panel-body">
			<div class="form-group">
				<label class="col-sm-2 control-label">
					Host
				</label>
				<div class="col-sm-10">
						<input class="form-control" name="strapcaccess" id="strapcaccess" type="text" 
							value="<?=htmlspecialchars($_POST['strapcaccess'])?>">
					<span class="help-block">
						Default: <strong>localhost</strong><br /><br />
						Note: apcaccess uses apcupsd's inbuilt Network Information Server (NIS) to obtain the current status information<br />
						from the UPS on the local or remote computer. It is therefore necessary to have the following configuration directives: <br />
						NETSERVER <strong>on</strong> <br />
						NISPORT <strong>3551</strong> <br />
						<br />
					</span>
				</div>
			</div>
		</div>
	</div>
	<div class="col-sm-10 col-sm-offset-2">
		<button name="submit" type="submit" class="btn btn-warning btn-sm" 
			value="EXECAPCACCESS" title="<?=gettext("Retrieve status information from apcupsd")?>">
			<i class="fa-solid fa-bolt"></i>
			<?=gettext("Execute")?>
		</button>
	</div>
</form>

<?php
include("foot.inc");
?>

