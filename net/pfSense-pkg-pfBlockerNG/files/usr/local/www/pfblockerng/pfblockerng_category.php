<?php
/*
 * pfblockerng_category.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2023 BBcan177@gmail.com
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

require_once('guiconfig.inc');
require_once('globals.inc');
require_once('/usr/local/pkg/pfblockerng/pfblockerng.inc');

global $config, $pfb;
pfb_global();

$action = $gtype = '';
$rowdata = array();
$rowid = 0;

// Called via AJAX (Save page order format/settings)
if (isset($_REQUEST) && isset($_REQUEST['act']) && ($_REQUEST['act'] == 'update')) {
	$_POST = $_REQUEST;
}

if (isset($_GET)) {
	if (isset($_GET['savemsg']) && !empty($_GET['savemsg'])) {
		$savemsg = htmlspecialchars($_GET['savemsg']);
	}
	if (isset($_GET['rowid']) && !empty($_GET['rowid'])) {
		$temp_value = pfb_filter($_GET['rowid'], PFB_FILTER_NUM, 'Category');
                if (!empty($temp_value)) {
			$rowid = $temp_value ?: 0;
		}
	}
	if (isset($_GET['type']) && !empty($_GET['type'])) {
		$temp_value = pfb_filter($_GET['type'], PFB_FILTER_HTML, 'Category');
		if (in_array($temp_value, array('ipv4', 'ipv6', 'geoip', 'dnsbl'))) {
			$gtype = $temp_value;
		}
	}
}

if (isset($_POST)) {
	if (isset($_POST['savemsg']) && !empty($_POST['savemsg'])) {
		$savemsg = htmlspecialchars($_POST['savemsg']);
	}
	if (isset($_POST['rowid']) && !empty($_POST['rowid'])) {
		$temp_value = pfb_filter($_POST['rowid'], PFB_FILTER_NUM, 'Category');
		if (!empty($temp_value)) {
			$rowid = $temp_value ?: 0;
		}
	}
	if (isset($_POST['type']) && !empty($_POST['type'])) {
		$temp_value = pfb_filter($_POST['type'], PFB_FILTER_HTML, 'Category');
		if (in_array($temp_value, array('ipv4', 'ipv6', 'geoip', 'dnsbl'))) {
			$gtype = $temp_value;
		}
	}

	// AJAX request
	if (isset($_POST['postdata']) && !empty($_POST['postdata'])) {
		parse_str($_POST['postdata'], $post_data);
	}
	if (isset($_POST['ids']) && !empty($_POST['ids'])) {
		parse_str($_POST['ids'], $post_ids);
	}
	if (isset($_POST['act']) && !empty($_POST['act'])) {
		if ($_POST['act'] == 'del') {
			$action = 'del';
		} elseif ($_POST['act'] == 'update') {
			$action = 'update';
		}
	}
}

// Set 'active' GUI Tabs
$active = array('ip' => FALSE, 'ipv4' => FALSE, 'ipv6' => FALSE, 'dnsbl' => FALSE, 'geoip' => FALSE);

switch ($gtype) {
	case 'ipv4':
		$type		= 'IPv4';
		$conf_type	= 'pfblockernglistsv4';
		$active		= array('ip' => TRUE, 'ipv4' => TRUE, 'ipv6' => FALSE, 'dnsbl' => FALSE, 'geoip' => FALSE);
		break;
	case 'ipv6':
		$type		= 'IPv6';
		$conf_type	= 'pfblockernglistsv6';
		$active		= array('ip' => TRUE, 'ipv4' => FALSE, 'ipv6' => TRUE, 'dnsbl' => FALSE, 'geoip' => FALSE);
		break;
	case 'geoip':
		$type		= 'GeoIP';
		$active		= array('ip' => TRUE, 'ipv4' => FALSE, 'ipv6' => FALSE, 'dnsbl' => FALSE, 'geoip' => TRUE);
		break;
	case 'dnsbl':
	default:
		$gtype		= 'dnsbl';
		$type		= 'DNSBL Groups';
		$conf_type	= 'pfblockerngdnsbl';
		$active		= array('ip' => FALSE, 'ipv4' => FALSE, 'ipv6' => FALSE, 'dnsbl' => TRUE, 'geoip' => FALSE);
		break;
}

// Collect rowdata
if ($type != 'GeoIP') {
	init_config_arr(array('installedpackages', $conf_type, 'config'));
	$rowdata = &$config['installedpackages'][$conf_type]['config'];
} else {

	// Collect GeoIP rowdata
	foreach ($pfb['continents'] as $continent => $pfb_alias) {
		if (isset($config['installedpackages']['pfblockerng' . strtolower(str_replace(' ', '', $continent))]['config'])) {
			$continent_config = $config['installedpackages']['pfblockerng' . strtolower(str_replace(' ', '', $continent))]['config'];
		}
		else {
			$continent_config			= array();
			$continent_config[0]			= array();
			$continent_config[0]['action']		= 'Disabled';
			$continent_config[0]['cron']		= 'Never';
			$continent_config[0]['aliaslog']	= 'enabled';
		}
		if (!is_array($continent_config[0])) {
			$continent_config[0] = array();
		}
		$continent_config[0]['aliasname']		= $continent;
		$continent_config[0]['filename']		= str_replace(' ', '_', $continent);
		$continent_config[0]['description']		= "GeoIP {$continent}";
		$rowdata = array_merge($rowdata, $continent_config);
	}
}

// Remove any empty '<config></config>' XML tags
if (isset($rowdata[0]) && empty($rowdata[0])) {
	unset($rowdata[0]);
	$rowdata = array_values($rowdata);
	write_config("pfBlockerNG: Removed empty rowdata");
}

if (!empty($action) && isset($gtype) && isset($rowid)) {

	switch ($action) {
		case 'del':
			// Delete Table row (via POST)
			$name = pfb_filter($rowdata[$rowid]['aliasname'], PFB_FILTER_WORD, 'Category');
			if (!empty($name) && isset($rowdata[$rowid])) {
				unset($rowdata[$rowid]);
				write_config("pfBlockerNG: Removed [ {$type} | {$name} ]");
				$savemsg = "Removed [ Type: {$type}, Name: {$name} ]";
			} else {
				$savemsg = "Could not delete [ Type: {$type}, Name: {$name} ], not found";
			}
			header("Location: /pfblockerng/pfblockerng_category.php?type={$gtype}&savemsg={$savemsg}");
			exit;

		case 'update':
			if (isset($input_errors)) {
				unset($input_errors);
			}
			if (is_array($rowdata)) {
				$action_values = array(	'Disabled',
							'Deny_Inbound', 
							'Deny_Outbound',
							'Deny_Both',
							'Permit_Inbound',
							'Permit_Outbound',
							'Permit_Both',
							'Match_Inbound',
							'Match_Outbound',
							'Match_Both',
							'Alias_Deny',
							'Alias_Permit',
							'Alias_Match',
							'Alias_Native',
							'unbound'
							);	

				$cron_values = array(	'Never',
							'01hour',
							'02hours',
							'03hours',
							'04hours',
							'06hours',
							'08hours',
							'12hours',
							'EveryDay',
							'Weekly'
							);

				$aliaslog_values = array('enabled',
							'disabled',
							'disabled_log'
							);

				// Parse POST and save new values
				if (!empty($post_data) && is_array($post_data)) {
					foreach ($post_data as $key => $value) {
						if (strpos($key, '-') !== FALSE) {
							$k_field = explode('-', $key);

							if (count($k_field) != 2) {
								$input_errors[] = "Failed too many fields: " . htmlspecialchars($key);
							}

							// Validate Variable names
							if (in_array($k_field[0], array('action', 'cron', 'aliaslog', 'logging'))) {
								$variable = $k_field[0];
							} else {
								$input_errors[] = "Failed Variable: " . htmlspecialchars($k_field[0]);
							}

							// Validate Rowid
							$temp_value = pfb_filter($k_field[1], PFB_FILTER_NUM, 'Category');
							if (!empty($temp_value) || $k_field[1] == 0) {
								$rowid = $temp_value ?: 0;
							} else {
								$input_errors[] = "Failed Rowid: " . htmlspecialchars($k_field[1]);
							}

							switch ($variable) {
								case 'action':
									if (!in_array($value, $action_values)) {
										$input_errors[] = "Failed Action: " . htmlspecialchars($value);
									}
									break;
								case 'cron':
									if (!in_array($value, $cron_values)) {
										$input_errors[] = "Failed Cron: " . htmlspecialchars($value);
									}
									break;
								case 'aliaslog':
								case 'logging':
									if (!in_array($value, $aliaslog_values)) {
										$input_errors[] = "Failed Aliaslog: " . htmlspecialchars($value);
									}
									break;
								default:
									$input_errors[] = "Failed variable name: " . htmlspecialchars($variable);
							}

							if (!$input_errors) {
								if ($gtype != 'geoip') {
									$rowdata[$rowid][$variable] = pfb_filter($value, PFB_FILTER_HTML, 'Category');
								} else {
									$continent = pfb_filter(strtolower(str_replace(' ', '', $rowdata[$rowid]['aliasname'])), PFB_FILTER_HTML, 'Category');

									init_config_arr(array('installedpackages', 'pfblockerng' . $continent, 'config', 0));
									$config['installedpackages']['pfblockerng' . $continent]['config'][0][$variable] = pfb_filter($value, PFB_FILTER_HTML, 'Category');
								}
							}
						}
					}
				}

				// Save new Table order format (via AJAX)
				if (!empty($post_ids['ids']) && is_array($post_ids['ids'])) {
					$new_rows = array();
					foreach ($post_ids['ids'] as $key => $value) {

						$temp_value = pfb_filter($key, PFB_FILTER_NUM, 'Category');
						if (!empty($temp_value) || $key == 0) {
							$key = $temp_value ?: 0;
						} else {
							$input_errors[] = "IDS Failed " . htmlspecialchars($key);
						}

						$temp_value = pfb_filter(str_replace('r', '', $value), PFB_FILTER_NUM, 'Category');
						if (!empty($temp_value) || $value == 'r0') {
							$rowid = $temp_value ?: 0;
						} else {
							$input_errors[] = "IDS Failed Rowid: " . htmlspecialchars($value);
						}

						if (!$input_errors) {
							$new_rows[$key] = $rowdata[$rowid];
						}
					}

					if (!$input_errors) {
						$rowdata = $new_rows;
					}
				}

				// Save postdata and Table re-ordering
				if (!$input_errors) {
					write_config("pfBlockerNG: Saved page order format/settings for [ {$type} ]");
				} else {
					// return errors to AJAX request
					print(json_encode($input_errors));
				}
			}
	}
	exit;
}

$pgtype = 'IP'; $l_pgtype = 'ip';
$pg_url = '/pfblockerng/pfblockerng_category.php?type=ipv4';

if ($gtype == 'dnsbl') {
	$pgtype = 'DNSBL'; $l_pgtype = 'dnsbl';
	$pg_url = '/pfblockerng/pfblockerng_dnsbl.php';
}

$pgtitle = array(gettext('Firewall'), gettext('pfBlockerNG'), gettext($pgtype), gettext($type));
$pglinks = array('', '/pfblockerng/pfblockerng_general.php', "{$pg_url}", '@self');

include_once('head.inc');

// Define default Alerts Tab href link (Top row)
$get_req = pfb_alerts_default_page();

$tab_array	= array();
$tab_array[]	= array(gettext('General'),	false,			'/pfblockerng/pfblockerng_general.php');
$tab_array[]	= array(gettext('IP'),		$active['ip'],		'/pfblockerng/pfblockerng_ip.php');
$tab_array[]	= array(gettext('DNSBL'),	$active['dnsbl'],	'/pfblockerng/pfblockerng_dnsbl.php');
$tab_array[]	= array(gettext('Update'),	false,			'/pfblockerng/pfblockerng_update.php');
$tab_array[]	= array(gettext('Reports'),	false,			"/pfblockerng/pfblockerng_alerts.php{$get_req}");
$tab_array[]	= array(gettext('Feeds'),	false,			'/pfblockerng/pfblockerng_feeds.php');
$tab_array[]	= array(gettext('Logs'),	false,			'/pfblockerng/pfblockerng_log.php');
$tab_array[]	= array(gettext('Sync'),	false,			'/pfblockerng/pfblockerng_sync.php');
display_top_tabs($tab_array, true);

$tab_array = array();

if ($gtype == 'ipv4' || $gtype == 'ipv6' || $gtype == 'geoip') {
	$tab_array[]	= array(gettext('IPv4'),	$active['ipv4'],	'/pfblockerng/pfblockerng_category.php?type=ipv4');
	$tab_array[]	= array(gettext('IPv6'),	$active['ipv6'],	'/pfblockerng/pfblockerng_category.php?type=ipv6');
	$tab_array[]	= array(gettext('GeoIP'),	$active['geoip'],	'/pfblockerng/pfblockerng_category.php?type=geoip');
	$tab_array[]	= array(gettext('Reputation'),	false,			'/pfblockerng/pfblockerng_reputation.php');
}
else {
	$tab_array[]	= array(gettext('DNSBL Groups'),	$active['dnsbl'],	'/pfblockerng/pfblockerng_category.php?type=dnsbl');
	$tab_array[]	= array(gettext('DNSBL Category'),	false,			'/pfblockerng/pfblockerng_blacklist.php');
	$tab_array[]	= array(gettext('DNSBL SafeSearch'),	false,			'/pfblockerng/pfblockerng_safesearch.php');
}
display_top_tabs($tab_array, true);

if (isset($savemsg)) {
	print_info_box($savemsg, 'success');
}

?>
<form action="pfblockerng_category.php" method="post" name="iform" id="iform">
<div id="savemsg_json" class="alert" role="alert"></div>
<input id="type" name="type" type="hidden" value="<?=$gtype?>"/>
<input type="hidden" name="rowid" id="rowid" value="">
<input type="hidden" name="act" id="act" value="">

<div class="panel panel-default">
	<div class="panel-heading">
		<?php if ($gtype != 'geoip'):
			$pageid = 'pfb_table'; ?>

			<h2 class="panel-title"><?=gettext("{$type} Summary &emsp;&emsp;(Drag to change order)")?></h2>
		<?php else:
			$pageid = 'pfb_table_geoip'; ?>

			<h2 class="panel-title"><?=gettext("{$type} Summary")?></h2>
		<?php endif; ?>
	</div>
	<div id="<?=$pageid;?>" class="panel-body">

		<?php
			// Maxmind License Key verification
			if ($gtype == 'geoip') {
				$maxmind_verify = TRUE;
				if (empty($pfb['maxmind_key'])) {
					$maxmind_verify = FALSE;
					print_callout('<br /><p><strong>'
							. 'MaxMind now requires a License Key! Review the IP tab: MaxMind settings for more information.'
							. '</strong></p><br />', 'warning', '');
				}
			}
		?>

		<div class="table-responsive">
		<table id="<?=$pageid;?>" class="table table-striped table-hover table-compact sortable-theme-bootstrap table-rowdblclickedit" data-sortable>
			<thead>
				<tr id="pfb_header">
					<th><?=gettext('Name');?></th>
					<th><?=gettext('Description');?></th>
					<th><?=gettext('Action');?></th>
					<?php if ($gtype != 'geoip'): ?>
					<th><?=gettext('Frequency');?></th>
					<?php endif; ?>
					<?php if ($gtype == 'dnsbl'): ?>
						<th><?=gettext('Logging/Blocking Mode');?></th>
					<?php else: ?>
						<th><?=gettext('Logging');?></th>
					<?php endif; ?>
					<th><!----- Buttons -----></th>
				</tr>
			</thead>
			<tbody>

				<?php if (!empty($rowdata) && !empty($rowdata[0])):
					foreach ($rowdata as $r_id => $row): ?>

				<tr style="vertical-align: top" class="sortable" id="pfb_r<?=$r_id;?>">
					<td>
					<?php
						$row['aliasname'] = htmlspecialchars($row['aliasname']);
						if (strlen($row['aliasname']) >= 20) {
							print ("<p title=\"{$row['aliasname']}\">" . substr($row['aliasname'], 0, 15) . '...</p>');
						} else {
							print ($row['aliasname']);
						}
					?>
					</td>

					<td>
					<?php
						$row['description'] = htmlspecialchars($row['description']);
						if (strlen($row['description']) >= 20) {
							print ("<p title=\"{$row['description']}\">" . substr($row['description'], 0, 15) . '...</p>');
						} else {
							print ($row['description']);
						}
					?>
					</td>

					<td>
					<?php
						if ($gtype == 'ipv4' || $gtype == 'ipv6' || $gtype == 'geoip') {
							$list_array = array(	'Disabled' => 'Disabled', 'Deny_Inbound' => 'Deny Inbound',
										'Deny_Outbound' => 'Deny Outbound', 'Deny_Both' => 'Deny Both',
										'Permit_Inbound' => 'Permit Inbound', 'Permit_Outbound' => 'Permit Outbound',
										'Permit_Both' => 'Permit Both', 'Match_Inbound' => 'Match Inbound',
										'Match_Outbound' => 'Match Outbound', 'Match_Both' => 'Match Both',
										'Alias_Deny' => 'Alias Deny', 'Alias_Permit' => 'Alias Permit',
										'Alias_Match' => 'Alias Match', 'Alias_Native' => 'Alias Native' );
						} else {
							$list_array = array(	'Disabled' => 'Disabled', 'unbound' => 'Unbound' );
						}

						$selectadd = new Form_Select(
								'action-' . $r_id,
								'List Action',
								$rowdata[$r_id]['action'],
								$list_array
						);
						$selectadd->setWidth(8)->setAttribute('style', 'width: auto');
						print ($selectadd);
					?>
					</td>

					<?php if ($gtype != 'geoip'): ?>

					<td>
					<?php
						$selectadd = new Form_Select(
								'cron-' . $r_id,
								'Update Frequency',
								$rowdata[$r_id]['cron'],
								[	'Never' => 'Never', '01hour' => 'Every hour', '02hours' => 'Every 2 hours',
									'03hours' => 'Every 3 hours', '04hours' => 'Every 4 hours',
									'06hours' => 'Every 6 hours', '08hours' => 'Every 8 hours',
									'12hours' => 'Every 12 hours', 'EveryDay' => 'Once a day',
									'Weekly' => 'Weekly'
								]
						);
						$selectadd->setWidth(8)->setAttribute('style', 'width: auto');
						print ($selectadd);
					?>
					</td>

					<?php endif; ?>

					<td>
					<?php
						if ($gtype == 'ipv4' || $gtype == 'ipv6' || $gtype == 'geoip') {
							$field = 'aliaslog-' . $r_id;
							$logtype = $rowdata[$r_id]['aliaslog'];
						} else {
							$field = 'logging-' . $r_id;
							$logtype = $rowdata[$r_id]['logging'];
						}

						$log_error = '';
						if ($gtype == 'dnsbl') {
							if ($pfb['dnsbl_py_blacklist']) {
								$log_options = ['enabled'	=> 'DNSBL WebServer/VIP',
										'disabled'	=> 'Null Block (no logging)',
										'disabled_log'	=> 'Null Block (logging)'];
							} else {
								$log_options = ['enabled'	=> 'DNSBL WebServer/VIP',
										'disabled'	=> 'Null Block (no logging)'];
							}

							// Global DNSBL Logging/Blocking mode
							if (!empty($pfb['dnsbl_global_log'])) {
								if (!$pfb['dnsbl_py_blacklist'] && $pfb['dnsbl_global_log'] == 'disabled_log') {
									$logtype		= 'enabled';
									$log_error		= "Global Log 'Null Block (logging)' not available in Unbound Mode."
												. " Re-configure Global Log option!";
								} else {
									$logtype		= $pfb['dnsbl_global_log'];
									$log_options[$logtype]	= "{$log_options[$logtype]} (Global)";
								}
							}
						}
						else {
							$log_options = [ 'enabled' => 'Enabled', 'disabled' => 'Disabled' ];
						}

						$selectadd = new Form_Select(
								$field,
								'Logging/Blocking Mode',
								$logtype,
								$log_options
						);
						$selectadd->setWidth(8)->setAttribute('style', 'width: auto')
							  ->setHelp($log_error);
						print ($selectadd);
					?>
					</td>

					<td>
					<?php if ($gtype != 'geoip'): ?>
						<a href="/pfblockerng/pfblockerng_category_edit.php?type=<?=$gtype?>&rowid=<?=$r_id?>">
							<i class="fa-solid fa-pencil" alt="edit"></i>
						</a>
						<i class="fa-solid fa-trash-can icon-pointer no-confirm"
							title="<?=gettext('Delete selected entry') . ' [ ' . $row['aliasname'] .' ] ?' ?>"
							onclick="$('#rowid').val('<?=$r_id?>');$('#act').val('del');pfb_rownamedelete();">
						</i>

						<?php
							// Add href anchor link to CustomList if defined
							if (!empty($rowdata[$r_id]['custom'])):
						?>

							<a href="/pfblockerng/pfblockerng_category_edit.php?type=<?=$gtype?>&rowid=<?=$r_id?>#Customlist"
								title="Quick link to Custom List">
								<i class="fa-solid fa-anchor" alt="edit"></i>
								</a>
							<?php endif; ?>

						<?php
							if ($gtype == 'dnsbl' && isset($row['order']) && $row['order'] == 'primary'):
						?>
							<i class="fa-regular fa-square-check" style="cursor: default" title="DNSBL Primary Group order defined"></i>
							<?php endif; ?>

					<?php elseif ($maxmind_verify && file_exists("/usr/local/www/pfblockerng/pfblockerng_{$row['filename']}.php")): ?>
						<a href="/pfblockerng/pfblockerng_<?=$row['filename'];?>.php">
							<i class="fa-solid fa-pencil" alt="edit"></i>
						</a>
					<?php endif; ?>

					</td>
				</tr>
					<?php endforeach; ?>
				<?php else: $r_id = -1; ?>

				<tr>
					<td>
						No Alias/Groups are defined.
						<br />Click <strong>Add</strong> to define a new Alias/Group.
						<br /><br /><strong>Note</strong>: Pre-defined Alias/Groups are available in the Feeds Tab.
					</td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>
		</div>
	</div>
	<nav class="action-buttons">
		<?php if ($gtype != 'geoip'): ?>
		<a href="/pfblockerng/pfblockerng_category_edit.php?type=<?=$gtype?>&rowid=<?=$r_id +1?>" class="btn btn-sm btn-success">
			<i class="fa-solid fa-plus icon-embed-btn"></i>
			<?=gettext('Add')?>
		</a>
		<?php endif; ?>
		<button class="btn btn-sm btn-primary" type="button" id="btnsave" title="Save the page 'Order' format">
			<i class="fa-solid fa-save icon-embed-btn"></i>
			<?=gettext('Save')?>
		</button>&emsp;
	</nav>
</div>

<?php
if ($gtype == 'geoip') {
	print_callout('GeoIP database GeoLite2 distributed under the Creative Commons Attribution-ShareAlike 4.0 International License by:
			<a target="_blank" href="https://www.maxmind.com">MaxMind Inc.</a><br /><br />
			The GeoIP database is automatically updated the first Tuesday of each month.<br />
			(To avoid any MaxMind update delays, update is now scheduled for the first Thursday of each month.)<br /><br />

			<span class="text-danger"><strong>Note:&emsp;</strong></span>
			pfSense by default implicitly blocks all unsolicited inbound traffic to the WAN interface.<br />
			Therefore adding GeoIP based firewall rules to the WAN will <strong>not</strong> provide any benefit, unless there are
			open WAN ports.<br /><br />
			Its also <strong>not</strong> recommended to block the "world", instead consider rules to "Permit" traffic to/from
			selected Countries only.<br />
			Also consider protecting just the specific open WAN ports and its just as important to protect the outbound LAN traffic.<br /><br />
			Country ISOs can also be defined in the IPv4/6 Tabs (Refer to blue infoblocks for more details)<br /><br />
			<strong>Setting changes are applied via CRON or \'Force Update|Reload\' only!</strong></p>');
}
elseif ($gtype == 'dnsbl') {
	print_callout('<p><strong>Setting changes are applied via CRON or \'Force Update|Reload\' only!</strong><br /><br />
			DNSBL Category feeds are processed first, followed by the DNSBL Groups.<br />
			DNSBL Groups can be prioritized first, by selecting the \'Group Order\' option.</p>');
}
else {
	print_callout('<p><strong>Setting changes are applied via CRON or \'Force Update|Reload\' only!</strong></p>');
}
?>
</form>

<script type="text/javascript">
//<![CDATA[

var pagetype = null;

function pfb_rownamedelete() {
	if (confirm('Delete selected entry?')) {
		$('form').submit();
	}
}

events.push(function() {

	function save_new_changes() {
		var gtype = "<?=$gtype?>";
		if ($('#pfb_table table tbody').length == 0) {
			var ids = '';
		} else {
			var ids = $('#pfb_table table tbody').sortable('serialize', {key:"ids[]"});
		}
		var postdata = $('#iform').serialize();

		if (confirm("<?=gettext("Save settings and/or page 'Order' changes?")?>")) {

			ajaxRequest = $.ajax(
				{
					type: 'post',
					url: '/pfblockerng/pfblockerng_category.php',
					data: {
						rowid: '0',
						act: 'update',
						type: gtype,
						ids: ids,
						postdata: postdata
					}
				}
			);

			// Deal with the results of the above ajax call
			ajaxRequest.done(function (response, textStatus, jqXHR) {
				if (response == '') {
					$('form').submit();
				} else {
					$('#savemsg_json').show();
					$('#savemsg_json').addClass("alert-danger")
					var json = new Object;
					json = jQuery.parseJSON(response)
					output = 'Could not save, Errors Found:<br />';
					$.each(json, function(key, value) {
						output += value + "<br />"  
					});
					$('#savemsg_json').html(output);
					var scrollToEl = document.getElementById('topmenu');
					scrollToEl.scrollIntoView(true);
				}
			});
		}
	}

	// Move line (User mouse drag)
	$('#pfb_table table tbody').sortable({
		items: 'tr.sortable',
		cursor: 'move',
		distance: 10,
		opacity: 0.8,
		helper: function(e, ui) {
			ui.children().each(function() {
				$(this).width($(this).width());
			});
			return ui;
			},
	});

	$('#savemsg_json').hide();
	$('#btnsave').click(function() {
		save_new_changes();
		$('#savemsg_json').hide();
	});
});

//]]>
</script>
<script src="pfBlockerNG.js" type="text/javascript"></script>
<?php include('foot.inc');?>
