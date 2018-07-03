<?php
/*
 * autoconfigbackup_stats.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008-2015 Rubicon Communications, LLC (Netgate)
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
require("globals.inc");
require("guiconfig.inc");
require("autoconfigbackup.inc");

// Seperator used during client / server communications
$oper_sep = "\|\|";
$exp_sep = "||";

// Encryption password
$decrypt_password = $config['installedpackages']['autoconfigbackup2']['config'][0]['crypto_password'];

// Defined username. Username must be sent lowercase. See Redmine #7127 and Netgate Redmine #163
$username = strtolower($config['installedpackages']['autoconfigbackup2']['config'][0]['username']);

// Defined password
$password = $config['installedpackages']['autoconfigbackup2']['config'][0]['password'];

// URL to restore.php
$get_url = "https://portal.pfsense.org/pfSconfigbackups/restore.php";

// URL to delete.php
$del_url = "https://portal.pfsense.org/pfSconfigbackups/delete.php";

// URL to stats.php
$stats_url = "https://portal.pfsense.org/pfSconfigbackups/showstats.php";

// Set hostname
$hostname = $config['system']['hostname'] . "." . $config['system']['domain'];

if (!$username) {
	Header("Location: /pkg_edit.php?xml=autoconfigbackup.xml&id=0&savemsg=Please+setup+Auto+Config+Backup");
	exit;
}

if ($_REQUEST['delhostname']) {
	$curl_session = curl_init();
	curl_setopt($curl_session, CURLOPT_URL, $del_url);
	curl_setopt($curl_session, CURLOPT_HTTPHEADER, array("Authorization: Basic " . base64_encode("{$acbuser}:{$acbpwd}")));
	curl_setopt($curl_session, CURLOPT_POST, 2);
	curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, 1);
	curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl_session, CURLOPT_POSTFIELDS, "action=deletehostname&delhostname=" . urlencode($_REQUEST['delhostname']));
	curl_setopt($curl_session, CURLOPT_USERAGENT, $g['product_name'] . '/' . rtrim(file_get_contents("/etc/version")));
	// Proxy
	curl_setopt_array($curl_session, configure_proxy());

	$data = curl_exec($curl_session);
	if (curl_errno($curl_session)) {
		$fd = fopen("/tmp/acb_deletedebug.txt", "w");
		fwrite($fd, $get_url . "" . "action=deletehostname&hostname=" . urlencode($_REQUEST['delhostname']) . "\n\n");
		fwrite($fd, $data);
		fwrite($fd, curl_error($curl_session));
		fclose($fd);
		$savemsg = "An error occurred while trying to remove the item from portal.pfsense.org.";
	} else {
		curl_close($curl_session);
		$savemsg = "ALL backup revisions for {$_REQUEST['delhostname']} have been removed.";
	}
}

$pgtitle = array("Diagnostics", "Auto Configuration Backup", "Stats");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$tab_array = array();
$tab_array[] = array("Settings", false, "/pkg_edit.php?xml=autoconfigbackup.xml&amp;id=0");
$tab_array[] = array("Restore", false, "/autoconfigbackup.php");
$tab_array[] = array("Backup now", false, "/autoconfigbackup_backup.php");
$tab_array[] = array("Stats", true, "/autoconfigbackup_stats.php");
display_top_tabs($tab_array);

?>
<div id="loading">
	<i class="fa fa-spinner fa-spin"></i> Loading, please wait...
</div>
<?php
// Populate available backups
$curl_session = curl_init();
curl_setopt($curl_session, CURLOPT_URL, $stats_url);
curl_setopt($curl_session, CURLOPT_HTTPHEADER, array("Authorization: Basic " . base64_encode("{$acbuser}:{$acbpwd}")));
curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, 1);
curl_setopt($curl_session, CURLOPT_POST, 1);
curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($curl_session, CURLOPT_POSTFIELDS, "userkey=" . $userkey);
curl_setopt($curl_session, CURLOPT_USERAGENT, $g['product_name'] . '/' . rtrim(file_get_contents("/etc/version")));
// Proxy
curl_setopt_array($curl_session, configure_proxy());

$data = curl_exec($curl_session);
if (curl_errno($curl_session)) {
	$fd = fopen("/tmp/acb_statsdebug.txt", "w");
	fwrite($fd, $get_url . "" . "action=showstats" . "\n\n");
	fwrite($fd, $data);
	fwrite($fd, curl_error($curl_session));
	fclose($fd);
} else {
	curl_close($curl_session);
}

$statvers = array();
$ds_split = explode($exp_sep, $data);

$tmp_array = array();
$tmp_array['hostname'] = $ds_split[1];
$tmp_array['hostnamecount'] = $ds_split[2];
if ($ds_split[1] && $ds_split[2]) {
	$statvers[] = $tmp_array;
}


$counter = 0;
$total_backups = 0;
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Automatic Configuration Backups")?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed" id="backup_stats">
				<thead>
					<tr>
		<!--				<th><?=gettext("Hostname")?></th> -->
						<th><?=gettext("Backup Count")?></th>
						<th><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody>
			<?php	foreach ($statvers as $cv): ?>
					<tr>
					<!--
						<td>
							<?= $cv['hostname']; ?>
						</td>
					-->
						<td>
							<?= $cv['hostnamecount']; ?>
						</td>
						<td>
							<a class="fa fa-search"	title="<?=gettext('View all backups for this host')?>"		href="autoconfigbackup.php?hostname=<?=urlencode($cv['hostname'])?>"></a>
							<a class="fa fa-trash"	title="<?=gettext('Delete all backups for this host')?>"	href="autoconfigbackup_stats.php?delhostname=<?=urlencode($cv['hostname'])?>"></a>
						</td>
					</tr>
			<?php
				$total_backups = $total_backups + $cv['hostnamecount'];
				$counter++;
				endforeach;

				if ($counter == 0): ?>
					<tr>
						<td colspan="3" align="center">
							<?=gettext("Sorry, status information could be located on portal.pfsense.org for this account.")?> (<?=htmlspecialchars($username)?>)
						</td>
					</tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function(){
	$('#loading').hide();
});
//]]>
</script>
<?php include("foot.inc"); ?>
