<?php
/*
 * suricata_blocked.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper
 * Copyright (c) 2005 Bill Marquette
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2024 Bill Meeks
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
require_once("/usr/local/pkg/suricata/suricata.inc");

global $g;

$suricatalogdir = SURICATALOGDIR;
$suri_pf_table = SURICATA_PF_TABLE;

$pconfig['brefresh'] = config_get_path('installedpackages/suricata/alertsblocks/brefresh', 'on');
$pconfig['blertnumber'] = config_get_path('installedpackages/suricata/alertsblocks/blertnumber', 500);
$bnentries = $pconfig['blertnumber'];

# --- AJAX REVERSE DNS RESOLVE Start ---
if (isset($_POST['resolve'])) {
	$ip = strtolower($_POST['resolve']);
	$res = (is_ipaddr($ip) ? gethostbyaddr($ip) : '');
	if (strpos($res, 'xn--') !== false) {
		$res = idn_to_utf8($res);
	}

	if ($res && $res != $ip)
		$response = array('resolve_ip' => $ip, 'resolve_text' => $res);
	else
		$response = array('resolve_ip' => $ip, 'resolve_text' => gettext("Cannot resolve"));

	echo json_encode(str_replace("\\","\\\\", $response)); // single escape chars can break JSON decode
	exit;
}
# --- AJAX REVERSE DNS RESOLVE End ---

# --- AJAX GEOIP CHECK Start ---
if (isset($_POST['geoip'])) {
	$ip = strtolower($_POST['geoip']);
	if (is_ipaddr($ip)) {
		$url = "https://api.hackertarget.com/geoip/?q={$ip}";
		$conn = curl_init("https://api.hackertarget.com/geoip/?q={$ip}");
		curl_setopt($conn, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($conn, CURLOPT_FRESH_CONNECT,  true);
		curl_setopt($conn, CURLOPT_RETURNTRANSFER, 1);
		set_curlproxy($conn);
		$res = curl_exec($conn);
		curl_close($conn);
	} else {
		$res = '';
	}

	if ($res && $res != $ip && !preg_match('/error/', $res))
		$response = array('geoip_text' => $res);
	else
		$response = array('geoip_text' => gettext("Cannot check {$ip}"));

	echo json_encode(str_replace("\\","\\\\", $response)); // single escape chars can break JSON decode
	exit;
}
# --- AJAX GEOIP CHECK End ---

if ($_POST['mode'] == 'todelete') {
	$ip = "";
	if ($_POST['ip'])
		$ip = $_POST['ip'];
	if (is_ipaddr($ip))
		exec("/sbin/pfctl -t {$suri_pf_table} -T delete {$ip}");
	else
		$input_errors[] = gettext("An invalid IP address was provided as a parameter.");
}

if ($_POST['remove']) {
	exec("/sbin/pfctl -t {$suri_pf_table} -T flush");
	header("Location: /suricata/suricata_blocked.php");
	exit;
}

/* TODO: build a file with block ip and disc */
if ($_POST['download'])
{
	$blocked_ips_array_save = "";
	exec("/sbin/pfctl -t {$suri_pf_table} -T show", $blocked_ips_array_save);
	/* build the list */
	if (is_array($blocked_ips_array_save) && count($blocked_ips_array_save) > 0) {
		$save_date = date("Y-m-d-H-i-s");
		$file_name = "suricata_blocked_{$save_date}.tar.gz";
		safe_mkdir("{$g['tmp_path']}/suricata_blocked");
		file_put_contents("{$g['tmp_path']}/suricata_blocked/suricata_block.pf", "");
		foreach($blocked_ips_array_save as $counter => $fileline) {
			if (empty($fileline))
				continue;
			$fileline = trim($fileline, " \n\t");
			file_put_contents("{$g['tmp_path']}/suricata_blocked/suricata_block.pf", "{$fileline}\n", FILE_APPEND);
		}

		// Create a tar gzip archive of blocked host IP addresses
		exec("/usr/bin/tar -czf {$g['tmp_path']}/{$file_name} -C{$g['tmp_path']}/suricata_blocked suricata_block.pf");

		// If we successfully created the archive, send it to the browser.
		if(file_exists("{$g['tmp_path']}/{$file_name}")) {
			ob_start(); //important or other posts will fail
			if (isset($_SERVER['HTTPS'])) {
				header('Pragma: ');
				header('Cache-Control: ');
			} else {
				header("Pragma: private");
				header("Cache-Control: private, must-revalidate");
			}
			header("Content-Type: application/octet-stream");
			header("Content-length: " . filesize("{$g['tmp_path']}/{$file_name}"));
			header("Content-disposition: attachment; filename = {$file_name}");
			ob_end_clean(); //important or other post will fail
			readfile("{$g['tmp_path']}/{$file_name}");

			// Clean up the temp files and directory
			unlink_if_exists("{$g['tmp_path']}/{$file_name}");
			rmdir_recursive("{$g['tmp_path']}/suricata_blocked");
		} else
			$savemsg = gettext("An error occurred while creating archive");
	} else
		$savemsg = gettext("No content on suricata block list");
}

if ($_POST['save'])
{
	/* no errors */
	if (!$input_errors) {
		config_set_path('installedpackages/suricata/alertsblocks/brefresh', $_POST['brefresh'] ? 'on' : 'off');
		config_set_path('installedpackages/suricata/alertsblocks/blertnumber', $_POST['blertnumber']);
		write_config("Suricata pkg: updated BLOCKED tab settings.");
		header("Location: /suricata/suricata_blocked.php");
		exit;
	}
}

$pglinks = array("", "/suricata/suricata_interfaces.php", "@self");
$pgtitle = array("Services", "Suricata", "Blocked Hosts");
include_once("head.inc");

/* refresh every 60 secs */
if ($pconfig['brefresh'] == 'on') {
	print '<meta http-equiv="refresh" content="60;url=/suricata/suricata_blocked.php" />';
}

/* Display Alert message */
if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg);
}

$tab_array = array();
$tab_array[] = array(gettext("Interfaces"), false, "/suricata/suricata_interfaces.php");
$tab_array[] = array(gettext("Global Settings"), false, "/suricata/suricata_global.php");
$tab_array[] = array(gettext("Updates"), false, "/suricata/suricata_download_updates.php");
$tab_array[] = array(gettext("Alerts"), false, "/suricata/suricata_alerts.php");
$tab_array[] = array(gettext("Blocks"), true, "/suricata/suricata_blocked.php");
$tab_array[] = array(gettext("Files"), false, "/suricata/suricata_files.php");
$tab_array[] = array(gettext("Pass Lists"), false, "/suricata/suricata_passlist.php");
$tab_array[] = array(gettext("Suppress"), false, "/suricata/suricata_suppress.php");
$tab_array[] = array(gettext("Logs View"), false, "/suricata/suricata_logs_browser.php?instance={$instanceid}");
$tab_array[] = array(gettext("Logs Mgmt"), false, "/suricata/suricata_logs_mgmt.php");
$tab_array[] = array(gettext("SID Mgmt"), false, "/suricata/suricata_sid_mgmt.php");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
$tab_array[] = array(gettext("IP Lists"), false, "/suricata/suricata_ip_list_mgmt.php");
display_top_tabs($tab_array, true);


$form = new Form(false);
$form->setAttribute('id', 'formblock');

$section = new Form_Section('Blocked Hosts Log View Settings');

$group = new Form_Group('Save or Remove Hosts');

$group->add(new Form_Button(
	'download',
	'Download',
	null,
	'fa-solid fa-download'
))->removeClass('btn-default')->addClass('btn-info btn-sm')
  ->setHelp('All blocked hosts will be saved');

$group->add(new Form_Button(
	'remove',
	'Clear',
	null,
	'fa-solid fa-trash-can'
))->removeClass('btn-default')->addClass('btn-danger btn-sm')
  ->setHelp('All blocked hosts will be cleared');

$section->add($group);

$group = new Form_Group('Save Settings');

$group->add(new Form_Button(
	'save',
	'Save',
	null,
	'fa-solid fa-save'
))->removeClass('btn-default')->addClass('btn-success btn-sm')
  ->setHelp('Save auto-refresh and view settings');

$group->add(new Form_Checkbox(
	'brefresh',
	null,
	'Refresh',
	((config_get_path('installedpackages/suricata/alertsblocks/brefresh')=="on") || (config_get_path('installedpackages/suricata/alertsblocks/brefresh')=='')),
	'on'
))->setHelp('Default is ON');

$group->add(new Form_Input(
	'blertnumber',
	'Blocked Entries',
	'number',
	$bnentries
	))->setHelp('Number of blocked entries to view. Default is 500');

$section->add($group);
$form->add($section);

$form->addGlobal(new Form_Input(
	'id',
	'id',
	'hidden',
	''
));
$form->addGlobal(new Form_Input(
	'ip',
	'ip',
	'hidden',
	''
));
$form->addGlobal(new Form_Input(
	'mode',
	'mode',
	'hidden',
	''
));

print($form);

?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=sprintf(gettext("Last %s Hosts Blocked by Suricata"), $bnentries)?></h2></div>
	<div class="panel-body table-responsive">
		<div class="content table-responsive">
			<span class="text-info"><b><?=gettext('Note: ');?></b><?=gettext('Only blocked IP addresses from Legacy Mode interfaces are shown! ' .
			'For inline IPS mode interfaces, dropped IP addresses are ');?><span class="text-danger"><?=gettext('highlighted ');?></span>
			<?=gettext('on the ALERTS tab.');?></span>
		</div>
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
			   <tr>
				<th><?=gettext("Blocked IP"); ?></th>
				<th><?=gettext("Block Date/Time"); ?></th>
				<th><?=gettext("Block Alert Description"); ?></th>
				<th><?=gettext("Block Rule GID:SID"); ?></th>
				<th><?=gettext("Remove Block"); ?></th>
			   </tr>
			</thead>
		<tbody>
		<?php

		/* set the arrays */
		$blocked_ips_array = suricata_get_blocked_ips();

		/* Change IP from presentation to network form for use as array key */
		if (!empty($blocked_ips_array)) {
			foreach ($blocked_ips_array as &$ip) {
				$ip = inet_pton($ip);
			}

			// Unset $blocked_ips_array reference as we are done with it
			unset($ip);

			$tmpblocked = array_flip($blocked_ips_array);
			$src_ip_list = array();

			foreach (glob("{$suricatalogdir}*/block.log") as $alertfile) {
				$fd = fopen($alertfile, "r");
				if ($fd) {

					/*************** FORMAT for file -- BLOCK -- **************************************************************************/
					/* Line format: timestamp  action [**] [gid:sid:rev] msg [**] [Classification: class] [Priority: pri] {proto} ip:port */
					/*              0          1            2   3   4    5                         6                 7     8      9  10   */
					/**********************************************************************************************************************/

					$buf = "";
					while (($buf = fgets($fd)) !== FALSE) {
						$fields = array();
						$tmp = array();

						// Drop any invalid line read from the log
						if (empty(trim($buf)))
							continue;

						/***************************************************************/
						/* Parse block log entry to find the parts we want to display. */
						/* We parse out all the fields even though we currently use    */
						/* just a few of them.                                         */
						/***************************************************************/

						// Field 0 is the event timestamp
						$fields['time'] = substr($buf, 0, strpos($buf, '  '));

						// Create a DateTime object from the event timestamp that
						// we can use to easily manipulate output formats.
						try {
							$event_tm = date_create_from_format("m/d/Y-H:i:s.u", $fields['time']);
						} catch (Exception $e) {
							logger(LOG_WARNING, localize_text("found invalid timestamp entry in current blocks.log, the line will be ignored and skipped."), LOG_PREFIX_PKG_SURICATA);
							continue;
						}

						// Field 1 is the action
						if (strpos($buf, '[') !== FALSE && strpos($buf, ']') !== FALSE)
							$fields['action'] = substr($buf, strpos($buf, '[') + 1, strpos($buf, ']') - strpos($buf, '[') - 1);
						else
							$fields['action'] = null;

						// The regular expression match below returns an array as follows:
						// [2] => GID, [3] => SID, [4] => REV, [5] => MSG, [6] => CLASSIFICATION, [7] = PRIORITY
						preg_match('/\[\*{2}\]\s\[((\d+):(\d+):(\d+))\]\s(.*)\[\*{2}\]\s\[Classification:\s(.*)\]\s\[Priority:\s(\d+)\]\s/', $buf, $tmp);
						$fields['gid'] = trim($tmp[2]);
						$fields['sid'] = trim($tmp[3]);
						$fields['rev'] = trim($tmp[4]);
						$fields['msg'] = trim($tmp[5]);
						$fields['class'] = trim($tmp[6]);
						$fields['priority'] = trim($tmp[7]);

						// The regular expression match below looks for the PROTO, IP and PORT fields
						// and returns an array as follows:
						// [1] = PROTO, [2] => IP:PORT
						if (preg_match('/\{(.*)\}\s(.*)/', $buf, $tmp)) {
							// Get PROTO
							$fields['proto'] = trim($tmp[1]);

							// Get IP
							$fields['ip'] = trim(substr($tmp[2], 0, strrpos($tmp[2], ':')));
							if (is_ipaddrv6($fields['ip']))
								$fields['ip'] = inet_ntop(inet_pton($fields['ip']));

							// Get PORT
							$fields['port'] = trim(substr($tmp[2], strrpos($tmp[2], ':') + 1));
						}

						// In the unlikely event we read an old log file and fail to parse
						// out an IP address, just skip the record since we can't use it.
						if (empty($fields['ip']))
							continue;
						$fields['ip'] = inet_pton($fields['ip']);
						if (isset($tmpblocked[$fields['ip']])) {
							if (!is_array($src_ip_list[$fields['ip']]))
								$src_ip_list[$fields['ip']] = array();
							if (!is_array($src_ip_list[$fields['ip']]['time']))
								$src_ip_list[$fields['ip']]['time'] = array();
							if (!is_array($src_ip_list[$fields['ip']]['msg']))
								$src_ip_list[$fields['ip']]['msg'] = array();
							if (!is_array($src_ip_list[$fields['ip']]['rule_id']))
								$src_ip_list[$fields['ip']]['rule_id'] = array();

							/* Time */
							@$alert_time = date_format($event_tm, "H:i:s");
							/* Date */
							@$alert_date = date_format($event_tm, "m/d/Y");

							$src_ip_list[$fields['ip']]['time'][] = $alert_date . " " . $alert_time;
							$src_ip_list[$fields['ip']]['msg'][] = "{$fields['msg']}";
							$src_ip_list[$fields['ip']]['rule_id'][] = "{$fields['gid']}:{$fields['sid']}";
						}
					}
					fclose($fd);
				}
			}

			foreach($blocked_ips_array as $blocked_ip) {
				if (is_ipaddr($blocked_ip) && !isset($src_ip_list[$blocked_ip])) {
					$src_ip_list[$blocked_ip] = array("N\A\n");
				}
			}

			/* build final list, build html */
			$counter = 0;
			foreach($src_ip_list as $blocked_ip => $blocked) {
				/* Reverse the 'time', 'msg', and 'rule_id' arrays to display most recent event first */
				$blocked_time = implode("<br/>", array_reverse($blocked['time'], true));
				$blocked_desc = implode("<br/>", array_reverse($blocked['msg'], true));
				$blocked_ruleid = implode("<br/>", array_reverse($blocked['rule_id'], true));
				if($counter > $bnentries)
					break;
				else
					$counter++;

				$block_ip_str = inet_ntop($blocked_ip);
				/* Add zero-width space as soft-break opportunity after each colon if we have an IPv6 address */
				$tmp_ip = str_replace(":", ":&#8203;", $block_ip_str);
				/* Add reverse DNS lookup icons */
				$rdns_link = "";
				$rdns_link .= "<i class=\"fa-solid fa-search icon-pointer\" onclick=\"javascript:resolve_with_ajax('{$block_ip_str}');\" title=\"";
				$rdns_link .= gettext("Resolve host via reverse DNS lookup") . "\" alt=\"Icon Reverse Resolve with DNS\"></i>";
				/* Add GeoIP check icon */
				if (!is_private_ip($block_ip_str) && (substr($block_ip_str, 0, 2) != 'fc') &&
				    (substr($block_ip_str, 0, 2) != 'fd')) {
					$rdns_link .= "&nbsp;&nbsp;<i class=\"fa-solid fa-globe\" onclick=\"javascript:geoip_with_ajax('{$block_ip_str}');\" title=\"";
					$rdns_link .= gettext("Check host GeoIP data") . "\" alt=\"Icon Check host GeoIP\"></i>";
				}
		?>
				<tr class="text-nowrap">
					<td style="word-wrap:break-word; white-space:normal"><?=$tmp_ip;?>&nbsp;&nbsp;<?=$rdns_link;?></td>
					<td><?=$blocked_time;?></td>
					<td style="word-wrap:break-word; white-space:normal"><?=$blocked_desc;?></td>
					<td><?=$blocked_ruleid;?></td>
					<td><i class="fa-solid fa-times icon-pointer text-danger" onClick="$('#ip').val('<?=$block_ip_str;?>');$('#mode').val('todelete');$('#formblock').submit();"
					 title="<?=gettext("Delete host from Blocked Table");?>"></i></td>
				</tr>
		<?php
			}
		}
		?>
				</tbody>
				<tfoot>
					<tr>
						<td colspan="5" style="text-align:center;" class="alert-info">

<?php	if (!empty($blocked_ips_array)) {
			if ($counter > 1)
				print($counter . gettext(" host IP addresses are currently being blocked."));
			else
				print($counter . gettext(" host IP address is currently being blocked."));
		} else {
			print(gettext("There are currently no hosts being blocked by Suricata."));
	}
?>
						</td>
					</tr>
				</tfoot>
		</table>
	</div>
</div>

<!-- The following AJAX code was borrowed from the diag_logs_filter.php -->
<!-- file in pfSense.  See copyright info at top of this page.          -->
<script type="text/javascript">
//<![CDATA[
function resolve_with_ajax(ip_to_resolve) {
	var url = "/suricata/suricata_blocked.php";

	$.ajax(
		url,
		{
			type: 'post',
			dataType: 'json',
			data: {
				resolve: ip_to_resolve,
				},
			complete: resolve_ip_callback
		});
}

function resolve_ip_callback(transport) {
	var response = $.parseJSON(transport.responseText);
	var msg = 'IP address "' + response.resolve_ip + '" resolves to\n';
	alert(msg + 'host "' + htmlspecialchars(response.resolve_text) + '"');
}

function geoip_with_ajax(ip_to_check) {
	var url = "/suricata/suricata_blocked.php";

	$.ajax(
		url,
		{
			type: 'post',
			dataType: 'json',
			data: {
				geoip: ip_to_check,
			      },
			complete: geoip_callback
		});
}

function geoip_callback(transport) {
	var response = $.parseJSON(transport.responseText);
	alert(htmlspecialchars(response.geoip_text));
}

// From http://stackoverflow.com/questions/5499078/fastest-method-to-escape-html-tags-as-html-entities
function htmlspecialchars(str) {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&apos;');
}
//]]>
</script>

<?php
include("foot.inc");
?>
