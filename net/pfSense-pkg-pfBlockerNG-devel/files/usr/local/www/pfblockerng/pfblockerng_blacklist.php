<?php
/*
 * pfblockerng_blacklist.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016-2022 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2022 BBcan177@gmail.com
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

$blacklist_types = glob("/usr/local/pkg/pfblockerng/*_global_usage");
$blacklist_options = array();
if (!empty($blacklist_types)) {
	foreach ($blacklist_types as $key => $type) {

		$validate		= 0;
		$list			= array();
		$list['CONTENTS']	= file($type, FILE_SKIP_EMPTY_LINES|FILE_IGNORE_NEW_LINES);

		if (is_array($list['CONTENTS'])) {
			foreach ($list['CONTENTS'] as $line => $data) {

				$data = trim($data);
				if (substr($data, 0, 1) == '#' || empty($data)) {
					unset($list['CONTENTS'][$line]);
					continue;
				}

				foreach (array('TITLE', 'DESCR', 'XML', 'FEED', 'SIZE', 'WEBSITE', 'LICENSE', 'REG') as $setting) {
					if (isset($list[$setting])) {
						continue;
					}

					if (strpos($data, "{$setting}:") !== FALSE) {

						$match = array_map('trim', explode(':', $data, 2));
						if ($setting == 'XML') {
							// Sanitize config variable name
							$list[$setting] = strtolower(preg_replace("/\W/", '', $match[1]));
						}
						elseif ($setting == 'DESCR') {
							$list[$setting] = html_entity_decode($match[1]);
						}
						else {
							$list[$setting] = $match[1];
						}

						unset($list['CONTENTS'][$line]);
						if (!empty($list[$setting]) && $setting != 'REG') {
							$validate++;
						}
						break;
					}
				}
			}
		}

		// Only add Blacklist if settings are validated
		if ($validate == 7) {
			$blacklist_types[$list['XML']]	= $list;
			$blacklist_options		= array_merge($blacklist_options, array($list['XML'] => $list['DESCR']));
		}
		unset($blacklist_types[$key]);
	}
}

init_config_arr(array('installedpackages', 'pfblockerngblacklist'));
$pfb['bconfig']	= &$config['installedpackages']['pfblockerngblacklist'];

$pconfig = array();
$pconfig['blacklist_enable']		= $pfb['bconfig']['blacklist_enable']				?: 'Disable';
$pconfig['blacklist_lang']		= $pfb['bconfig']['blacklist_lang']				?: 'EN';
$pconfig['blacklist_selected']		= explode(',', $pfb['bconfig']['blacklist_selected'])		?: array();
$pconfig['blacklist_freq']		= $pfb['bconfig']['blacklist_freq']				?: 'Never';
$pconfig['blacklist_logging']		= $pfb['bconfig']['blacklist_logging']				?: 'enabled';

if (isset($blacklist_types)) {
	foreach ($blacklist_types as $type => $setting) {

		$pconfig['blacklist_' . $type] = array();
		if (isset($pfb['bconfig']['item'])) {
			foreach ($pfb['bconfig']['item'] as $item) {

				if ($item['xml'] == $type) {
					$pconfig['blacklist_' . $type] = explode(',', $item['selected']) ?: array();
					if (isset($setting['REG'])) {
						$pconfig['blacklist_' . $type . '_username'] = $item['username'] ?: '';
						$pconfig['blacklist_' . $type . '_password'] = $item['password'] ?: '';
					}
					continue;
				}
			}
		}
	}
}

if ($_POST && !$_POST['enableall'] && !$_POST['disableall']) {

	$rowid		= 0;
	$a_list		= array();
	$config_mod	= FALSE;
	if (isset($input_errors)) {
		unset($input_errors);
	}
	if (isset($savemsg)) {
		unset($savemsg);
	}

	if (isset($_POST['blacklist_enable'])) {
		$pfb['bconfig']['blacklist_enable']	= $_POST['blacklist_enable'];
		$config_mod = TRUE;
	}

	if (isset($_POST['blacklist_lang'])) {
		$pfb['bconfig']['blacklist_lang']	= $_POST['blacklist_lang'];
		$config_mod = TRUE;
	}

	if (isset($_POST['save'])) {

		if (isset($_POST['blacklist_selected'])) {
			$pfb['bconfig']['blacklist_selected']	= implode(',', (array)$_POST['blacklist_selected']);
		} else {
			$pfb['bconfig']['blacklist_selected']	= '';
		}
		if (isset($_POST['blacklist_freq'])) {
			$pfb['bconfig']['blacklist_freq']	= $_POST['blacklist_freq'];
		}
		if (isset($_POST['blacklist_logging'])) {
			$pfb['bconfig']['blacklist_logging']	= $_POST['blacklist_logging'];
		}

		$config_mod = TRUE;
		foreach ($blacklist_types as $type => $setting) {
			$list = array();

			foreach (array('TITLE', 'XML', 'FEED', 'SIZE') as $value) {
				$lvalue = strtolower($value);	// Config variables must be in lowercase
				if (isset($blacklist_types[$type][$value])) {
					$list[$lvalue] = pfb_filter($blacklist_types[$type][$value], 1);
				}
			}

			$list['selected'] = implode(',', (array)$_POST['blacklist_' . $type]) ?: '';

			if (isset($_POST['blacklist_' . $type . '_username'])) {
				$list['username'] = pfb_filter($_POST['blacklist_' . $type . '_username'], 1);
			}

			if (isset($_POST['blacklist_' . $type . '_password'])) {
				if ($_POST['blacklist_' . $type . '_password'] == $_POST['blacklist_' . $type . '_password_confirm']) {
					if ($_POST['blacklist_' . $type . '_password'] != DMYPWD) {
						$list['password'] = pfb_filter($_POST['blacklist_' . $type . '_password'], 1);
					}
				} else {
					$input_errors[] = "[ {$setting['TITLE']} ] The password does not match the confirm password!";
				}
			}

			$a_list[] = $list;
		}
		$pfb['bconfig']['item'] = $a_list;

		// Check for Large category selections and show savemsg
		foreach ($blacklist_types as $type => $setting) {
			if (isset($_POST['blacklist_' . $type])) {
				foreach (array('porn', 'adult', 'prime') as $cat) {
					if (in_array($cat, $_POST['blacklist_' . $type])) {
						$savemsg .= "{$type} category [ " . ucfirst($cat) . " ] enabled.BR";
					}
				}
			}
		}
		if ($savemsg) {
			$savemsg .= 'BR *** Large categories selected! Please review DNSBL TLD memory recommendations before continuing ***';
		}
	}

	if ($config_mod && !isset($input_errors)) {

		write_config('[ pfBlockerNG ] save DNSBL Category settings');
		if ($savemsg) {
			header("Location: /pfblockerng/pfblockerng_blacklist.php?savemsg={$savemsg}");
		} else {
			header('Location: /pfblockerng/pfblockerng_blacklist.php');
		}
	}
}

$pgtitle = array(gettext('Firewall'), gettext('pfBlockerNG'), gettext('DNSBL'), gettext('DNSBL Category'));
$pglinks = array('', '/pfblockerng/pfblockerng_general.php', '/pfblockerng/pfblockerng_dnsbl.php', '@self');
include_once('head.inc');

// Define default Alerts Tab href link (Top row)
$get_req = pfb_alerts_default_page();

$tab_array	= array();
$tab_array[]	= array(gettext('General'),		false,	'/pfblockerng/pfblockerng_general.php');
$tab_array[]	= array(gettext('IP'),			false,	'/pfblockerng/pfblockerng_ip.php');
$tab_array[]	= array(gettext('DNSBL'),		true,	'/pfblockerng/pfblockerng_dnsbl.php');
$tab_array[]	= array(gettext('Update'),		false,	'/pfblockerng/pfblockerng_update.php');
$tab_array[]	= array(gettext('Reports'),		false,	"/pfblockerng/pfblockerng_alerts.php{$get_req}");
$tab_array[]	= array(gettext('Feeds'),		false,	'/pfblockerng/pfblockerng_feeds.php');
$tab_array[]	= array(gettext('Logs'),		false,	'/pfblockerng/pfblockerng_log.php');
$tab_array[]	= array(gettext('Sync'),		false,	'/pfblockerng/pfblockerng_sync.php');
display_top_tabs($tab_array, true);

$tab_array	= array();
$tab_array[]	= array(gettext('DNSBL Groups'),	false,	'/pfblockerng/pfblockerng_category.php?type=dnsbl');
$tab_array[]	= array(gettext('DNSBL Category'),	true,	'/pfblockerng/pfblockerng_blacklist.php');
$tab_array[]	= array(gettext('DNSBL SafeSearch'),	false,	'/pfblockerng/pfblockerng_safesearch.php');
display_top_tabs($tab_array, true);

if (isset($_REQUEST['savemsg'])) {
	$savemsg = str_replace('BR', '<br />', htmlspecialchars($_REQUEST['savemsg']));
	print_info_box($savemsg, 'info');
}

if (isset($input_errors)) {
	print_input_errors($input_errors);
}

// Create Form
$form = new Form('Save');

$section = new Form_Section('Blacklist Category settings');
$section->addInput(new Form_StaticText(
	'Links',
	'<small>'
	. '<a href="/firewall_aliases.php" target="_blank">Firewall Aliases</a>&emsp;'
	. '<a href="/firewall_rules.php" target="_blank">Firewall Rules</a>&emsp;'
	. '<a href="/status_logs_filter.php" target="_blank">Firewall Logs</a></small>'
));

$section->addInput(new Form_Select(
	'blacklist_enable',
	gettext('Blacklist Category'),
	$pconfig['blacklist_enable'],
	['Disable' => 'Disable', 'Enable' => 'Enable']
))->setHelp('Select to enable DNSBL category based Blacklist(s)<br />'
		. '<span class="text-danger">Note: </span> Save changes prior to enable/disable'
		. '<br /><span class="text-danger">Note: </span>To achieve the full potential of Category blocking,'
		. ' the <strong>TLD</strong> option should be utilized which will allow blocking of all sub-domains.')
  ->setAttribute('style', 'width: auto');

if (!empty($blacklist_types)) {
	$section->addInput(new Form_Select(
		'blacklist_selected',
		gettext('Blacklists'),
		$pconfig['blacklist_selected'],
		$blacklist_options,
		TRUE
	))->setHelp('Select Blacklist(s) to enable')
	  ->setAttribute('size', count($blacklist_types) ?: 1)
	  ->setAttribute('style', 'width: auto');
}
else {
	$section->addInput(new Form_StaticText(
		NULL,
		'<span style="color: red;">No Blacklist(s) global_usage files have been found!</span><br /><br />'
	));
}

$section->addInput(new Form_Select(
	'blacklist_lang',
	gettext('Language'),
	$pconfig['blacklist_lang'],
	['EN' => 'English', 'DE' => 'German', 'FR' => 'French', 'IT' => 'Italian',
	'NL' => 'Dutch', 'PT' => 'Portuguese', 'ES' => 'Spanish', 'RU' => 'Russian']
))->setHelp('Default: <strong>English</strong><br />
	Select the language setting. Not all languages have been fully translated.')
  ->setAttribute('style', 'width: auto');

$section->addInput(new Form_Select(
	'blacklist_freq',
	gettext('Update Frequency'),
	$pconfig['blacklist_freq'],
	['Never' => 'Never', 'EveryDay' => 'Once a day (Random hour)', 'Weekly' => 'Weekly (Sunday)']
))->setHelp('Default: <strong>Never</strong><br />
	Select how often the Blacklist database(s) will be downloaded.')
  ->setAttribute('style', 'width: auto');

$section->addInput(new Form_Select(
	'blacklist_logging',
	'Logging',
	$pconfig['blacklist_logging'],
	['enabled' => 'Enabled', 'disabled' => 'Disabled']
))->setHelp("Default: <strong>Enabled</strong><br />
	When 'Enabled', Domains are sinkholed to the DNSBL VIP and logged via the DNSBL Web Server.<br />
	When 'Disabled', '0.0.0.0' will be used instead of the DNSBL VIP.<br />
	A 'Force Reload - DNSBL' is required for changes to take effect")
  ->setAttribute('style', 'width: auto');

$form->add($section);

foreach ($blacklist_types as $type => $setting) {

	$sec_status = SEC_CLOSED;
	if ($pconfig['blacklist_enable'] != 'Disable' && in_array($type, $pconfig['blacklist_selected'])) {
		$sec_status = SEC_OPEN;
	}

	$section = new Form_Section($setting['TITLE'], $setting['XML'], COLLAPSIBLE|$sec_status);

	$lic_txt = 'Licence';
	if ($setting['REG']) {
		$lic_txt = '- Subscription required';
	}

	$section->addInput(new Form_StaticText(
		gettext('Links'),
		"<a href=\"{$setting['WEBSITE']}\" target=\"_blank\"><i class=\"fa fa-globe\"></i>&nbsp;{$setting['TITLE']} Summary</a>&emsp;"
		. "<a href=\"{$setting['LICENSE']}\" target=\"_blank\"><i class=\"fa fa-globe\"></i>&nbsp;{$setting['TITLE']} {$lic_txt}</a>"
	));

	// Add username/password fields if required
	if ($setting['REG']) {
		$section->addInput(new Form_Input(
			'blacklist_' . $type . '_username',
			NULL,
			'text',
			$pconfig['blacklist_' . $type . '_username'],
			['placeholder' => 'Enter the username']
		))->setHelp('Username')
		  ->setAttribute('autocomplete', 'off');

		$section->addPassword(new Form_Input(
			'blacklist_' . $type . '_password',
			NULL,
			'password',
			$pconfig['blacklist_' . $type . '_password'],
			['placeholder' => 'Enter the password']
		))->setHelp("Password<br /><br />")
		  ->setAttribute('autocomplete', 'off');
	}

	// Build array of Blacklist categories and descriptions by language
	$data = array();
	if (isset($blacklist_types[$type]['CONTENTS']) && !empty($blacklist_types[$type]['CONTENTS'])) {
		foreach ($blacklist_types[$type]['CONTENTS'] as $line) {

			if (strpos($line, 'NAME:') !== FALSE) {
				$cat = array_map('trim', explode(':', $line));
				if (strpos($cat[1], '/') !== FALSE) {
					$cat[1] = strstr($cat[1], '/', TRUE);
				}
			}
			elseif (strpos($line, 'DESC') !== FALSE) {
				$desc = explode(':', $line);
				$desc[0] = trim(strstr($desc[0], ' ', FALSE));
				$data[$cat[1]][$desc[0]][0]= trim($desc[1]);
			}
			elseif (strpos($line, 'NAME ') !== FALSE) {
				$name = explode(':', $line);
				$name[0] = trim(strstr($name[0], ' ', FALSE));
				$data[$cat[1]][$name[0]][1] = trim($name[1]);
			}
		}
	}

	ksort($data, SORT_NATURAL);
	foreach ($data as $category => $info) {

		$category_lang = $info[$pconfig['blacklist_lang']][1] ?: $info['EN'][1];

		$selected = FALSE;
		if ($_POST['enableall'][$type]) {
			$selected = TRUE; 
		}
		elseif ($_POST['disableall'][$type]) {
			$selected = FALSE;
		}
		elseif (in_array($category, $pconfig['blacklist_' . $type])) {
			$selected = TRUE;
		}

		$group = new Form_Group(NULL);
		$group->add(new Form_Checkbox(
			'blacklist_' . $type . '[]',
			'',
			NULL,
			$selected,
			$category
		))->setWidth(1)
		  ->setAttribute('title', "Select to enable [ {$setting['TITLE']} - {$category_lang} ] category | {$category}")
		  ->addClass('multi')->setAttribute('id');

		$group->add(new Form_StaticText(
			'',
			$category_lang
		));

		$group->add(new Form_StaticText(
			'',
			$info[$pconfig['blacklist_lang']][0] ?: $info['EN'][0]
		))->setWidth(7);

		$section->add($group);
	}

	$group = new Form_Group(NULL);
	$btnenableall = new Form_Button(
		'enableall[' . $type . ']',
		gettext('Enable All'),
		NULL,
		'fa-toggle-on'
	);
	$btnenableall->removeClass('btn-primary')->addClass('btn-primary btn-xs');

	$btndisableall = new Form_Button(
		'disableall[' . $type . ']',
		gettext('Disable All'),
		NULL,
		'fa-toggle-off'
	);
	$btndisableall->removeClass('btn-primary')->addClass('btn-primary btn-xs');

	$group->add(new Form_StaticText(
		'',
		$btnenableall . '&emsp;' . $btndisableall
	));
	$section->add($group);
	$form->add($section);
}

print($form);
print_callout('<p><strong>Setting changes are applied via CRON or \'Force Update|Reload\' only!</strong><br /><br />
		DNSBL Category Feeds are processed first, followed by the DNSBL Groups.<br />
		DNSBL Groups can be prioritized first, by selecting the \'Group Order\' option.</p>');
?>

<script type="text/javascript">
//<![CDATA[

events.push(function() {

	$('#blacklist_enable').change(function() {
		$('form').submit();
	});
	$('#blacklist_lang').change(function() {
		$('form').submit();
	});
})

//]]>
</script>

<?php include('foot.inc');?>
