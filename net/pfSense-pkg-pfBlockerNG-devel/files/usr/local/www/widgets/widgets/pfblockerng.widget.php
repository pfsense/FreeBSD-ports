<?php
/*
 * pfblockerng.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2024 BBcan177@gmail.com
 * All rights reserved.
 *
 * Originally based Upon pfBlocker
 * Copyright (c) 2011 Thomas Schaefer
 * Copyright (c) 2011 Marcello Coutinho
 * All rights reserved.
 *
 * Adapted From snort_alerts.widget.php
 * Copyright (c) 2016 Bill Meeks
 * All rights reserved.
 *
 * Javascript and Integration modifications by J. Nieuwenhuizen and J. Van Breedam
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

$nocsrf = true;
@require_once('/usr/local/www/widgets/include/widget-pfblockerng.inc');
@require_once('/usr/local/pkg/pfblockerng/pfblockerng.inc');
@require_once('guiconfig.inc');

pfb_global();

// Image source definition
$pfb['down']	= '<i class="fa-solid fa-turn-down" title="No Rules are Defined using this Alias"></i>';
$pfb['up']	= '<i class="fa-solid fa-turn-up text-success" title="Rules are Defined using this Alias (# of fw rules defined)"></i>';
$pfb['err']	= '<i class="fa-solid fa-minus-circle text-danger" title="pf Errors found."></i>';

// Widget customizations
$wglobal_array = array ('popup' => 'off', 'sortcolumn' => 'none', 'sortmix' => 'off', 'sortdir' => 'asc', 'dnsblquery' => 5,
			'maxfails' => 3, 'maxheight' => 2500, 'clearip' => 'never', 'cleardnsbl' => 'never');

$pfb['wglobal'] = config_get_path('installedpackages/pfblockerngglobal', []);
foreach ($wglobal_array as $type => $value) {
	$pfb[$type] = $pfb['wglobal']['widget-' . "{$type}"] ?: $value;
}

if ($_GET) {

	// Called by Ajax to update failed download contents
	if ($_GET['getNewFailed']) {
		pfBlockerNG_get_failed();
		return;
	}

	// Called by Ajax to update widget contents
	elseif ($_GET['getNewWidget']) {
		$pfb_table = pfBlockerNG_get_header('js');
		pfBlockerNG_get_table('js', $pfb_table);
		return;
	}
}

if ($_POST) {

	// Save widget customizations
	if (isset($_POST['pfb_submit'])) {
		$pfb['wglobal']['widget-popup']			= pfb_filter($_POST['pfb_popup'], PFB_FILTER_ON_OFF, 'widget');
		$pfb['wglobal']['widget-sortmix']		= pfb_filter($_POST['pfb_sortmix'], PFB_FILTER_ON_OFF, 'widget');
		config_set_path('installedpackages/pfblockerngglobal/widget-popup', $pfb['wglobal']['widget-popup']);
		config_set_path('installedpackages/pfblockerngglobal/widget-sortmix', $pfb['wglobal']['widget-sortmix']);

		if (in_array($_POST['pfb_sortcolumn'], array('none', 'alias', 'count', 'packets', 'update'))) {
			$pfb['wglobal']['widget-sortcolumn']	= $_POST['pfb_sortcolumn'];
			config_set_path('installedpackages/pfblockerngglobal/widget-sortcolumn', $pfb['wglobal']['widget-sortcolumn']);
		}
		if (in_array($_POST['pfb_sortdir'], array('asc', 'des'))) {
			$pfb['wglobal']['widget-sortdir']	= $_POST['pfb_sortdir'];
			config_set_path('installedpackages/pfblockerngglobal/widget-sortdir', $pfb['wglobal']['widget-sortdir']);
		}
		if (in_array($_POST['pfb_clearip'], array('never', 'daily', 'weekly'))) {
			$pfb['wglobal']['widget-clearip']	= $_POST['pfb_clearip'];
			config_set_path('installedpackages/pfblockerngglobal/widget-clearip', $pfb['wglobal']['widget-clearip']);
		}
		if (in_array($_POST['pfb_cleardnsbl'], array('never', 'daily', 'weekly'))) {
			$pfb['wglobal']['widget-cleardnsbl']	= $_POST['pfb_cleardnsbl'];
			config_set_path('installedpackages/pfblockerngglobal/widget-cleardnsbl', $pfb['wglobal']['widget-cleardnsbl']);
		}
		if (is_numeric($_POST['pfb_dnsblquery']) && $_POST['pfb_dnsblquery'] < 10000) {
			$pfb['wglobal']['widget-dnsblquery']	= $_POST['pfb_dnsblquery'];
			config_set_path('installedpackages/pfblockerngglobal/widget-dnsblquery', $pfb['wglobal']['widget-dnsblquery']);

			// Restart pfb_dnsbl service on Query frequency changes
			if ($_POST['pfb_dnsblquery'] != $pfb['dnsblquery']) {
				restart_service('pfb_dnsbl');
			}
		}
		if (is_numeric($_POST['pfb_maxfails']) && $_POST['pfb_maxfails'] < 100) {
			$pfb['wglobal']['widget-maxfails']	= $_POST['pfb_maxfails'];
			config_set_path('installedpackages/pfblockerngglobal/widget-maxfails', $pfb['wglobal']['widget-maxfails']);
		}
		if (is_numeric($_POST['pfb_maxheight']) && $_POST['pfb_maxheight'] < 10000) {
			$pfb['wglobal']['widget-maxheight']	= $_POST['pfb_maxheight'];
			config_set_path('installedpackages/pfblockerngglobal/widget-maxheight', $pfb['wglobal']['widget-maxheight']);
		}

		// Define pfBlockerNG clear [ dnsbl and/or IP ] counter CRON job
		foreach (array( 'clearip', 'cleardnsbl') as $type) {
			$pfb_cmd = "/usr/local/bin/php /usr/local/www/pfblockerng/pfblockerng.php {$type} >/dev/null 2>&1";
			if (isset($pfb['wglobal']['widget-' . $type])) {
				if ($pfb['wglobal']['widget-' . $type] != 'never') {

					$pfb_day = '*';
					if ($pfb['wglobal']['widget-' . $type] == 'weekly') {
						$pfb_day = '7';
					}

					// Remove unreferenced 'daily' or 'weekly' cron job
					$pfb_other = ($pfb_day == '*') ? '7' : '*';
					if (pfblockerng_cron_exists($pfb_cmd, '0', '0', '*', $pfb_other)) {
						install_cron_job("pfblockerng.php {$type}", false);
					}

					if (!pfblockerng_cron_exists($pfb_cmd, '0', '0', '*', $pfb_day)) {
						install_cron_job($pfb_cmd, true, '0', '0', '*', '*', $pfb_day, 'root');
					}
				}
				else {
					if (pfblockerng_cron_exists($pfb_cmd, '0', '0', '*', '*')) {
						install_cron_job("pfblockerng.php {$type}", false);
					}
					if (pfblockerng_cron_exists($pfb_cmd, '0', '0', '*', '7')) {
						install_cron_job("pfblockerng.php {$type}", false);
					}
				}
			}
			else {
				if (pfblockerng_cron_exists($pfb_cmd, '0', '0', '*', '*')) {
					install_cron_job("pfblockerng.php {$type}", false);
				}
				if (pfblockerng_cron_exists($pfb_cmd, '0', '0', '*', '7')) {
					install_cron_job("pfblockerng.php {$type}", false);
				}
			}
		}

		// Remove old settings
		if (isset($pfb['wglobal']['widget-maxpivot'])) {
			unset($pfb['wglobal']['widget-maxpivot']);
			config_del_path('installedpackages/pfblockerngglobal/widget-maxpivot');
		}

		write_config('pfBlockerNG: Saved Widget customizations via Dashboard');
		header("Location: /");
		exit(0);
	}

	// Clear widget Failed downloads
	elseif ($_POST['pfblockerngack']) {
		exec("{$pfb['sed']} -i '' 's/FAIL/Fail/g' /var/log/pfblockerng/error.log");
		header("Location: /");
		exit(0);
	}

	// Clear widget IP/DNSBL Packet Counts
	elseif ($_POST['pfblockerngclearall']) {
		pfBlockerNG_clearip();
		pfBlockerNG_clearsqlite('clearip');
		pfBlockerNG_clearsqlite('cleardnsbl');
		header("Location: /");
		exit(0);
	}

	// Clear widget IP Packet Counts
	elseif ($_POST['pfblockerngclearip']) {
		pfBlockerNG_clearip();
		pfBlockerNG_clearsqlite('clearip');
		header("Location: /");
		exit(0);
	}

	// Clear widget DNSBL Packet Counts
	elseif ($_POST['pfblockerngcleardnsbl']) {
		pfBlockerNG_clearsqlite('cleardnsbl');
		header("Location: /");
		exit(0);
	}
}


// Sort widget table according to user configuration
function pfbsort(&$array, $subkey, $sort_ascending) {
	if (empty($array)) {
		return;
	}

	if (count($array)) {
		$temp_array[key($array)] = array_shift($array);
	}

	foreach ($array as $key => $val) {
		$offset = 0;
		$found = FALSE;

		foreach ($temp_array as $tmp_key => $tmp_val) {
			if (!$found) {
				switch($subkey) {
					case 'alias':
						(strtolower($key) > strtolower($tmp_key)) ? $found = TRUE : $found = FALSE;
						break;
					case 'update':
						(strtotime($val[$subkey]) > strtotime($tmp_val[$subkey])) ? $found = TRUE : $found = FALSE;
						break;
					default:
						(strtolower($val[$subkey]) > strtolower($tmp_val[$subkey])) ? $found = TRUE : $found = FALSE;
						break;
				}
			}
			if ($found) {
				$temp_array = array_merge((array)array_slice($temp_array, 0, $offset), array($key => $val), array_slice($temp_array, $offset));
			}
			$offset++;
		}

		if (!$found) {
			$temp_array = array_merge($temp_array, array($key => $val));
		}
	}

	if (!$sort_ascending) {
		$array = array_reverse($temp_array);
	} else {
		$array = $temp_array;
	}
	return;
}


// Collect all pfBlockerNG statistics
function pfBlockerNG_update_table() {
	global $pfb;
	$pfb_table = $pfb_dtable = array();
	$pfb['pfctlerr'] = FALSE;

	/* Alias Table Definitions -	'update'	- Last Updated Timestamp
					'rule'		- Total number of Firewall rules per alias
					'count'		- Total Line Count per alias
					'packets'	- Total number of pf packets per alias
					'type'		- Rule type - block|reject|pass|match
					'id'		- Alias key value				*/

	exec("{$pfb['pfctl']} -vvsTables | {$pfb['grep']} -A4 'pfB_'", $pfb_pfctl);
	if (!empty($pfb_pfctl)) {
		foreach($pfb_pfctl as $line) {
			$line = trim(str_replace(array( '[', ']' ), '', $line));
			if (substr($line, 0, 1) == '-') {
				$pfb_alias = trim(strstr($line, 'pfB', FALSE));
				if (empty($pfb_alias) || $pfb_alias == 'pfB_DNSBL_VIPs') {
					unset($pfb_alias);
					continue;
				}

				$pfb_aliasdir_esc = escapeshellarg("{$pfb['aliasdir']}/{$pfb_alias}.txt");
				exec("{$pfb['grep']} -cv '^1\.1\.1\.1\$' {$pfb_aliasdir_esc}", $match);
				if (!is_numeric($match[1])) {
					$match[1] = 0;
				}
				$pfb_table[$pfb_alias] = array('count' => $match[1], 'img' => $pfb['down']);
				exec("{$pfb['ls']} -l -D'%b %d %T' {$pfb_aliasdir_esc} | {$pfb['awk']} '{ print \$6,\$7,\$8 }'", $update);

				$pfb_table[$pfb_alias]['update']	= htmlspecialchars($update[0]);
				$pfb_table[$pfb_alias]['rule']		= 0;
				$pfb_table[$pfb_alias]['packets']	= 0;
				unset($match, $update);
				continue;
			}

			if (isset($pfb_alias)) {
				if (substr($line, 0, 9) == 'Addresses') {
					$addr = trim(substr(strrchr($line, ':'), 1));
					if (!is_numeric($addr)) {
						$addr = 0;
					}
					$pfb_table[$pfb_alias]['count'] = $addr;
					continue;
				}
				unset($pfb_alias);
			}
		}
	}
	else {
		// Error. No pf labels found.
		$pfb['pfctlerr'] = TRUE;
	}

	// Determine if firewall rules are defined
	$pfb_filter_rules = config_get_path('filter/rule', []);

	if (!empty($pfb_filter_rules)) {
		$tracked_rules = array();

		// Get the relevant pfB rules
		foreach ($pfb_filter_rules as $rule) {
			if (strpos($rule['descr'], 'pfB_DNSBL_Ping') !== FALSE || strpos($rule['descr'], 'pfB_DNSBL_Permit') !== FALSE) {
				continue;
			}

			$tracked_rules[$rule['tracker']] = array('packets' => 0);

			if (isset($rule['source']['address']) && stripos($rule['source']['address'], 'pfb_') !== FALSE) {
				$tracked_rules[$rule['tracker']]['source'] = $rule['source']['address'];
				if (!isset($rule['disabled'])) {
					$pfb_table[$rule['source']['address']]['img'] = $pfb['up'];
					$pfb_table[$rule['source']['address']]['rule'] += 1;
					if (!isset($pfb_table[$rule['source']['address']]['packets'])) {
						$pfb_table[$rule['source']['address']]['packets'] = 0;
					}
					$pfb_table[$rule['source']['address']]['type'] = ucfirst($rule['type']) ?: 'unknown';
				}
			}
			
			if (isset($rule['destination']['address']) && stripos($rule['destination']['address'], 'pfb_') !== FALSE) {
				$tracked_rules[$rule['tracker']]['destination'] = $rule['destination']['address'];
				if (!isset($rule['disabled'])) {
					$pfb_table[$rule['destination']['address']]['img'] = $pfb['up'];
					$pfb_table[$rule['destination']['address']]['rule'] += 1;
					if (!isset($pfb_table[$rule['destination']['address']]['packets'])) {
						$pfb_table[$rule['destination']['address']]['packets'] = 0;
					}
					$pfb_table[$rule['destination']['address']]['type'] = ucfirst($rule['type']) ?: 'unknown';
				}
			}
		}

		// Get the packet count for each pfB rule
		if (!empty($tracked_rules)) {
			foreach (pfSense_get_pf_rules() as $prule) {

				// prule may be an error string if pftcl_get_rule() returned an error
				if (!is_array($prule)) {
					continue;
				}

				if (isset($prule['tracker'])) {
					$prule_id = $prule['tracker'];
					if (isset($tracked_rules[$prule_id])) {
						if (isset($prule['packets']) && $prule['packets'] > 0) {
							if (isset($tracked_rules[$prule_id]['source'])) {
								$pfb_table[$tracked_rules[$prule_id]['source']]['packets'] += $prule['packets'];
							}
							if (isset($tracked_rules[$prule_id]['destination'])) {
								$pfb_table[$tracked_rules[$prule_id]['destination']]['packets'] += $prule['packets'];
							}
						}
					}
				}
			}
		}
	}

	// Collect pfB Alias ID for popup
	foreach (config_get_path('aliases/alias', []) as $key => $alias) {
		if (isset($pfb_table[$alias['name']])) {
			$pfb_table[$alias['name']]['id'] = $key;
		}
	}

	// DNSBL collect statistics
	if ($pfb['enable'] == 'on' && $pfb['dnsbl'] == 'on') {

		$pfb['dnsbl_missing'] = TRUE;	// Flag to indicate error message to user in widget
		$db_handle = pfb_open_sqlite(1, 'Widget stats');
		if ($db_handle) {
			$result = $db_handle->query("SELECT * FROM dnsbl;");
			if ($result) {
				while ($res = $result->fetchArray(SQLITE3_ASSOC)) {

					if ($res['entries'] == 'disabled') {
						$pfb_dtable[$res['groupname']] = array ('count' => 'disabled', 'img' => $pfb['down']);
					} else {
						if (!is_numeric($res['entries'])) {
							$res['entries'] = 0;
						}
						if (!empty(pfb_filter($res['groupname'], PFB_FILTER_WORD, 'widget'))) {
							$pfb_dtable[$res['groupname']] = array ('count' => $res['entries'], 'img' => $pfb['up']);
						}
					}
					$pfb_dtable[$res['groupname']]['update'] = htmlspecialchars("{$res['timestamp']}");

					if (!is_numeric($res['counter'])) {
						$res['counter'] = 0;
					}

					$pfb_dtable[$res['groupname']]['packets']= "{$res['counter']}";
					$pfb_dtable[$res['groupname']]['type']   = 'DNSBL';

					unset($pfb['dnsbl_missing']);
				}
			}
		}
		pfb_close_sqlite($db_handle);
	}

	// Sort tables per sort customization
	if ($pfb['sortcolumn'] != 'none') {
		if ($pfb['sortdir'] == 'asc') {
			if ($pfb['sortmix'] == 'on') {
				$pfb_table = array_merge($pfb_table, $pfb_dtable);
				pfbsort($pfb_table, $pfb['sortcolumn'], FALSE);
			} else {
				pfbsort($pfb_table, $pfb['sortcolumn'], FALSE);
				pfbsort($pfb_dtable, $pfb['sortcolumn'], FALSE);
			}
		} else {
			if ($pfb['sortmix'] == 'on') {
				$pfb_table = array_merge($pfb_table, $pfb_dtable);
				pfbsort($pfb_table, $pfb['sortcolumn'], TRUE);
			} else {
				pfbsort($pfb_table, $pfb['sortcolumn'], TRUE);
				pfbsort($pfb_dtable, $pfb['sortcolumn'], TRUE);
			}
		}
	}

	if ($pfb['sortcolumn'] == 'none' || $pfb['sortmix'] == 'off') {
		$pfb_table = array_merge($pfb_table, $pfb_dtable);
	}
	return $pfb_table;
}


// Called on initial load and Ajax to update Failed download contents (Create href to Alias/Group editor)
function pfBlockerNG_get_failed() {
	global $pfb;
	$response = '';

	// Collect any failed downloads
	$today_date = date('m/j/y', time());
	exec("{$pfb['grep']} 'FAIL' {$pfb['errlog']} | {$pfb['grep']} {$today_date}", $results);
	$results = array_reverse($results);

	if (!empty($results)) {

		$list_type = array( 'pfblockernglistsv4' => 'ipv4', 'pfblockernglistsv6' => 'ipv6', 'pfblockerngdnsbl' => 'dnsbl' );
		$emheight = ($pfb['maxfails'] * 1.37) + 0.1;
		$response .= "\r";
		$response .= "<ol style=\"white-space: nowrap; text-overflow: ellipsis; max-height: {$emheight}em; overflow-y: scroll;\"><small>";

		$tab6 = "\t\t\t\t\t";
		$tab7 = "\t\t\t\t\t\t";
		$counter = 1;

		foreach ($results as $result) {
			$result = htmlspecialchars($result);

			if (substr($result, 3, 4) == 'pfB_') {
				$header		= str_replace(' [ pfB_', '', strstr($result, ' - ', TRUE));
				$pfb_prefix	= 'pfB_';
			} else {
				$header		= str_replace(' [ DNSBL_', '', strstr($result, ' - ', TRUE));
				$pfb_prefix	= 'DNSBL_';
			}

			// Remove trailing IP type
			$suffix = substr($header, -3);
			if ($suffix == '_v4' || $suffix == '_v6') {
				$f_alias = substr($header, 0, -3);
			} else {
				$f_alias = $header;
			}

			if (!empty(pfb_filter($f_alias, PFB_FILTER_WORD, 'widget')) && $f_alias != $p_alias) {
				$pfb_found = FALSE;
				foreach ($list_type as $conf_type => $type) {
					foreach (config_get_path("installedpackages/{$conf_type}/config", []) as $key => $alias) {
						if ($alias['aliasname'] == $f_alias) {
							$pfb_found = TRUE;
							break 2;
						}
					}
				}
			}
			else {
				$pfb_found = TRUE;
			}

			if ($pfb_found) {
				$link   = "<a target=\"_blank\" href=\"/pfblockerng/pfblockerng_category_edit.php?type={$type}&act=edit&rowid={$key}\" ";
				$link  .= "\"title=\"Click to view Alias\" >{$pfb_prefix}{$f_alias}</a>";
				$final	= str_replace("{$pfb_prefix}{$f_alias}", $link, $result);
				$p_alias = $f_alias;
			}
			else {
				$final = $result;
				$p_alias = '';
			}

			if ($counter == 1) {
				$response .= "{$tab6}<li>{$final}&emsp;\n{$tab7}<i class=\"fa-regular fa-trash-can icon-pointer\" id=\"pfblockerngackicon\"
						title=\"" . gettext("Clear Failed Downloads") . "\" ></i></li>\n";
			} else {
				$response .= "{$tab6}<li>{$final}</li>\n";
			}
			$counter++;
		}
		$response .= "</small>
				</ol>";
	} else {
		// Print MaxMind version when failed downloads is null
		$maxver = htmlspecialchars( exec("grep -o 'Last-.*' /var/log/pfblockerng/maxmind_ver"));
		$response .= "&emsp;<small>MaxMind: {$maxver}</small>";
	}
	print ($response);
}


// Called on initial load and Ajax to update header contents
function pfBlockerNG_get_header($mode='') {
	global $pfb;
	$response = '';

	$pfb_table = pfBlockerNG_update_table();

	$pfb_table['stats'] = $pfb_table['counts'] = array();
	$pfb_ip_types = array_flip(array('Deny', 'Pass', 'Match'));

	if (!empty($pfb_table)) {
		foreach ($pfb_table as $pfb_alias => $values) {

			if (empty($values)) {
				continue;
			}

			// TODO: Split Deny evaluations into Block and Reject
			if (isset($values['type']) && ($values['type'] == 'Block' || $values['type'] == 'Reject')) {
				$values['type'] = 'Deny';
			}

			if (!isset($values['id'])) {
				if (!isset($pfb_table['stats']['DNSBL'])) {
					$pfb_table['stats']['DNSBL'] = 0;
				}
				if (!isset($pfb_table['counts']['DNSBL'])) {
					$pfb_table['counts']['DNSBL'] = 0;
				}

				if (is_numeric($values['packets'])) {
					$pfb_table['stats']['DNSBL']		+= $values['packets'];
				}
				if (is_numeric($values['count'])) {
					$pfb_table['counts']['DNSBL']		+= $values['count'];
				}
			}
			elseif (isset($values['id']) && isset($values['type']) && isset($pfb_ip_types[$values['type']])) {
				if (!isset($pfb_table['stats'][$values['type']])) {
					$pfb_table['stats'][$values['type']] = 0;
				}

				if (!isset($pfb_table['counts'][$values['type']])) {
					$pfb_table['counts'][$values['type']] = 0;
				}

				if (is_numeric($values['packets'])) {
					$pfb_table['stats'][$values['type']]	+= $values['packets'];
				}
				if (is_numeric($values['count'])) {
					$pfb_table['counts'][$values['type']]	+= $values['count'];
				}
			}
		}
	}

	foreach ($pfb_ip_types as $key => $itype) {
		if (!isset($pfb_table['stats'][$key])) {
			$pfb_table['stats'][$key] = 0;
		}
		if (!isset($pfb_table['counts'][$key])) {
			$pfb_table['counts'][$key] = 0;
		}
	}

	// Status indicator if pfBlockerNG is enabled/disabled
	if ($pfb['enable'] == 'on') {
		$pfb_status	= 'fa-solid fa-check-circle text-success';
		$pfb_msg	= 'pfBlockerNG is Active.';

		// Check Masterfile Database Sanity
		if (isset($pfb['config']['enable_dup']) && $pfb['config']['enable_dup'] == 'on') {
			$db_sanity = exec("{$pfb['grep']} 'Sanity check' {$pfb['logdir']}/pfblockerng.log | tail -1 | {$pfb['grep']} -o 'PASSED'");
			if ($db_sanity != 'PASSED') {
				$pfb_status	= 'fa-solid fa-exclamation-circle text-warning';
				$pfb_msg	= 'pfBlockerNG deDuplication is out of sync. Perform a Force Reload to correct.';
			}
		}
	} else {
		$pfb_status = 'fa-solid fa-times-circle text-danger';
		$pfb_msg = 'pfBlockerNG is Disabled.';
	}

	$unbound_validate = FALSE;
	if (file_exists("{$pfb['dnsbldir']}/unbound.conf")) {
		if ($pfb['dnsbl_mode'] == 'dnsbl_python') {
			$py_mode = '(Python mode)';
			if (strpos(file_get_contents("{$pfb['dnsbldir']}/unbound.conf"), 'pfb_unbound.py') !== FALSE) {
				$unbound_validate = TRUE;
			}
		} else {
			$py_mode = '(Unbound mode)';
			if (strpos(file_get_contents("{$pfb['dnsbldir']}/unbound.conf"), 'pfb_dnsbl') !== FALSE) {
				$unbound_validate = TRUE;
			}
		}
	}

	// Status indicator if DNSBL is actively running
	if ($pfb['enable'] == 'on' && $pfb['dnsbl'] == 'on' && $pfb['unbound_state'] == 'on' && $unbound_validate) {

		// Check DNSBL Database Sanity
		$db_sanity = exec("{$pfb['grep']} 'DNSBL update' {$pfb['logdir']}/pfblockerng.log | tail -1 | {$pfb['grep']} -o 'OUT OF SYNC'");
		if ($db_sanity == 'OUT OF SYNC') {
			$dnsbl_status	= 'fa-solid fa-exclamation-circle text-warning';
			$dnsbl_msg	= "DNSBL {$py_mode} is out of sync. Perform a Force Reload to correct.";
		} else {
			$dnsbl_status	= 'fa-solid fa-check-circle text-success';
			$dnsbl_msg	= "DNSBL {$py_mode} is Active on vip: {$pfb['dnsbl_vip4']} ports: {$pfb['dnsbl_port']} & {$pfb['dnsbl_port_ssl']}";
		}

		// Check for any Python Integration errors
		if ($pfb['dnsbl_mode'] == 'dnsbl_python' && (int)@filesize($pfb['pyerrlog']) > 0) {
			$dnsbl_status	= 'fa-solid fa-exclamation-circle text-warning';
			$dnsbl_msg	= "DNSBL {$py_mode} errors Found! Review py_error.log";
		}
	} else {
		$dnsbl_status		= 'fa-solid fa-times-circle text-danger';

		// Check for any Python Integration errors
		if ($pfb['dnsbl_mode'] == 'dnsbl_python' && (int)@filesize($pfb['pyerrlog']) > 0) {
			$dnsbl_msg = "DNSBL {$py_mode} is Disabled with errors! Review py_error.log";
		} else {
			$dnsbl_msg = "DNSBL {$py_mode} is Disabled.";
		}
	}

	// Collect folder/file counts
	$stats = array();
	$widget_head = 	array ( array (	'Deny'		=> '',
					'Pass'		=> '',
					'Match'		=> '',
					'Suppression'	=> "{$pfb['ipconfig']['v4suppression']}"),

				array (	'DNSBL'		=> '',
					'Queries'	=> '',
					'Percent'	=> '',
					'Whitelist'	=> "{$pfb['dnsblconfig']['suppression']}"));

	foreach ($widget_head as $key => $line) {
		foreach ($line as $type => $config_path) {

			$stats[$key][$type] = 0;
			if ($type == 'DNSBL') {
				if (isset($pfb['dnsbl_missing'])) {
					$stats[$key][$type] = "<span title='*** SQLite database 'pfb_py_dnsbl.sqlite' is missing, Force Reload DNSBL to recover! ***'>Unknown</span>";
				} else {
					$stats[$key][$type] = htmlspecialchars($pfb_table['stats']['DNSBL']);
				}
			}

			elseif ($type == 'Suppression' || $type == 'Whitelist') {

				$gcount = 0;
				foreach (pfbng_text_area_decode($config_path, TRUE, FALSE, TRUE) as $cline) {
					if (substr(trim($cline), 0, 1) != '#' && !empty($cline)) {
						$gcount++;
					}
				}

				if (is_numeric($gcount)) {
					$stats[$key][$type] = number_format( $gcount, 0, '', ',' ) ?: 0;
				} else {
					$stats[$key][$type] = htmlspecialchars($gcount) ?: 0;
				}
			}

			elseif ($type == 'Queries') {
				$resolver	= array();
				$pfb_found	= FALSE;

				$db_handle = pfb_open_sqlite(3, 'Resolver collect queries');
				if ($db_handle) {

					$result = $db_handle->query("SELECT * FROM resolver WHERE row = 0;");
					if ($result) {
						while ($qstats = $result->fetchArray(SQLITE3_ASSOC)) {
							$pfb_found	= TRUE;
							$resolver[]	= $qstats;
						}
					}

					// Create new row
					if (!$pfb_found) {
						$db_update = "INSERT INTO resolver ( row, totalqueries, queries ) VALUES ( 0, 0, 0 );";
						$db_handle->exec("BEGIN TRANSACTION;"
								. "{$db_update}"
								. "END TRANSACTION;");
					}
				}
				pfb_close_sqlite($db_handle);

				if (!is_numeric($resolver[0]['totalqueries'])) {
					$resolver[0]['totalqueries'] = 0;
				}
				if (!is_numeric($resolver[0]['queries'])) {
					$resolver[0]['queries'] = 0;
				}
				$stats[$key][$type] = ($resolver[0]['totalqueries'] ?: 0) + ($resolver[0]['queries'] ?: 0);
			}

			elseif ($type == 'Percent') {
				if (is_numeric($stats[1]['DNSBL']) && $stats[1]['DNSBL'] > 0 && is_numeric($stats[1]['Queries']) && $stats[1]['Queries'] > 0) {
					$stats[$key][$type] = number_format( min( ($stats[1]['DNSBL'] / $stats[1]['Queries']) * 100, 100), 2);
				} else {
					$stats[$key][$type] = 0;
				}
			}

			elseif (is_numeric($pfb_table['stats'][$type])) {
				$stats[$key][$type] = number_format($pfb_table['stats'][$type], 0, '', ',' ) ?: 0;
			}

			else {
				$stats[$key][$type] = htmlspecialchars($pfb_table['stats'][$type]) ?: 0;
			}
		}
	}

	if (is_numeric($stats[1]['DNSBL'])) {
		$stats[1]['DNSBL'] = number_format($stats[1]['DNSBL'], 0, '', ',' ) ?: 0;
	}
	if (is_numeric($stats[1]['Queries'])) {
		$stats[1]['Queries'] = number_format($stats[1]['Queries'], 0, '', ',' ) ?: 0;
	}

	if (isset($pfb_table['stats'])) {
		unset($pfb_table['stats']);
	}

	$counts = array();
	if (isset($pfb_table['counts'])) {
		foreach ($pfb_table['counts'] as $key => $line) {
			if (is_numeric($line)) {
				$counts[$key] = number_format($line, 0, '', ',' ) ?: 0;
			} else {
				$counts[$key] = htmlspecialchars($line) ?: 0;
			}
		}
		unset($pfb_table['counts']);
	}

	// Collect Last packet clear timestamps
	if ($pfb['enable'] == 'on') {
		$db_handle = pfb_open_sqlite(6, 'Last Clear Stats');
		$pfb_found = FALSE;
		if ($db_handle) {
			$result = $db_handle->query("SELECT * FROM lastclear WHERE row = 0;");
			if ($result) {
				while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
					if ($res['lastipclear'] !== NULL) {
						$ip_last_clear		= htmlspecialchars("{$res['lastipclear']}");
						$dnsbl_last_clear	= htmlspecialchars("{$res['lastdnsblclear']}");
						$pfb_found = TRUE;
					}
				}
			}

			if (!$pfb_found) {
				$db_update = "INSERT into lastclear (row, lastipclear, lastdnsblclear) VALUES (0, 'Unknown', 'Unknown');";
				$db_handle->exec("BEGIN TRANSACTION;"
						. "{$db_update}"
						. "END TRANSACTION;");
			}
		}
	}

	// Update values via AJAX
	if ($mode == 'js') {

		foreach ($stats as $key => $group) {
			foreach ($group as $type => $value) {

				if ($type == 'Suppression') {
					print("DNSBLSTATUS||{$dnsbl_status}||{$dnsbl_msg}\n");
					print("{$type}||{$value}||-\n");
				} elseif ($type == 'Whitelist') {
					print("PFBSTATUS||{$pfb_status}||{$pfb_msg}\n");
					print("{$type}||{$value}||-\n");
				} else {
					print("{$type}||{$value}||-\n");
				}
			}
		}

		// Update titles
		print("Deny||Number of BLOCK & REJECT packet(s) blocked: {$stats[0]['Deny']}_BR_Total Count: {$counts['Deny']}"
			. "_BR_Last clear: {$ip_last_clear}||title\n");
		print("Pass||Number of PASS packet(s) passed: {$stats[0]['Pass']}_BR_Total Count: {$counts['Pass']}"
			. "_BR_Last clear: {$ip_last_clear}||title\n");
		print("Match||Number of MATCH packet(s) matched: {$stats[0]['Match']}_BR_Total Count: {$counts['Match']}"
			. "_BR_Last clear: {$ip_last_clear}||title\n");

		// Don't add DNSBL title if entry is "Unknown"
		if (!isset($pfb['dnsbl_missing'])) {
			print("DNSBL||Number of DNSBL Packet(s) blocked: {$stats[1]['DNSBL']}_BR_Total Count: {$counts['DNSBL']}"
				. "_BR_Last clear: {$dnsbl_last_clear}||title\n");
			print("Queries||Number of Unbound Resolver Queries since last clearing_BR_Last clear: {$dnsbl_last_clear}\n");
		}
		else {
			print("DNSBL||{$counts['DNSBL']}\n");
		}
	}
	else {
		$tab4 = "\t\t\t\t";
		$tab5 = "\t\t\t\t\t";
		$tab6 = "\t\t\t\t\t\t";
		$tab7 = "\t\t\t\t\t\t\t";
		$tdl = "style=\"text-align: left;\"";

		// FA Icons
		$faicon = [
			['fa-solid fa-times text-danger', 'fa-solid fa-check text-success', 'fa-solid fa-filter', 'fa-solid fa-list-ol'],
			['fa-solid fa-times text-danger', 'fa-solid fa-history', 'fa-solid fa-percent', 'fa-solid fa-list-ol']
		];

		// Title descriptions
		$titles = array ( array (	'Deny'		=> "Number of BLOCK & REJECT packet(s) blocked: {$stats[0]['Deny']}"
									. "\nTotal Count: {$counts['Deny']}"
									. "\nLast clear: {$ip_last_clear}",
						'Pass'		=> "Number of PASS packet(s) passed: {$stats[0]['Pass']}"
									. "\nTotal Count: {$counts['Pass']}"
									. "\nLast clear: {$ip_last_clear}",
						'Match'		=> "Number of MATCH packet(s) matched: {$stats[0]['Match']}"
									. "\nTotal Count: {$counts['Match']}"
									. "\nLast clear: {$ip_last_clear}",
						'Suppression'	=> 'Number of IP entries in the Suppression List'),

				 array (	'DNSBL'		=> "Number of DNSBL Packet(s) blocked: {$stats[1]['DNSBL']}"
									. "\nTotal Count: {$counts['DNSBL']}"
									. "\nLast clear: {$dnsbl_last_clear}",
						'Queries'	=> 'Number of Unbound Resolver Queries since last clearing'
									. "\nLast clear: {$dnsbl_last_clear}",
						'Percent'	=> 'Percentage of Domains Blocked vs Unbound Resolver Queries',
						'Whitelist'	=> 'Number of Domain entries in the DNSBL Whitelist'));

		foreach ($stats as $key => $line) {
			$col = 0;

			// Print IP widget statistics
			if ($key == 0 && $col == 0) {
				print("<tr>\n{$tab5}<td {$tdl} title=\"{$pfb_msg}\"><i class=\"PFBSTATUS {$pfb_status}\">"
					. "</i>&nbsp;&nbsp;<strong>IP</strong></td>\n");
			}

			// Print DNSBL widget statistics
			elseif ($key == 1 && $col == 0) {
				print("\n{$tab4}<tr>\n{$tab5}<td {$tdl} title=\"{$dnsbl_msg}\"><i class=\"DNSBLSTATUS {$dnsbl_status}\">"
					. "</i>&nbsp;&nbsp;<strong>DNSBL</strong></td>\n");
			}

			// Print widget data
			foreach ($line as $data => $value) {

				if ($data == 'Suppression' || $data == 'Whitelist') {
					$d_type = ($data == 'Suppression') ? 'ip' : 'dnsbl';
					print("{$tab5}<td {$tdl} title=\"{$titles[$key][$data]}\"><i class=\"{$faicon[$key][$col]}\"></i>&nbsp;&nbsp;"
						. "<a target=\"_blank\" href=\"/pfblockerng/pfblockerng_{$d_type}.php#{$data}\" title=\"Link to {$data}\">"
						. "<small><span class=\"pfb_{$data}\">{$value}</span></small></a></td>\n");
				}
				else {
					print("{$tab5}<td {$tdl} class=\"pfb_title_{$data}\" title=\"{$titles[$key][$data]}\">"
						. "<i class=\"{$faicon[$key][$col]}\"></i>&nbsp;&nbsp;"
						. "<small><span class=\"pfb_{$data}\">{$value}</span></small></td>\n");
				}
				$col++;
			}

			// Print 'Click to Open Logs tab' icon
			if ($key == 0) {
				print("{$tab5}<td>\n{$tab6}<a target='_blank' href='pfblockerng/pfblockerng_log.php' "
					. "title='" . gettext("Click to open Logs tab") . "'>\n{$tab7}<i class='fa-regular fa-rectangle-list'></i>\n{$tab5}</a></td>\n"
					. "{$tab4}</tr>");
			}
			elseif ($key == 1) {
				print("{$tab4}</tr>\n");
			}
		}
	}

	// Use pfb_table array for next table function
	return $pfb_table;
}


// Update table contents
function pfBlockerNG_get_table($mode='', $pfb_table) {
	global $pfb;
	$counter = 0; $dcounter = 1; $response = '';

	if (!empty($pfb_table)) {

		reset($pfb_table);
		$last_line = end($pfb_table);

		foreach ($pfb_table as $pfb_alias => $values) {

			if (is_numeric($values['count'])) {
				$values['count'] = number_format($values['count'], 0, '', ',' ) ?: 0;
			}

			if (strpos($pfb_alias, 'DNSBL_') !== FALSE) {
				// Packet column pivot to Alerts Tab
				if ($values['packets'] > 0) {
					$packets  = "<a href=\"/pfblockerng/pfblockerng_alerts.php?filterdnsbl={$pfb_alias}\" ";
					$packets .= "target=\"_blank\" title=\"Click to view these packets in Alerts tab\" >{$values['packets']}</a>";
				} else {
					$packets = $values['packets'] ?: 0;
				}
			} else {
				// Add firewall rules count associated with alias
				$values['img'] = $values['img'] . '<span title="Alias Firewall Rule count"></span>';
				if ($values['rule'] > 0) {
					$values['img'] .= "&nbsp;&nbsp;<small>({$values['rule']})</small>";
				}

				// If packet fence errors found, display error.
				if ($pfb['pfctlerr']) {
					$values['img'] = $pfb['err'];
				}

				// Packet column pivot to Alerts Tab
				if ($values['packets'] > 0) {
					$packets  = "<a target=\"_blank\" href=\"/pfblockerng/pfblockerng_alerts.php?filterip={$pfb_alias}\" ";
					$packets .= "title=\"Click to view these packets in Alerts tab\" >{$values['packets']}</a>";
				}
				else {
					$packets = $values['packets'] ?: 0;
				}

				// Alias table popup
				if ($values['count'] > 0 && $pfb['popup'] == 'on') {
					$pfb_alias = "<a href=\"/firewall_aliases_edit.php?id={$values['id']}\" data-popover=\"true\" "
						. " data-trigger=\"hover focus\" title=\"pfBlockerNG Alias details\" data-content=\""
						. alias_info_popup($values['id']) . "\" data-html=\"true\">{$pfb_alias}</a>";
				}
			}

			if ($mode == 'js') {
				print $response = "{$pfb_alias}||{$values['count']}||{$packets}||{$values['update']}||{$values['img']}\n";
			}
			else {
				print ("<tr>
						<td><small>{$pfb_alias}</small></td>
						<td><small>{$values['count']}</small></td>
						<td><small>{$packets}</small></td>
						<td><small>{$values['update']}</small></td>
						<td>{$values['img']}</td>
				</tr>");

				if ($values !== $last_line) {
					print ("\n\t\t\t\t");
				} else {
					print ("\r");
				}
			}
		}
	}
}
?>

<form id="formicons" action="/widgets/widgets/pfblockerng.widget.php" method="post" class="form-horizontal">
<input type="hidden" name="pfblockerngack" id="pfblockerngack" value="">
<input type="hidden" name="pfblockerngclear" id="pfblockerngclear" value="">
<input type="hidden" name="pfblockerngclearall" id="pfblockerngclearall" value="">
<input type="hidden" name="pfblockerngclearip" id="pfblockerngclearip" value="">
<input type="hidden" name="pfblockerngcleardnsbl" id="pfblockerngcleardnsbl" value="">

	<!-- Print failed downloads (if any) -->
	<div class="table-responsive">
		<div id="pfBNG-failed">
			<!-- Print failed contents, subsequent refresh by javascript function -->
			<?=pfBlockerNG_get_failed()?>
		</div>

		<!-- Print Status header -->
		<table class="table table-condensed">
			<thead>
				<tr>
					<th width="17%"><!-- Status icon    --></th>
					<th width="17%"><!-- IP/DNSBL count --></th>
					<th width="17%"><!-- Permit count   --></th>
					<th width="17%"><!-- Match count    --></th>
					<th width="17%"><!-- Supp/White     --></th>
					<th width="15%"><!-- Icons	    --></th>
				</tr>
			</thead>
			<tbody id="pfBNG-header">
				<!-- Print header contents, subsequent refresh by javascript function -->
				<?php
				$pfb_table = pfBlockerNG_get_header();
				?>
			</tbody>
		</table>
	</div>

	<!-- Print main table header -->
	<div class="table-responsive" style="max-height: <?=$pfb['maxheight'];?>px; overflow: auto;">
		<table id="pfb-tbl" class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
					<th id="pfB_col1" ><?=gettext("Alias");?></th>
					<th id="pfB_col2" title="The count can be a mixture of Single IPs or CIDR values"><?=gettext("Count");?></th>
					<th id="pfB_col3" title="Total Packet counts by IP Alias / DNSBL Group"><?=gettext("Packets");?>
						<i class='fa-regular fa-trash-can icon-pointer' id='pfblockerngclearicon' title="Clear Packets"></i>
					</th>
					<th id="pfB_col4" title="Last Update (Date/Time) of the Alias"><?=gettext("Updated");?></th>
					<th><?=$pfb['down']?>&nbsp;<?=$pfb['up']?></th>
				</tr>
			</thead>
			<tbody id="pfBNG-table">
				<!-- Print table contents, subsequent refresh by javascript function -->
				<?=pfBlockerNG_get_table('', $pfb_table);?>
			</tbody>
		</table>
	</div>
</form>

<!-- Widget customization settings wrench -->
</div>
<div id="widget-<?=$widgetname?>_panel-footer" class="panel-footer collapse">

<form action="/widgets/widgets/pfblockerng.widget.php" method="post" class="form-horizontal">
	<div class="form-group">
		<label class="col-sm-8 control-label">Enable Alias Table Popup</label>
		<div class="col-sm-2 checkbox">
			<label><input type="checkbox" name="pfb_popup" value="on"
				<?=($pfb['popup'] == "on" ? 'checked' : '')?> /></label>
		</div>
	</div>
	<div class="form-group">
		<label for="pfb_clearip" class="col-sm-8 control-label">Enter frequency to clear the IP counters</label>
		<div class="col-sm-4">
			<select name="pfb_clearip" class="form-control">
			<?php foreach (array('never' => gettext('Never'), 'daily' => gettext('Daily'), 'weekly' => gettext('Weekly'))
				as $clearip => $cleartype):?>
				<option value="<?=$clearip?>" <?=($clearip == $pfb['clearip'] ? 'selected' : '')?> ><?=$cleartype?></option>
			<?php endforeach;?>
			</select>
		</div>
	</div>
	<div class="form-group">
		<label for="pfb_cleardnsbl" class="col-sm-8 control-label">Enter frequency to clear the DNSBL/Unbound counters</label>
		<div class="col-sm-4">
			<select name="pfb_cleardnsbl" class="form-control">
			<?php foreach (array('never' => gettext('Never'), 'daily' => gettext('Daily'), 'weekly' => gettext('Weekly'))
				as $cleardnsbl => $cleartype):?>
				<option value="<?=$cleardnsbl?>" <?=($cleardnsbl == $pfb['cleardnsbl'] ? 'selected' : '')?> ><?=$cleartype?></option>
			<?php endforeach;?>
			</select>
		</div>
	</div>
	<div class="form-group">
		<label for="pfb_dnsblquery" class="col-sm-8 control-label">Enter DNSBL Resolver Query frequency (Default:5)</label>
		<div class="col-sm-4">
			<input type="number" name="pfb_dnsblquery" value="<?=$pfb['dnsblquery']?>"
				min="5" max="300" class="form-control" />
		</div>
	</div>
	<div class="form-group">
		<label for="pfb_maxfails" class="col-sm-8 control-label">Enter number of download fails to display (default:3)</label>
		<div class="col-sm-3">
			<input type="number" name="pfb_maxfails" value="<?=$pfb['maxfails']?>"
				min="1" max="20" class="form-control" />
		</div>
	</div>
	<div class="form-group">
		<label for="pfb_sortcolumn" class="col-sm-8 control-label">Enter Sort Column</label>
		<div class="col-sm-4">
			<select name="pfb_sortcolumn" class="form-control">
			<?php foreach (array('none' => gettext('None'), 'alias' => gettext('Alias'), 'count' => gettext('Count'),
			    'packets' => gettext('Packets'), 'update' => gettext('Update'))
				as $sort => $sorttype):?>
				<option value="<?=$sort?>" <?=($sort == $pfb['sortcolumn'] ? 'selected' : '')?> ><?=$sorttype?></option>
			<?php endforeach;?>
			</select>
		</div>
	</div>
	<div class="form-group">
		<label for="pfb_sortmix" class="col-sm-8 control-label"><?=gettext('Combined sort (IP/DNSBL)')?></label>
		<div class="col-sm-2 checkbox">
			<label><input type="checkbox" name="pfb_sortmix" value="on"
				<?=($pfb['sortmix'] == "on" ? 'checked' : '')?> /></label>
		</div>
	</div>
	<div class="form-group">
		<label for="pfb_sortdir" class="col-sm-8 control-label"><?=gettext('Select sort direction')?></label>
		<div class="col-sm-4">
			<label><input type="radio" name="pfb_sortdir" id="pfb_sortdir_asc" value="asc"
				<?=($pfb['sortdir'] == "asc" ? 'checked' : '')?> /> <?=gettext('Ascending')?></label>
			<label><input type="radio" name="pfb_sortdir" id="pfb_sortdir_des" value="des"
				<?=($pfb['sortdir'] == "des" ? 'checked' : '')?> /> <?=gettext('Descending')?></label>
		</div>
	</div>
	<div class="form-group">
		<label for="pfb_maxheight" class="col-sm-8 control-label"><?=gettext('Widget max height in px (default:2500)')?></label>
		<div class="col-sm-3">
			<input type="number" name="pfb_maxheight" value="<?=$pfb['maxheight'];?>"
				min="100" max="2500" step="10" class="form-control" />
		</div>
	</div>
	<div class="form-group">
		<div class="col-sm-offset-4 col-sm-8">
			<button type="submit" name="pfb_submit" id="pfb_submit" class="btn btn-primary">
				<i class="fa-solid fa-save icon-embed-btn"></i><?=gettext('Save Settings')?>
			</button>
		</div>
	</div>
</form>
