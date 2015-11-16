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

global $pf_version;
$pf_version = substr(trim(file_get_contents("/etc/version")), 0, 3);
if ($pf_version < 2.0) {
	require("crypt_acb.php");
}

// Seperator used during client / server communications
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
	$pgtitle = "Diagnostics: Auto Configuration Backup revision information";
} else {
	$pgtitle = "Diagnostics: Auto Configuration Backup";
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
	curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, 0);
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

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<div id='maincontent'>
<script src="/javascript/scriptaculous/prototype.js" type="text/javascript"></script>
<?php
	include("fbegin.inc");
	if ($pf_version < 2.0) {
		echo "<p class=\"pgtitle\">{$pgtitle}</p>";
	}
	if ($savemsg) {
		echo "<div id='savemsg'>";
		print_info_box($savemsg);
		echo "</div>";
	}
	if ($input_errors) {
		print_input_errors($input_errors);
	}
	if ($hostname <> $myhostname) {
		print_info_box("Warning! You are currently viewing an alternate host's backup history ($hostname)");
	}
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td>
<div id="loading">
	<img src="/themes/metallic/images/misc/loader.gif" alt="" /> Loading, please wait...
	<div>&nbsp;</div>
</div>
<div id='feedbackdiv'></div>
	<?php
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
</td></tr>
<tr><td>
	<table id="backuptable" class="tabcont" align="center" width="100%" border="0" cellpadding="6" cellspacing="0">
	<tr>
		<td colspan="2" align="left">
			<?php
				if ($_REQUEST['rmver'] != "") {
					$curl_session = curl_init();
					curl_setopt($curl_session, CURLOPT_URL, $del_url);
					curl_setopt($curl_session, CURLOPT_HTTPHEADER, array("Authorization: Basic " . base64_encode("{$username}:{$password}")));
					curl_setopt($curl_session, CURLOPT_POST, 3);
					curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, 0);
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
					print_info_box($savemsg);
				}
				if ($_REQUEST['newver'] != "") {
					// Phone home and obtain backups
					$curl_session = curl_init();
					curl_setopt($curl_session, CURLOPT_URL, $get_url);
					curl_setopt($curl_session, CURLOPT_HTTPHEADER, array("Authorization: Basic " . base64_encode("{$username}:{$password}")));
					curl_setopt($curl_session, CURLOPT_POST, 3);
					curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, 0);
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
						if ($ondisksha256 <> $sha256) {
							$input_errors[] = "SHA256 values do not match, cannot restore. $ondisksha256 <> $sha256";
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
						<form action="reboot.php" method="post">
							Would you like to reboot?
							<input name="Submit" type="submit" class="formbtn" value=" Yes " />
							<input name="Submit" type="submit" class="formbtn" value=" No " />
						</form>
EOF;
						} else {
							$savemsg = "Unable to revert to the selected configuration.";
						}
						print_info_box($savemsg);
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
					curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, 0);
					curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($curl_session, CURLOPT_POSTFIELDS, "action=restore" . "&hostname=" . urlencode($hostname) . "&revision=" . urlencode($_REQUEST['download']));
					curl_setopt($curl_session, CURLOPT_USERAGENT, $g['product_name'] . '/' . rtrim(file_get_contents("/etc/version")));
					// Proxy
					curl_setopt_array($curl_session, configure_proxy());
					$data = curl_exec($curl_session);
					if (!tagfile_deformat($data, $data1, "config.xml")) {
						$input_errors[] = "The downloaded file does not appear to contain an encrypted pfSense configuration.";
					}
					if ($input_errors) {
						print_input_errors($input_errors);
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
						$data = htmlentities(decrypt_data($data, $decrypt_password));
						if (!strstr($data, "pfsense")) {
							$data = "Could not decrypt. Different encryption key?";
							$input_errors[] = "Could not decrypt config.xml";
						}
						echo "<h2>Hostname</h2>";
						echo "<textarea rows='1' cols='70'>{$hostname}</textarea>";
						echo "<h2>Revision date/time</h2>";
						echo "<textarea name='download' rows='1' cols='70'>{$_REQUEST['download']}</textarea>";
						echo "<h2>Revision reason</h2>";
						echo "<textarea name='download' rows='1' cols='70'>{$_REQUEST['reason']}</textarea>";
						echo "<h2>SHA256 summary</h2>";
						echo "<textarea name='shasum' rows='1' cols='70'>{$sha256sum}</textarea>";
						echo "<h2>Encrypted config.xml</h2>";
						echo "<textarea name='config_xml' rows='40' cols='70'>{$ds[1]}</textarea>";
						echo "<h2>Decrypted config.xml</h2>";
						echo "<textarea name='dec_config_xml' rows='40' cols='70'>{$data}</textarea>";
					}
					if (!$input_errors) {
						echo "<br /><input type=\"button\" value=\"Install this revision\" onclick=\"document.location='autoconfigbackup.php?newver=" . urlencode($_REQUEST['download']) . "';\">";
					}
					echo "<script type=\"text/javascript\">";
					echo "$('loading').innerHTML = '';";
					echo "</script>";
					echo "</td></tr></table></div></td></td></tr></tr></table></form>";
					require("fend.inc");
					exit;
				}
				// Populate available backups
				$curl_session = curl_init();
				curl_setopt($curl_session, CURLOPT_URL, $get_url);
				curl_setopt($curl_session, CURLOPT_HTTPHEADER, array("Authorization: Basic " . base64_encode("{$username}:{$password}")));
				curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, 0);
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
				if ($input_errors) {
					print_input_errors($input_errors);
				}
			?>
		</td>
	</tr>
	<tr>
		<td colspan="2">
			<div style="text-align: center;">
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
		</td>
	</tr>
	<tr>
		<td width="30%" class="listhdrr">Date</td>
		<td width="70%" class="listhdrr">Configuration Change</td>
	</tr>
<?php
	$counter = 0;
	echo "<script type=\"text/javascript\">";
	echo "$('loading').innerHTML = '';";
	echo "</script>";
	foreach ($confvers as $cv):
?>
		<tr valign="top">
			<td class="listlr"> <?= $cv['localtime']; ?></td>
			<td class="listbg"> <?= $cv['reason']; ?></td>
			<td colspan="2" valign="middle" class="list" nowrap="nowrap">
				<a title="Restore this revision" onclick="return confirm('Are you sure you want to restore <?= $cv['localtime']; ?>?')" href="autoconfigbackup.php?hostname=<?=urlencode($hostname)?>&newver=<?=urlencode($cv['time']);?>">
				<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="" />
				</a>
				<a title="Show info" href="autoconfigbackup.php?download=<?=urlencode($cv['time']);?>&hostname=<?=urlencode($hostname)?>&reason=<?php echo urlencode($cv['reason']);?>">
				<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_down.gif" width="17" height="17" border="0" alt="" />
				</a>
				<a title="Delete" onclick="return confirm('Are you sure you want to delete <?= $cv['localtime']; ?>?')"href="autoconfigbackup.php?hostname=<?=urlencode($hostname)?>&rmver=<?=urlencode($cv['time']);?>">
				<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" alt="" />
				</a>
			</td>
		</tr>
<?php
		$counter++;
	endforeach;
	if ($counter == 0) {
		echo "<tr><td colspan='3' align='center'>Sorry, we could not locate any backups at portal.pfsense.org for this hostname ({$hostname}).</td></tr>";
	} else {
		echo "<tr><td colspan='3' align='center'><br />Backups hosted currently for this hostname on portal.pfsense.org: {$counter}.</td></tr>";
	}
?>
	</table>
	</div>
	</td>
		<tr><td>
			<div>
				<strong>&nbsp;&nbsp;<span class="red">Hint:&nbsp;</span></strong>Click the + sign next to the revision you would like to restore.
			</div>
		</td></tr>
</tr></table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
