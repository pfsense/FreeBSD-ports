<?php
/*
 * snort_blocked.php
 *
 * Copyright (C) 2006 Scott Ullrich
 * All rights reserved.
 *
 * Modified for the Pfsense snort package v. 1.8+
 * Copyright (C) 2009 Robert Zelaya Sr. Developer
 * Copyright (C) 2014 Jim Pingle jim@pingle.org
 * Copyright (C) 2014-2016 Bill Meeks
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

require_once("guiconfig.inc");
require_once("/usr/local/pkg/snort/snort.inc");

$snortlogdir = SNORTLOGDIR;

// Grab pfSense version so we can refer to it later on this page
$pfs_version=substr(trim(file_get_contents("/etc/version")),0,3);

if (!is_array($config['installedpackages']['snortglobal']['alertsblocks']))
	$config['installedpackages']['snortglobal']['alertsblocks'] = array();

$pconfig['brefresh'] = $config['installedpackages']['snortglobal']['alertsblocks']['brefresh'];
$pconfig['blertnumber'] = $config['installedpackages']['snortglobal']['alertsblocks']['blertnumber'];

if (empty($pconfig['blertnumber']))
	$bnentries = '500';
else
	$bnentries = $pconfig['blertnumber'];

if (empty($pconfig['brefresh']))
	$bnentries = 'on';

# --- AJAX REVERSE DNS RESOLVE Start ---
if (isset($_POST['resolve'])) {
	$ip = strtolower($_POST['resolve']);
	$res = (is_ipaddr($ip) ? gethostbyaddr($ip) : '');
	
	if ($res && $res != $ip)
		$response = array('resolve_ip' => $ip, 'resolve_text' => $res);
	else
		$response = array('resolve_ip' => $ip, 'resolve_text' => gettext("Cannot resolve"));
	
	print(json_encode(str_replace("\\","\\\\", $response))); // single escape chars can break JSON decode
	exit;
}
# --- AJAX REVERSE DNS RESOLVE End ---

if ($_POST['mode'] == 'todelete') {
	$ip = "";
	if ($_POST['ip'])
		$ip = $_POST['ip'];
	if (is_ipaddr($ip))
		exec("/sbin/pfctl -t snort2c -T delete {$ip}");
	else
		$input_errors[] = gettext("An invalid IP address was provided as a parameter.");
}

if ($_POST['remove']) {
	exec("/sbin/pfctl -t snort2c -T flush");
	header("Location: /snort/snort_blocked.php");
	exit;
}

/* TODO: build a file with block ip and disc */
if ($_POST['download'])
{
	$blocked_ips_array_save = "";
	exec('/sbin/pfctl -t snort2c -T show', $blocked_ips_array_save);
	/* build the list */
	if (is_array($blocked_ips_array_save) && count($blocked_ips_array_save) > 0) {
		$save_date = date("Y-m-d-H-i-s");
		$file_name = "snort_blocked_{$save_date}.tar.gz";
		safe_mkdir("{$g['tmp_path']}/snort_blocked");
		file_put_contents("{$g['tmp_path']}/snort_blocked/snort_block.pf", "");
		foreach($blocked_ips_array_save as $counter => $fileline) {
			if (empty($fileline))
				continue;
			$fileline = trim($fileline, " \n\t");
			file_put_contents("{$g['tmp_path']}/snort_blocked/snort_block.pf", "{$fileline}\n", FILE_APPEND);
		}

		// Create a tar gzip archive of blocked host IP addresses
		exec("/usr/bin/tar -czf {$g['tmp_path']}/{$file_name} -C{$g['tmp_path']}/snort_blocked snort_block.pf");

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
			rmdir_recursive("{$g['tmp_path']}/snort_blocked");
		} else
			$savemsg = gettext("An error occurred while creating archive");
	} else
		$savemsg = gettext("No content on snort block list");
}

if ($_POST['save'])
{
	/* no errors */
	if (!$input_errors) {
		$config['installedpackages']['snortglobal']['alertsblocks']['brefresh'] = $_POST['brefresh'] ? 'on' : 'off';
		$config['installedpackages']['snortglobal']['alertsblocks']['blertnumber'] = $_POST['blertnumber'];

		write_config("Snort pkg: updated BLOCKED tab settings.");

		header("Location: /snort/snort_blocked.php");
		exit;
	}

}

$pgtitle = array(gettext("Services"), gettext("Snort"), gettext("Blocked Hosts"));
include("head.inc");

/* refresh every 60 secs */
if ($pconfig['brefresh'] == 'on')
	print '<meta http-equiv="refresh" content="60;url=/snort/snort_blocked.php" />';

/* Display Alert message */
if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg);
}

$tab_array = array();
$tab_array[] = array(gettext("Snort Interfaces"), false, "/snort/snort_interfaces.php");
$tab_array[] = array(gettext("Global Settings"), false, "/snort/snort_interfaces_global.php");
$tab_array[] = array(gettext("Updates"), false, "/snort/snort_download_updates.php");
$tab_array[] = array(gettext("Alerts"), false, "/snort/snort_alerts.php");
$tab_array[] = array(gettext("Blocked"), true, "/snort/snort_blocked.php");
$tab_array[] = array(gettext("Pass Lists"), false, "/snort/snort_passlist.php");
$tab_array[] = array(gettext("Suppress"), false, "/snort/snort_interfaces_suppress.php");
$tab_array[] = array(gettext("IP Lists"), false, "/snort/snort_ip_list_mgmt.php");
$tab_array[] = array(gettext("SID Mgmt"), false, "/snort/snort_sid_mgmt.php");
$tab_array[] = array(gettext("Log Mgmt"), false, "/snort/snort_log_mgmt.php");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=snort/snort_sync.xml");
display_top_tabs($tab_array, true);

$form = new Form(false);
$form->setAttribute('id', 'formblock');

$section = new Form_Section('Blocked Hosts and Log View Settings');

$group = new Form_Group('Blocked Hosts');

$group->add(new Form_Button(
	'download',
	'Download',
	null,
	'fa-download'
))->removeClass('btn-default')->addClass('btn-success btn-sm')
  ->setHelp('All blocked hosts will be saved');

$group->add(new Form_Button(
	'remove',
	'Clear',
	null,
	'fa-trash'
))->removeClass('btn-default')->addClass('btn-danger btn-sm')
  ->setHelp('All blocked hosts will be removed');

$section->add($group);

$group = new Form_Group('Refresh and Log View');

$group->add(new Form_Button(
	'save',
	'Save',
	null,
	'fa-save'
))->removeClass('btn-default')->addClass('btn-primary btn-sm')
  ->setHelp('Save auto-refresh and view settings');

$group->add(new Form_Checkbox(
	'brefresh',
	null,
	'Refresh',
	(($config['installedpackages']['snortglobal']['alertsblocks']['brefresh']=='on') || ($config['installedpackages']['snortglobal']['alertsblocks']['brefresh']=='')),
	'on'
))->setHelp('Deault is ON');

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
	null,
	'hidden',
	''
));
$form->addGlobal(new Form_Input(
	'ip',
	null,
	'hidden',
	''
));
$form->addGlobal(new Form_Input(
	'mode',
	null,
	'hidden',
	''
));

print($form);

?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=sprintf(gettext("Last %s Hosts Blocked by Snort"), $bnentries)?></h2></div>
	<div class="panel-body table-responsive">

		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr class="sortableHeaderRowIdentifier text-nowrap">
					<th data-sortable-type="numeric">#</th>
					<th><?=gettext("IP"); ?></th>
					<th data-sortable-type="alpha"><?=gettext("Alert Descriptions and Event Times"); ?></th>
					<th data-sortable="false"><?=gettext("Remove"); ?></th>
				   </tr>
			</thead>
			<tbody>
			<?php
			/* set the arrays */
			$blocked_ips_array = array();
			if (is_array($blocked_ips)) {
				foreach ($blocked_ips as $blocked_ip) {
					if (empty($blocked_ip))
						continue;
					$blocked_ips_array[] = trim($blocked_ip, " \n\t");
				}
			}
			$blocked_ips_array = snort_get_blocked_ips();
			if (!empty($blocked_ips_array)) {
				$tmpblocked = array_flip($blocked_ips_array);
				$src_ip_list = array();
				foreach (glob("{$snortlogdir}/*/alert") as $alertfile) {
					$fd = fopen($alertfile, "r");
					if ($fd) {
						/*                 0         1           2      3      4    5    6    7      8     9    10    11             12
						/* File format timestamp,sig_generator,sig_id,sig_rev,msg,proto,src,srcport,dst,dstport,id,classification,priority */
						while (($fields = fgetcsv($fd, 1000, ',', '"')) !== FALSE) {
							if(count($fields) < 13)
								continue;
					
							if (isset($tmpblocked[$fields[6]])) {
								if (!is_array($src_ip_list[$fields[6]]))
									$src_ip_list[$fields[6]] = array();
								$src_ip_list[$fields[6]][$fields[4]] = "{$fields[4]} - " . substr($fields[0], 0, -8);
							}
							if (isset($tmpblocked[$fields[8]])) {
								if (!is_array($src_ip_list[$fields[8]]))
									$src_ip_list[$fields[8]] = array();
								$src_ip_list[$fields[8]][$fields[4]] = "{$fields[4]} - " . substr($fields[0], 0, -8);
							}
						}
						fclose($fd);
					}
				}

				foreach($blocked_ips_array as $blocked_ip) {
					if (is_ipaddr($blocked_ip) && !isset($src_ip_list[$blocked_ip]))
						$src_ip_list[$blocked_ip] = array("Alert Description No Longer Available\n");
				}

				/* build final list, preg_match, build html */
				$counter = 0;
				foreach($src_ip_list as $blocked_ip => $blocked_msg) {
					$blocked_desc = implode("<br/>", $blocked_msg);
					if($counter > $bnentries)
						break;
					else
						$counter++;

					/* Add zero-width space as soft-break opportunity after each colon if we have an IPv6 address */
					$tmp_ip = str_replace(":", ":&#8203;", $blocked_ip);
					/* Add reverse DNS lookup icons (two different links if pfSense version supports them) */
					$rdns_link = "";
					$rdns_link .= "<i class=\"fa fa-search icon-pointer\" onclick=\"javascript:resolve_with_ajax('{$blocked_ip}');\" title=\"";
					$rdns_link .= gettext("Resolve host via reverse DNS lookup") . "\" alt=\"Icon Reverse Resolve with DNS\"></i>";

					/* print the table row */
						print("<tr class=\"text-nowrap\">
							<td>{$counter}</td>
							<td style=\"word-wrap:break-word; white-space:normal\">{$tmp_ip}<br/>{$rdns_link}</td>
							<td style=\"word-wrap:break-word; white-space:normal\">{$blocked_desc}</td>
							<td><i class=\"fa fa-times icon-pointer text-danger\" onClick=\"$('#ip').val('{$blocked_ip}');$('#mode').val('todelete');$('#formblock').submit();\" 
							 title=\"" . gettext("Delete host from Blocked Table") . "\"></i></td>
						</tr>\n");
				}
			}
			?>
			</tbody>
			<tfoot>
				<tr>
					<td colspan="4" style="text-align:center;" class="alert-info">
						<?php	if (!empty($blocked_ips_array)) {
							if ($counter > 1)
								print($counter . gettext(" host IP addresses are currently being blocked by Snort."));
							else
								print($counter . gettext(" host IP address is currently being blocked Snort."));
						}
						else {
							print(gettext("There are currently no hosts being blocked by Snort."));
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
		var url = "/snort/snort_blocked.php";

		jQuery.ajax(
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
		var response = jQuery.parseJSON(transport.responseText);
		var msg = 'IP address "' + response.resolve_ip + '" resolves to\n';
		alert(msg + 'host "' + htmlspecialchars(response.resolve_text) + '"');
	}

	// From http://stackoverflow.com/questions/5499078/fastest-method-to-escape-html-tags-as-html-entities
	function htmlspecialchars(str) {
    	return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&apos;');
	}

//]]>
</script>

<?php include("foot.inc"); ?>

