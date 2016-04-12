<?php
/*
	autoconfigbackup.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2008 Scott Ullrich
	Copyright (C) 2008-2015 Electric Sheep Fencing LP
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
require("guiconfig.inc");
require("autoconfigbackup.inc");

// Separator used during client / server communications
$oper_sep = "\|\|";

// Encryption password
$decrypt_password = $config['installedpackages']['autoconfigbackup']['config'][0]['crypto_password'];

// Defined username
$username = $config['installedpackages']['autoconfigbackup']['config'][0]['username'];

// Defined password
$password = $config['installedpackages']['autoconfigbackup']['config'][0]['password'];

// URL to restore.php
$get_url = "https://portal.pfsense.org/pfSconfigbackups/restore.php";

// URL to stats
$stats_url = "https://portal.pfsense.org/pfSconfigbackups/showstats.php";

// URL to delete.php
$del_url = "https://portal.pfsense.org/pfSconfigbackups/delete.php";

// Set hostname
if ($_REQUEST['hostname']) {
	$hostname = $_REQUEST['hostname'];
} else {
	$hostname = $config['system']['hostname'] . "." . $config['system']['domain'];
}

// Hostname of local machine
$myhostname = $config['system']['hostname'] . "." . $config['system']['domain'];

if (!$username) {
	Header("Location: /pkg_edit.php?xml=autoconfigbackup.xml&id=0&savemsg=Please+setup+Auto+Config+Backup");
	exit;
}

if ($_REQUEST['savemsg']) {
	$savemsg = htmlentities($_REQUEST['savemsg']);
}

if ($_REQUEST['download']) {
	$pgtitle = array("Diagnostics", "Auto Configuration Backup", "Revision Information");
} else {
	$pgtitle = array("Diagnostics", "Auto Configuration Backup", "Restore");
}

/* Set up time zones for conversion. See #5250 */
$acbtz = new DateTimeZone('America/Chicago');
$mytz = new DateTimeZone(date_default_timezone_get());

include("head.inc");

function get_hostnames() {
	global $stats_url, $username, $password, $oper_sep, $config, $g;
	// Populate available backups
	$curl_session = curl_init();
	curl_setopt($curl_session, CURLOPT_URL, $stats_url);
	curl_setopt($curl_session, CURLOPT_HTTPHEADER, array("Authorization: Basic " . base64_encode("{$username}:{$password}")));
	curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, 1);
	curl_setopt($curl_session, CURLOPT_POST, 1);
	curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl_session, CURLOPT_POSTFIELDS, "action=showstats");
	curl_setopt($curl_session, CURLOPT_USERAGENT, $g['product_name'] . '/' . rtrim(file_get_contents("/etc/version")));
	// Proxy
	curl_setopt_array($curl_session, configure_proxy());

	$data = curl_exec($curl_session);
	if (curl_errno($curl_session)) {
		$fd = fopen("/tmp/acb_statsdebug.txt", "w");
		fwrite($fd, $stats_url . "" . "action=showstats" . "\n\n");
		fwrite($fd, $data);
		fwrite($fd, curl_error($curl_session));
		fclose($fd);
	} else {
		curl_close($curl_session);
	}
	// Loop through and create new confvers
	$data_split = explode("\n", $data);
	$statvers = array();
	foreach ($data_split as $ds) {
		$ds_split = split($oper_sep, $ds);
		if ($ds_split[0]) {
			$statvers[] = $ds_split[0];
		}
	}
	return $statvers;
}

if ($_REQUEST['rmver'] != "") {
	$curl_session = curl_init();
	curl_setopt($curl_session, CURLOPT_URL, $del_url);
	curl_setopt($curl_session, CURLOPT_HTTPHEADER, array("Authorization: Basic " . base64_encode("{$username}:{$password}")));
	curl_setopt($curl_session, CURLOPT_POST, 3);
	curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, 1);
	curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl_session, CURLOPT_POSTFIELDS, "action=delete" . "&hostname=" . urlencode($hostname) . "&revision=" . urlencode($_REQUEST['rmver']));
	curl_setopt($curl_session, CURLOPT_USERAGENT, $g['product_name'] . '/' . rtrim(file_get_contents("/etc/version")));
	// Proxy
	curl_setopt_array($curl_session, configure_proxy());

	$data = curl_exec($curl_session);
	if (curl_errno($curl_session)) {
		$fd = fopen("/tmp/acb_deletedebug.txt", "w");
		fwrite($fd, $get_url . "" . "action=delete&hostname=" . urlencode($hostname) . "&revision=" . urlencode($_REQUEST['rmver']) . "\n\n");
		fwrite($fd, $data);
		fwrite($fd, curl_error($curl_session));
		fclose($fd);
		$savemsg = "An error occurred while trying to remove the item from portal.pfsense.org.";
	} else {
		curl_close($curl_session);
		$budate = new DateTime($_REQUEST['rmver'], $acbtz);
		$budate->setTimezone($mytz);
		$savemsg = "Backup revision " . htmlspecialchars($budate->format(DATE_RFC2822)) . " has been removed.";
	}
}
if ($_REQUEST['newver'] != "") {
	// Phone home and obtain backups
	$curl_session = curl_init();
	curl_setopt($curl_session, CURLOPT_URL, $get_url);
	curl_setopt($curl_session, CURLOPT_HTTPHEADER, array("Authorization: Basic " . base64_encode("{$username}:{$password}")));
	curl_setopt($curl_session, CURLOPT_POST, 3);
	curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, 1);
	curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl_session, CURLOPT_POSTFIELDS, "action=restore" . "&hostname=" . urlencode($hostname) . "&revision=" . urlencode($_REQUEST['newver']));
	curl_setopt($curl_session, CURLOPT_USERAGENT, $g['product_name'] . '/' . rtrim(file_get_contents("/etc/version")));
	// Proxy
	curl_setopt_array($curl_session, configure_proxy());
	$data = curl_exec($curl_session);
	$data_split = split("\+\+\+\+", $data);
	$sha256 = trim($data_split[0]);
	$data = $data_split[1];
	if (!tagfile_deformat($data, $data, "config.xml")) {
		$input_errors[] = "The downloaded file does not appear to contain an encrypted pfSense configuration.";
	}
	$out = decrypt_data($data, $decrypt_password);

	$pos = stripos($out, "</pfsense>");
	$data = substr($out, 0, $pos);
	$data = $data . "</pfsense>\n";

	$fd = fopen("/tmp/config_restore.xml", "w");
	fwrite($fd, $data);
	fclose($fd);
	if (strlen($data) < 50) {
		$input_errors[] = "The decrypted config.xml is under 50 characters, something went wrong. Aborting.";
	}
	$ondisksha256 = trim(shell_exec("/sbin/sha256 /tmp/config_restore.xml | /usr/bin/awk '{ print $4 }'"));
	// We might not have a sha256 on file for older backups
	if ($sha256 != "0" && $sha256 != "") {
		if ($ondisksha256 != $sha256) {
			$input_errors[] = "SHA256 values do not match, cannot restore. $ondisksha256 != $sha256";
		}
	}
	if (curl_errno($curl_session)) {
		/* If an error occured, log the error in /tmp/ */
		$fd = fopen("/tmp/acb_restoredebug.txt", "w");
		fwrite($fd, $get_url . "" . "action=restore&hostname={$hostname}&revision=" . urlencode($_REQUEST['newver']) . "\n\n");
		fwrite($fd, $data);
		fwrite($fd, curl_error($curl_session));
		fclose($fd);
	} else {
		curl_close($curl_session);
	}
	if (!$input_errors && $data) {
		conf_mount_rw();
		if (config_restore("/tmp/config_restore.xml") == 0) {
			$savemsg = "Successfully reverted the pfSense configuration to revision " . urldecode($_REQUEST['newver']) . ".";
			$savemsg .= <<<EOF
			<br />
		<form action="diag_reboot.php" method="post">
			Reboot the firewall to full activate changes?
			<input name="override" type="hidden" value="yes" />
			<input name="Submit" type="submit" class="formbtn" value=" Yes " />
		</form>
EOF;
		} else {
			$savemsg = "Unable to revert to the selected configuration.";
		}
	} else {
		log_error("There was an error when restoring the AutoConfigBackup item");
	}
	unlink_if_exists("/tmp/config_restore.xml");
	conf_mount_ro();
}
if ($_REQUEST['download']) {
	// Phone home and obtain backups
	$curl_session = curl_init();
	curl_setopt($curl_session, CURLOPT_URL, $get_url);
	curl_setopt($curl_session, CURLOPT_HTTPHEADER, array("Authorization: Basic " . base64_encode("{$username}:{$password}")));
	curl_setopt($curl_session, CURLOPT_POST, 3);
	curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, 1);
	curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl_session, CURLOPT_POSTFIELDS, "action=restore" . "&hostname=" . urlencode($hostname) . "&revision=" . urlencode($_REQUEST['download']));
	curl_setopt($curl_session, CURLOPT_USERAGENT, $g['product_name'] . '/' . rtrim(file_get_contents("/etc/version")));
	// Proxy
	curl_setopt_array($curl_session, configure_proxy());
	$data = curl_exec($curl_session);
	if (!tagfile_deformat($data, $data1, "config.xml")) {
		$input_errors[] = "The downloaded file does not appear to contain an encrypted pfSense configuration.";
	} else {
		$ds = split("\+\+\+\+", $data);
		$revision = $_REQUEST['download'];
		$sha256sum = $ds[0];
		if ($sha256sum == "0") {
			$sha256sum = "None on file.";
		}
		$data = $ds[1];
		$configtype = "Encrypted";
		if (!tagfile_deformat($data, $data, "config.xml")) {
			$input_errors[] = "The downloaded file does not appear to contain an encrypted pfSense configuration.";
		}
		$data = decrypt_data($data, $decrypt_password);
		if (!strstr($data, "pfsense")) {
			$data = "Could not decrypt. Different encryption key?";
			$input_errors[] = "Could not decrypt config.xml";
		}
	}
} else {
	// Populate available backups
	$curl_session = curl_init();
	curl_setopt($curl_session, CURLOPT_URL, $get_url);
	curl_setopt($curl_session, CURLOPT_HTTPHEADER, array("Authorization: Basic " . base64_encode("{$username}:{$password}")));
	curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, 1);
	curl_setopt($curl_session, CURLOPT_POST, 1);
	curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl_session, CURLOPT_POSTFIELDS, "action=showbackups&hostname={$hostname}");
	curl_setopt($curl_session, CURLOPT_USERAGENT, $g['product_name'] . '/' . rtrim(file_get_contents("/etc/version")));
	// Proxy
	curl_setopt_array($curl_session, configure_proxy());

	$data = curl_exec($curl_session);
	if (curl_errno($curl_session)) {
		$fd = fopen("/tmp/acb_backupdebug.txt", "w");
		fwrite($fd, $get_url . "" . "action=showbackups" . "\n\n");
		fwrite($fd, $data);
		fwrite($fd, curl_error($curl_session));
		fclose($fd);
	} else {
		curl_close($curl_session);
	}
	// Loop through and create new confvers
	$data_split = split("\n", $data);
	$confvers = array();

	foreach ($data_split as $ds) {
		$ds_split = split($oper_sep, $ds);
		$tmp_array = array();
		$tmp_array['username'] = $ds_split[0];
		$tmp_array['reason'] = $ds_split[1];
		$tmp_array['time'] = $ds_split[2];

		/* Convert the time from server time to local. See #5250 */
		$budate = new DateTime($tmp_array['time'], $acbtz);
		$budate->setTimezone($mytz);
		$tmp_array['localtime'] = $budate->format(DATE_RFC2822);

		if ($ds_split[2] && $ds_split[0]) {
			$confvers[] = $tmp_array;
		}
	}
}

if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if ($hostname != $myhostname) {
	print_info_box("Warning: Currently viewing the backup history of an alternate host (" . htmlspecialchars($hostname) . ")", 'warning');
}

$tab_array = array();
$tab_array[0] = array("Settings", false, "/pkg_edit.php?xml=autoconfigbackup.xml&amp;id=0");
if ($_REQUEST['download']) {
	$active = false;
} else {
	$active = true;
}
$tab_array[1] = array("Restore", $active, "/autoconfigbackup.php");
if ($_REQUEST['download']) {
	$tab_array[] = array("Revision", true, "/autoconfigbackup.php?download={$_REQUEST['download']}");
}
$tab_array[] = array("Backup now", false, "/autoconfigbackup_backup.php");
$tab_array[] = array("Stats", false, "/autoconfigbackup_stats.php");
display_top_tabs($tab_array);

$hostnames = get_hostnames();
?>

<div id="loading">
	<i class="fa fa-spinner fa-spin"></i> Loading, please wait...
</div>


<?php if ($_REQUEST['download'] && (!$input_errors)):

$form = new Form(false);

$section = new Form_Section('Backup Details');

$section->addInput(new Form_Input(
	'hostname',
	'Hostname',
	'text',
	$hostname
))->setWidth(7)->setReadOnly();

$section->addInput(new Form_Input(
	'download',
	'Revision date/time',
	'text',
	$_REQUEST['download']
))->setWidth(7)->setReadOnly();

$section->addInput(new Form_Input(
	'reason',
	'Revision Reason',
	'text',
	$_REQUEST['reason']
))->setWidth(7)->setReadOnly();

$section->addInput(new Form_Input(
	'shasum',
	'SHA256 summary',
	'text',
	$sha256sum
))->setWidth(7)->setReadOnly();

$section->addInput(new Form_Textarea(
	'config_xml',
	'Encrypted config.xml',
	$ds[1]
))->setWidth(7)->setAttribute("rows", "40")->setAttribute("wrap", "off");

$section->addInput(new Form_Textarea(
	'dec_config_xml',
	'Decrypted config.xml',
	$data
))->setWidth(7)->setAttribute("rows", "40")->setAttribute("wrap", "off");

$form->add($section);

print($form);


?>
<a class="btn btn-primary" title="<?=gettext('Restore this revision')?>" href="autoconfigbackup.php?newver=<?= urlencode($_REQUEST['download']) ?>" onclick="return confirm('<?=gettext("Are you sure you want to restore {$cv['localtime']}?")?>')"><i class="fa fa-undo"></i> Install this revision</a>

<?php else: ?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Automatic Configuration Backups")?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
		<strong>Hostname:</strong>
		<select id="hostname" name="hostname" onchange="document.location='autoconfigbackup.php?hostname=' + this.value;">
			<?
			$host_not_found = true;
			foreach ($hostnames as $hn):
			?>
			<option value='<?=$hn?>' <? if ($hn == $hostname) {echo " selected=\"selected\""; $host_not_found = false;} ?>>
				<?=$hn?>
			</option>
			<?endforeach?>
			<? if ($host_not_found) { ?>
				<option value='<?=$hostname?>' SELECTED><?=$hostname?></option>
			<? } ?>
		</select>
		</div>
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed" id="backups">
				<thead>
					<tr>
						<th width="30%"><?=gettext("Date")?></th>
						<th width="60%"><?=gettext("Configuration Change")?></th>
						<th width="10%"><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody>

			<?php
				$counter = 0;
				foreach ($confvers as $cv):
			?>
					<tr>
						<td><?= $cv['localtime']; ?></td>
						<td><?= $cv['reason']; ?></td>
						<td>
							<a class="fa fa-undo"		title="<?=gettext('Restore this revision')?>"	href="autoconfigbackup.php?hostname=<?=urlencode($hostname)?>&newver=<?=urlencode($cv['time'])?>"	onclick="return confirm('<?=gettext("Are you sure you want to restore {$cv['localtime']}?")?>')"></a>
							<a class="fa fa-download"	title="<?=gettext('Show info')?>"	href="autoconfigbackup.php?download=<?=urlencode($cv['time'])?>&hostname=<?=urlencode($hostname)?>&reason=<?=urlencode($cv['reason'])?>"></a>
							<a class="fa fa-trash"		title="<?=gettext('Delete config')?>"	href="autoconfigbackup.php?hostname=<?=urlencode($hostname)?>&rmver=<?=urlencode($cv['time'])?>"></a>
						</td>
					</tr>
				<?php	$counter++;
				endforeach;
				if ($counter == 0): ?>
					<tr>
						<td colspan="3" align="center">
							<?=gettext("Sorry, no backups could be located on portal.pfsense.org for this hostname.")?> (<?=htmlspecialchars($hostname)?>)
						</td>
					</tr>
				<?php else: ?>
					<tr>
						<td colspan="3" align="center">
							<br /><?=gettext("Current count of hosted backups for this hostname on portal.pfsense.org")?> : <?= $counter ?>
						</td>
					</tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<?= print_info_box(sprintf(gettext("Click %s next to the revision to restore."), '<i class="fa fa-undo"></i>'), 'info'); ?>

<?php endif; ?>

</form>

<script type="text/javascript">
//<![CDATA[
events.push(function(){
	$('#loading').hide();
});
//]]>
</script>

<?php include("foot.inc"); ?>
