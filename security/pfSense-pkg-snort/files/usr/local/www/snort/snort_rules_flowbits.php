<?php
/*
 * snort_rules_flowbits.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013-2022 Bill Meeks
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
require_once("/usr/local/pkg/snort/snort.inc");

global $g, $rebuild_rules;

$snortdir = SNORTDIR;
$flowbit_rules_file = FLOWBITS_FILENAME;
$rules_map = array();
$supplist = array();

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

if (is_null($id)) {
	header("Location: /snort/snort_interfaces.php");
	exit;
}

// Set who called us so we can return to the correct page with
// the RETURN ('cancel') button.
if (isset($_POST['referrer']) && !empty($_POST['referrer'])) {
	$referrer = urldecode($_POST['referrer']);
}
else {
	$referrer = $_SERVER['HTTP_REFERER'];
}

// Make sure a rule index ID is appended to the return URL
if (strpos($referrer, "?id={$id}") === FALSE)
	$referrer .= "?id={$id}";

// If RETURN button clicked, exit to original calling page
if (isset($_POST['cancel'])) {
	header("Location: {$referrer}");
	exit;
}

$a_nat = config_get_path("installedpackages/snortglobal/rule/{$id}", []);
$if_real = get_real_interface($a_nat['interface']);
$snort_uuid = $a_nat['uuid'];

/* We should normally never get to this page if Auto-Flowbits are disabled, but just in case... */
if ($a_nat['autoflowbitrules'] == 'on') {
	if (file_exists("{$snortdir}/snort_{$snort_uuid}_{$if_real}/rules/{$flowbit_rules_file}") &&
	    filesize("{$snortdir}/snort_{$snort_uuid}_{$if_real}/rules/{$flowbit_rules_file}") > 0) {
		$rules_map = snort_load_rules_map("{$snortdir}/snort_{$snort_uuid}_{$if_real}/rules/{$flowbit_rules_file}");
	}
	else
		$savemsg = gettext("There are no flowbit-required rules necessary for the current enforcing rule set.");
}
else
	$input_errors[] = gettext("Auto-Flowbit rule generation is disabled for this interface!");

if ($_POST['addsuppress'] && is_numeric($_POST['sid']) && is_numeric($_POST['gid'])) {
	$descr = snort_get_msg($rules_map[$_POST['gid']][$_POST['sid']]['rule']);
	$suppress = gettext("## -- This rule manually suppressed from the Auto-Flowbits list. -- ##\n");
	if (empty($descr))
		$suppress .= "suppress gen_id {$_POST['gid']}, sig_id {$_POST['sid']}\n";
	else
		$suppress .= "# {$descr}\nsuppress gen_id {$_POST['gid']}, sig_id {$_POST['sid']}\n";
	$a_suppress = config_get_path('installedpackages/snortglobal/suppress/item', []);
	$found_list = false;

	if (empty($a_nat['suppresslistname']) || $a_nat['suppresslistname'] == 'default') {
		$s_list = array();
		$s_list['uuid'] = uniqid();
		$s_list['name'] = $a_nat['interface'] . "suppress" . "_" . $s_list['uuid'];
		$s_list['descr']  =  "Auto-generated list for Alert suppression";
		$s_list['suppresspassthru'] = base64_encode($suppress);
		$a_suppress[] = $s_list;
		$a_nat['suppresslistname'] = $s_list['name'];
		$found_list = true;
	} else {
		/* If we get here, a Suppress List is defined for the interface so see if we can find it */
		foreach ($a_suppress as $a_id => $alist) {
			if ($alist['name'] == $a_nat['suppresslistname']) {
				$found_list = true;
				if (!empty($alist['suppresspassthru'])) {
					$tmplist = base64_decode($alist['suppresspassthru']);
					$tmplist .= "\n{$suppress}";
					$alist['suppresspassthru'] = base64_encode($tmplist);
					$a_suppress[$a_id] = $alist;
				}
				else {
					$alist['suppresspassthru'] = base64_encode($suppress);
					$a_suppress[$a_id] = $alist;
				}
			}
		}
	}
	if ($found_list) {
		config_set_path('installedpackages/snortglobal/suppress/item', $a_suppress);
		config_set_path("installedpackages/snortglobal/rule/{$id}", $a_nat);
		write_config("Snort pkg: modified Suppress List for {$a_nat['interface']}.");
		$rebuild_rules = false;
		sync_snort_package_config();
		snort_reload_config($a_nat);
		$savemsg = gettext("An entry to suppress the Alert for 'gen_id {$_POST['gid']}, sig_id {$_POST['sid']}' has been added to Suppress List '{$a_nat['suppresslistname']}'.");
	}
	else {
		/* We did not find the defined list, so notify the user with an error */
		$input_errors[] = gettext("Suppress List '{$a_nat['suppresslistname']}' is defined for this interface, but it could not be found!");
	}
}

/* Load up an array with the current Suppression List GID,SID values */
$supplist = snort_load_suppress_sigs($a_nat);

$if_friendly = convert_friendly_interface_to_friendly_descr($a_nat['interface']);
$pgtitle = array(gettext("Services"), gettext("Snort"), gettext("Flowbit Rules"), gettext("{$if_friendly}"));
include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);
?>

<form action="snort_rules_flowbits.php" method="post" enctype="multipart/form-data" class="form-horizontal" name="iform" id="iform">
<input type="hidden" name="id" value="<?=$id;?>"/>
<input type="hidden" name="referrer" value="<?=$referrer;?>"/>
<input type="hidden" name="sid" id="sid" value=""/>
<input type="hidden" name="gid" id="gid" value=""/>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Auto-Generated Flowbit-Required Rules")?></h2></div>
	<div class="panel-body">
		<?php
		print_callout('<p>' . gettext("The rules listed below are required to be included in the rules set ") . 
			gettext("because they set flowbits that are checked and relied upon by rules in the enforcing rules set.  ") . 
			gettext("If these dependent flowbits are not set, then some of your chosen rules may not fire.  ") . 
			gettext("Enabling all the rules that set these dependent flowbits ensures your chosen rules fire as intended.  ") . 
			gettext("Most flowbits rules contain the ") . '<em>noalert</em>' . gettext(" keyword to prevent an alert from firing ") . 
			gettext("when the flowbit is detected.  For those flowbit rules that do not contain the ") . '<em>noalert</em>' . 
			gettext(" option, click the ") . gettext("icon displayed beside the Signature ID (SID) to add the alert to the Suppression List if desired.") . 
			'</p>', 'info', 'Note:');
		?>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Flowbit-Required Rules for {$if_friendly}")?></h2></div>
	<div class="panel-body">
		<div class="content pull-left">
			<dl class="dl-horizontal">
				<dt><i class="fa-regular fa-square-plus"></i></dt><dd><?=gettext('Alert is not suppressed');?></dd>
				<dt><i class="fa-solid fa-info-circle"></i></dt><dd><?=gettext('Alert is suppressed');?><dd>
				<dt></dt><dd class="text-info"><b><?=gettext('Note: ');?></b><?=gettext('Icons are only displayed for flowbit rules without the ' . '<em>noalert</em>' . ' option.');?></dd>
			</dl>
		</div>
		<div class="content clearfix">
			<button type="submit" class="btn btn-default btn-sm btn-success pull-right" id="cancel" name="cancel" title="<?=gettext('Return to previous page');?>">
				<i class="fa-solid fa-backward icon-embed-btn text-success"></i>
				<?=gettext('Return'); ?>
			</button>
		</div>
		<div class="table-responsive">
			<table style="table-layout: fixed; width: 100%;" class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
				<colgroup>
					<col width="11%">
					<col width="5%">
					<col width="14%">
					<col width="14%">
					<col width="24%">
					<col>
				</colgroup>
				<thead>
				   <tr class="sortableHeaderRowIdentifier text-nowrap">
					<th data-sortable-type="numeric"><?=gettext("SID"); ?></th>
					<th><?=gettext("Proto"); ?></th>
					<th><?=gettext("Source"); ?></th>
					<th><?=gettext("Destination"); ?></th>
					<th><?=gettext("Flowbits"); ?></th>
					<th><?=gettext("Message"); ?></th>
				   </tr>
				<thead>
				<tbody>
					<?php
						$count = 0;
						foreach ($rules_map as $k1 => $rulem) {
							foreach ($rulem as $k2 => $v) {
								$sid = snort_get_sid($v['rule']);
								$gid = snort_get_gid($v['rule']);

								// Pick off the first section of the rule (prior to the start of the MSG field),
								// and then use a REGX split to isolate the remaining fields into an array.
								$tmp = substr($v['rule'], 0, strpos($v['rule'], "("));
								$tmp = trim(preg_replace('/^\s*#+\s*/', '', $tmp));
								$rule_content = preg_split('/[\s]+/', $tmp);

								$protocol = $rule_content[1];         //protocol
								$source = $rule_content[2];           //source
								$destination = $rule_content[5];      //destination
								$message = snort_get_msg($v['rule']); // description
								$flowbits = implode("; ", snort_get_flowbits($v['rule']));
								if (strstr($flowbits, "noalert"))
									$supplink = "";
								else {
									if (!isset($supplist[$gid][$sid])) {
										$supplink = "<i class=\"fa-regular fa-square-plus icon-pointer\" onClick=\"doAddSuppress('{$gid}','{$sid}');\"";
										$supplink .= " title='" . gettext("Click to add to Suppress List") . "'></i>";
									}
									else {
										$supplink = "<i class=\"fa-solid fa-info-circle icon-pointer\" title='";
										$supplink .= gettext("Alert has been suppressed") . "'></i>";
									}
								}

								// Use "echo" to write the table HTML row-by-row.
								echo "<tr>" . 
									"<td >{$sid}&nbsp;{$supplink}</td>" . 
									"<td>{$protocol}</td>" . 
									"<td style=\"overflow: hidden; text-overflow: ellipsis;\" nowrap><span title=\"{$rule_content[2]}\">{$source}</span></td>" . 
									"<td style=\"overflow: hidden; text-overflow: ellipsis;\" nowrap><span title=\"{$rule_content[5]}\">{$destination}</span></td>" . 
									"<td style=\"word-wrap:break-word; word-break:normal;\">{$flowbits}</td>" . 
									"<td style=\"word-wrap:break-word; word-break:normal;\">{$message}</td>" . 
								"</tr>";
								$count++;
							}
						}
						unset($rulem, $v);
					?>
				</tbody>
			</table>
		</div>
	</div>
</div>
</form>

<script type="text/javascript">
//<![CDATA[

	//-- This function stuffs the passed GID, SID and other values into
	//-- hidden Form Fields and posts the form.
	function doAddSuppress(rulegid,rulesid) {
		$('#sid').val(rulesid);
		$('#gid').val(rulegid);
		$('#iform').append('<input type="hidden" name="addsuppress" id="addsuppress" value="true">');
		$('#iform').submit();
	}
//]]>
</script>

<?php include("foot.inc"); ?>

