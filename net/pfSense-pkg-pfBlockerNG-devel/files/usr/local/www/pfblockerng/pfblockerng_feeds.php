<?php
/*
 * pfblockerng_feeds.php
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

init_config_arr(array('installedpackages', 'pfblockerngglobal'));
$fconfig	= &$config['installedpackages']['pfblockerngglobal'];

// Load/convert Feeds (w/alternative aliasname(s), if user-configured)
$feed_info	= convert_feeds_json();

$feed_count	= $feed_info['count'];
unset($feed_info['count']);

$pconfig = $feeds_list = array();
$feeds_list['ipv4'] = $feeds_list['ipv6'] = $feeds_list['dnsbl'] = array();

// $pfb['feeds_list'] created by load_feeds_json() function
if (is_array($pfb['feeds_list'])) {
	foreach ($pfb['feeds_list'] as $type => $data) {
		foreach ($data as $o_aliasname => $aliasname) {
			$l_aliasname = strtolower($o_aliasname);

			// Collect any user-defined alternate aliasname(s)
			$pconfig['feed_' . $l_aliasname] = $fconfig['feed_' . $l_aliasname] ?: '';

			// Collect list of original aliasnames/type (ipv4, ipv4 and dnsbl)
			$feeds_list[$type][] = $o_aliasname;
		}
	}
}

// Collect all 'selected' Alternative URL selections.
$feed_alt_selected = array();
if (is_array($fconfig)) {
	foreach ($fconfig as $key => $line) {
		if (substr($key, 0, 9) == 'feed_alt_') {
			$feed_alt_selected[] = str_replace('alt_', '', $line);
		}
	}
}

if ($_POST) {
	if (isset($_POST['save'])) {

		if (isset($input_errors)) {
			unset($input_errors);
		}

		$config_mod = FALSE;
		foreach ($pfb['feeds_list'] as $type => $data) {
			foreach ($data as $o_aliasname => $aliasname) {
				$l_aliasname = strtolower($o_aliasname);

				if (preg_match("/\W/", $_POST['feed_' . $l_aliasname])) {
					$input_errors[] = "Feed Settings: [ {$aliasname} ]"
							. 'Alias/Group name cannot contain spaces, special or international characters.';

					$pconfig['feed_' . $l_aliasname] = htmlspecialchars($_POST['feed_' . $l_aliasname]) ?: '';
				}
				else {
					$fconfig['feed_' . $l_aliasname] = $_POST['feed_' . $l_aliasname] ?: '';
					$config_mod = TRUE;

					// IPv4/6 Aliasnames cannot exceed 31 characters in PF. ( pfB_ + aliasname + _v? )
					if (!in_array(str_replace('feed_', '', $fconfig['feed_' . $l_aliasname]), $feeds_list['dnsbl'])) {
						$len_post = strlen($fconfig['feed_' . $l_aliasname]);
						if ($len_post > 24) {
							$input_errors[] = "Alternate Aliasname : [ {$o_aliasname} -> {$aliasname} ]"
									. " Field cannot exceed 24 characters. [ {$len_post} characters submitted. ]";
						}
					}
				}
			}
		}

		// Save all 'selected' Alternate URL feeds.
		if (isset($_POST['alt_selected'])) {

			$selected = explode(',', $_POST['alt_selected']);
			foreach ($selected as $value) {

				if (!empty($value)) {
					$post					= pfb_filter($_POST['alt_' . $value], 1);
					$value					= strtolower($value);		// config XML tag needs to be lowercase
					$feed_alt_{$value}			= $post;
					$fconfig['feed_alt_' . $value]		= $feed_alt_{$value};
				}
			}
			$config_mod = TRUE;
		}

		if ($config_mod && !$input_errors) {
			write_config('[pfBlockerNG] save Feed settings');
			header('Location: /pfblockerng/pfblockerng_feeds.php');
		}
	}
}

function url_compare($ftype, $key, $rowid, $aliasname, $row_aliasname, $row_url, $feed_url, $row_state,
	    $feed_header, $alternate=FALSE, $alt_header, $alt_info, $alt_register, $a_key) {

	global $ex_feeds, $alt_feeds, $icon;
	$x_icon = '';

	// Convert user defined URLs with '_API_KEY' to baseline URL format
	if (strpos($feed_url, '_API_KEY_') !== FALSE) {
		$ex = explode('_API_KEY_', preg_quote($feed_url, '/'));
		if (preg_match('/' . $ex[0] . '(.*)' . $ex[1] . '/', $row_url, $pm)) {
			$row_url = str_replace($pm[1], '_API_KEY_', $row_url);
		}
	}

	// Check for http/https Feed mismatch
	$x_url_mismatch = '';
	if (strpos($feed_url, 'https:') !== FALSE && strpos($row_url, 'http:') !== FALSE) {
		$x_url_mismatch = str_replace('http:', 'https:', $row_url);
	}
	elseif (strpos($feed_url, 'http:') !== FALSE && strpos($row_url, 'https:') !== FALSE) {
		$x_url_mismatch = str_replace('https:', 'http:', $row_url);
	}

	if ($feed_url == $row_url || $feed_url == $x_url_mismatch) {

		// Set found flag
		$ex_feeds[$ftype][$key]['found'] = TRUE;

		$fa_type = 'check';
		if ($aliasname != $row_aliasname) {
			$fa_type = 'check-circle-o';
		}

		if ($row_state == 'Disabled') {
			$x_icon .= "&emsp;"
				. "<a href=\"/pfblockerng/pfblockerng_category_edit.php?type={$ftype}"
				. "&rowid={$rowid}\""
				. "<span class=\"text-danger\""
				. "title=\"Feed exists in [ {$row_aliasname} ] but is Disabled.\">"
				. "<i class=\"fa fa-{$fa_type}\"></i>"
				. "</span>"
				. "</a>";
		} else {
			$x_icon .= "&emsp;"
				. "<a href=\"/pfblockerng/pfblockerng_category_edit.php?type={$ftype}"
				. "&rowid={$rowid}\""
				. "title=\"Feed exists in [ {$row_aliasname} ]\">"
				. "<i class=\"fa fa-{$fa_type}\"></i>"
				. "</a>";
		}

		// Add http/https protocol mismatch indicator
		if ($feed_url != $row_url) {
			$x_icon .= "&emsp;"
				. "<span class=\"text-danger\">"
				. "<i title=\"http/https protocol mismatch\" class=\"fa fa-key\"></i>"
				. "</span>";
		}
	}

	if (!empty($x_icon)) {
		if (!$alternate) {
			$icon	= $x_icon;
		}
		else {
			if (!$alt_feeds[$ftype][$aliasname][$alt_header]) {
				$alt_feeds[$ftype][$aliasname][$feed_header][$a_key] = array(	'icon' => $x_icon, 'url' => $feed_url, 'header' => $alt_header,
												'info' => $alt_info, 'register' => $alt_register );
				$alt_feeds[$ftype][$aliasname][$alt_header] = TRUE;
			} else {
				$a_icon = $alt_feeds[$ftype][$aliasname][$feed_header][$a_key]['icon'];
				$alt_feeds[$ftype][$aliasname][$feed_header][$a_key]['icon'] = $a_icon . $x_icon;
			}
		}
	}
	else {
		if ($alternate && !$alt_feeds[$ftype][$aliasname][$alt_header]) {
			$alt_feeds[$ftype][$aliasname][$feed_header][$a_key] = array(	'icon' => $x_icon, 'url' => $feed_url, 'header' => $alt_header,
											'info' => $alt_info, 'register' => $alt_register );
		}
	}
}

$pgtitle = array(gettext('Firewall'), gettext('pfBlockerNG'), gettext('Feeds'));
$pglinks = array('', '/pfblockerng/pfblockerng_general.php', '/pfblockerng/pfblockerng_feeds.php', '@self');
include_once('head.inc');

// Define default Alerts Tab href link (Top row)
$get_req = pfb_alerts_default_page();

$tab_array	= array();
$tab_array[]	= array(gettext('General'),	false,	'/pfblockerng/pfblockerng_general.php');
$tab_array[]	= array(gettext('IP'),		false,	'/pfblockerng/pfblockerng_ip.php');
$tab_array[]	= array(gettext('DNSBL'),	false,	'/pfblockerng/pfblockerng_dnsbl.php');
$tab_array[]	= array(gettext('Update'),	false,	'/pfblockerng/pfblockerng_update.php');
$tab_array[]	= array(gettext('Reports'),	false,	"/pfblockerng/pfblockerng_alerts.php{$get_req}");
$tab_array[]	= array(gettext('Feeds'),	true,	'/pfblockerng/pfblockerng_feeds.php');
$tab_array[]	= array(gettext('Logs'),	false,	'/pfblockerng/pfblockerng_log.php');
$tab_array[]	= array(gettext('Sync'),	false,	'/pfblockerng/pfblockerng_sync.php');
display_top_tabs($tab_array, true);

if (isset($input_errors)) {
	print_input_errors($input_errors);
}

?>
<form action="/pfblockerng/pfblockerng_feeds.php" method="post" name="iform" id="iform" class="form-horizontal">
<?php

$section = new Form_Section('Feed Settings', 'feedsettings', COLLAPSIBLE|SEC_CLOSED);
$section->addInput(new Form_StaticText(
	'Note:',
	'The <strong>Feed Settings</strong> can be used to rename the pre-defined Alias/Group names, and/or<br />'
	. ' combine multiple Alias/Groups together by using a duplicate Alias/Group name.'
));

$section->addInput(new Form_StaticText(
	'IPv4 Alias name(s):',
	'To change the default IPv4 Alias name(s), enter new Alias name(s) as desired.'));

foreach ($feeds_list['ipv4'] as $aliasname) {
	$l_aliasname = strtolower($aliasname);
	$section->addInput(new Form_Input(
		'feed_' . $l_aliasname,
		$aliasname,
		'text',
		$pconfig['feed_' . $l_aliasname]
	))->setWidth(3);
}

$section->addInput(new Form_StaticText(
	'IPv6 Alias name(s):',
	'To change the default IPv6 Alias name(s), enter new Alias name(s) as desired.'));

foreach ($feeds_list['ipv6'] as $aliasname) {
	$l_aliasname = strtolower($aliasname);
	$section->addInput(new Form_Input(
		'feed_' . $l_aliasname,
		$aliasname,
		'text',
		$pconfig['feed_' . $l_aliasname]
	))->setWidth(3);
}

$section->addInput(new Form_StaticText(
	'DNSBL Alias name(s):',
	'To change the default DNSBL Group name(s), enter new Group name(s) as desired.'));

foreach ($feeds_list['dnsbl'] as $aliasname) {
	$l_aliasname = strtolower($aliasname);
	$section->addInput(new Form_Input(
		'feed_' . $l_aliasname,
		$aliasname,
		'text',
		$pconfig['feed_' . $l_aliasname]
	))->setWidth(3);
}

$btn_save = new Form_Button(
	'save',
	'Save Settings',
	NULL,
	'fa-save'
);

$btn_save->removeClass('btn-primary')->addClass('btn-primary btn-sm');
$section->addInput(new Form_StaticText(
	NULL,
	$btn_save
));
print ($section);

?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext("Pre-defined Alias/Group/Feeds")?></h2>
	</div>

	&emsp;Links:&emsp;<small><a href="/firewall_aliases.php" target="_blank">Firewall Aliases</a>&emsp;
	<a href="/firewall_rules.php" target="_blank">Firewall Rules</a>&emsp;
	<a href="/status_logs_filter.php" target="_blank">Firewall Logs</a></small><br /><br />

	<div class="panel-body bg-info">
		<div style="margin: 10px 10px 10px 30px;">
			<p>
				The <strong>Feeds Management</strong> page is a collection of pre-defined Feeds arranged into Aliasnames/Groups.<br />
				Review the <strong>infoblock icons</strong> beside each Alias/Group name for details about each Group.<br /><br />

				<?php
				if (is_array($feed_count)) {
					print ("&emsp;<strong><u>Number of Feeds per Category Type:</strong></u><dl class=\"dl-horizontal\">");
					foreach ($feed_count as $type => $count) {
						print ("<dt>" . strtoupper($type) . ":</dt><dd>{$count}</dd>");
					}
					print ("</dl>");
				}
				?>

				&#8226; Feeds are listed by Category (IPv4/IPv6/DNSBL). Links are provided for each Feed website and Feed URL.<br />
				&#8226; Clicking the "+" icon(s) in the Category column will import all Feeds in the Alias/Group at once, while clicking the
				"+" icon(s) on the right will only import the individual feed.<br />

				&#8226; Feeds with 'Alternative' URL(s) can be configured via the Radio button options.<br />
				&#8226; Unknown user-defined Feeds are listed in a table below pre-defined Feeds<br />
				&#8226; Permit Type feeds are listed with a green background.<br />
			</p>

			<!-- Show Icon Legend -->
			Click here for Legend&emsp;-->
			<div class="infoblock" style="text-align:left">
				<dl class="dl-horizontal responsive">
					<dt><?=gettext('Icon')?></dt>
						<dd><?=gettext('Legend')?></dd>
					<dt><i class="fa fa-info-circle"></i></dt>
						<dd><?=gettext('Alias/Group/Feed Description/Information');?></dd>
					<dt><i class="fa fa-check"></i></dt>
						<dd><?=gettext('Item exists in pre-defined Alias/Group');?></dd>
					<dt><span class="text-danger"><i class="fa fa-check"></i></span></dt>
						<dd><?=gettext('Item exists but is Disabled in pre-defined Alias/Group');?></dd>
					<dt><i class="fa fa-check-circle-o"></i></dt>
						<dd><?=gettext('Item exists but in a different Alias/Group');?></dd>
					<dt><i class="fa fa-plus-circle"></i></dt>
						<dd><?=gettext('Add Item');?></dd>
					<dt><span class="text-danger"><i class="fa fa-key"></i></span></dt>
						<dd><?=gettext('Feed URL - http/https protocol mismatch');?></dd>
					<dt><i class="fa fa-angle-right"></i></dt>
						<dd><?=gettext('Alternate Feed URL options');?></dd>
					<dt><span class="text-danger"><i class="fa fa-bug"></i></span></dt>
						<dd><?=gettext('Feed temporarily unavailable');?></dd>
					<dt><i class="fa fa-sign-in"></i></dt>
						<dd><?=gettext('Subscription required to access Feed');?></dd>
				</dl>
			</div>
			<br /><strong><u>Disclaimer</u>: Use of the Feed(s) below are at your own risk!</strong>
			<span class="text-danger">&emsp;Note: Do not enable all Feeds at once.</span>
		</div>
	</div>
	<br />

	<div id="pfb_table" class="panel-body">
		<div class="table-responsive">
		<table id="pfb_table" class="table table-striped table-hover table-compact sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr id="pfb_header">
					<th><?=gettext('Category');?></th>
					<th><!----- Buttons -----></th>
					<th><?=gettext('Alias/Group');?></th>
					<th><?=gettext('Feed/Website');?></th>
					<th><?=gettext('Header/URL');?></th>
					<th><!----- Buttons -----></th>
				</tr>
			</thead>
			<tbody>

			<?php
			$list_type = array( 'pfblockernglistsv4' => 'ipv4', 'pfblockernglistsv6' => 'ipv6', 'pfblockerngdnsbl' => 'dnsbl');
			foreach ($list_type as $type => $feedtype) {
				if (!empty($config['installedpackages'][$type]['config'])) {
					foreach ($config['installedpackages'][$type]['config'] as $rowid => $list) {
						if (isset($list['row'])) {
							foreach ($list['row'] as $row) {
								if (!empty($row['url']) && !empty($row['header'])) {

									$ex_feeds[$feedtype][] = array(	'aliasname'	=>	$list['aliasname'],
													'action'	=>	$list['action'],
													'state'		=>	$row['state'],
													'url'		=>	$row['url'],
													'header'	=>	$row['header'],
													'rowid'		=>	$rowid
													);
								}
							}
						}
					}
				}

				if (!isset($ex_feeds[$feedtype])) {
					$ex_feeds[$feedtype][] = array();
				}
			}

			$alt_selected = '';		// CSV list of all Feeds which have 'Alternate URLs' (Used in POST/save)
			$feed_info_row = 0;
			$aliasname_found = array();

			foreach ($feed_info as $ftype => $info):

				if (empty($info)) {
					print ("<td class=\"bg-danger\"><br /><strong>No feeds definitions found!</strong><br /><br /></td>");
					continue;
				}

				foreach ($info as $aliasname => $data):
					$p_aliasname = '';
					if (!isset($data['feeds'])) {
						continue;
					}

					foreach ($data['feeds'] as $feed):
						$status = $icon = '';

						if (!empty($ex_feeds[$ftype])) {
							foreach ($ex_feeds[$ftype] as $key => $row) {

								if ($aliasname == $row['aliasname'] && !isset($aliasname_found[$aliasname])) {
									$aliasname_found[$aliasname] = $aliasname;

									if ($row['action'] == 'Disabled') {
										$status = "&emsp;"
											. "<a href=\"/pfblockerng/pfblockerng_category_edit.php?type={$ftype}"
											. "&rowid={$row['rowid']}\"<span class=\"text-danger\""
											. "title=\"Alias/Group exists but is currently Disabled\">"
											. "<i class=\"fa fa-check\"></i></span>"
											. "</a>";
									} else {
										$status = "&emsp;"
											. "<a href=\"/pfblockerng/pfblockerng_category_edit.php?type={$ftype}" 
											. "&rowid={$row['rowid']}\""
											. "title=\"Alias/Group exists\">"
											. "<i class=\"fa fa-check\"></i>"
											. "</a>";
									}
								}

								// Determine all Aliases that reference the Feed URL
								url_compare($ftype, $key, $row['rowid'], $aliasname, $row['aliasname'], $row['url'], $feed['url'],
										$row['state'], $feed['header'], FALSE, '', '', '', 0);

								// Determine all Aliases that reference the 'Alternate' Feed URLs
								if (isset($feed['alternate'])) {
									foreach ($feed['alternate'] as $a_key => $alt) {
										url_compare($ftype, $key, $row['rowid'], $aliasname, $row['aliasname'], $row['url'],
											$alt['url'], $row['state'], $feed['header'], TRUE, $alt['header'],
											$alt['info'], $alt['register'], $a_key);
									}
								}
							}

							if (!isset($aliasname_found[$aliasname])) {
								$aliasname_found[$aliasname] = $aliasname;
								$status = "&emsp;"
									. "<a href=\"/pfblockerng/pfblockerng_category_edit.php?type={$ftype}"
									. "&act=addgroup&atype={$aliasname}\""
									. "title=\"Add Alias/Group: {$aliasname}\">"
									. "<i class=\"fa fa-plus-circle\"></i>"
									. "</a>";
							}
						}

						if (isset($feed['feed'])):

							// Print table/row of Feeds and consecutive rows for all 'Alternate' URLs available.
							$counter = 0;
							if (isset($alt_feeds[$ftype][$aliasname][$feed['header']])) {
								$counter = count($alt_feeds[$ftype][$aliasname][$feed['header']]);
							}

							for ($i=0; $i <= $counter; $i++):

								// Print blank seperator line between Categories (skip first row)
								if ($feed_info_row > 0 && $ftype != $p_type) {
									print ("<tr><td><br /></td><td></td><td></td><td></td><td></td><td></td></tr>");
								}

								// Print applicable alternating background color
								if ($data['action'] == 'permit') {
									if ($aliasname == $p_aliasname) {
										$tr_style = 'background-color: #F5FBF6;';		// Light green 1
										if ($tr_style == $p_tr_style) {
											$tr_style = 'background-color: #EEF7EE;';	// Light green 2
										}
									} else {
										// Dark green (New Alias/Group)
										$tr_style = 'background-color: #A0B8A0;';	// #C8E6C9;';
									}
								}
								else {
									$tr_style = '';
									if ($aliasname != $p_aliasname) {
										// Grey - (New Alias/Group)
										$tr_style = 'background-color: #B8B8B8;';	//#D8D8D8;';
									}
								}
							?>

				<tr style="<?=$tr_style;?>">
					<td>
							<?php
								switch ($ftype) {
									case 'ipv4':
										$type = 'IPv4';
										break;
									case 'ipv6':
										$type = 'IPv6';
										break;
									case 'dnsbl':
										$type = 'DNSBL';
										break;
								}
								if ($ftype != $p_type) {
									print ("<strong>{$type} Category</strong>");
								} else {
									print ("<p style=\"font-size: 75%\">{$type}<p>");
								}
							?>
					</td>

					<td>
							<?php
								if ($aliasname != $p_aliasname) {
									// Add Line break (bullet)
									$data['info'] = str_replace('-LB-', "\n&emsp;&#9679;&emsp;", $data['info']);
									print ("<i title=\"{$data['info']}\" class=\"fa fa-info-circle\"></i>{$status}");
								}

								if ($i == 0) {

									// If alternative URLs are available, print radio checkbox options. Default to first radio.
									if (isset($alt_feeds[$ftype][$aliasname][$feed['header']])) {

										// Add first radio option as default if not previously saved 
										if (!isset($fconfig['feed_alt_' . strtolower($feed['header']) ])) {
											$feed_alt_selected[] = $feed['header'];
										}

										// Collect all 'Alternative' Feed Header names. (POST/save)
										$alt_selected .= $feed['header'] . ',';

										if (in_array($feed['header'], $feed_alt_selected) && empty($icon)) {
											$icon = "&emsp;"
												. "<a href=\"/pfblockerng/pfblockerng_category_edit.php?type={$ftype}"
												. "&act=add&atype={$feed['header']}\""
												. "title=\"Add feed: [ {$feed['header']} ]\">"
												. "<i class=\"fa fa-plus-circle\"></i>"
												. "</a>";
										}
										elseif (empty($icon)) {
											$pfb_found = FALSE;
											foreach ($alt_feeds[$ftype][$aliasname][$feed['header']] as $a_header) {
												if (!empty($a_header['icon']) || 
												    in_array($a_header['header'], $feed_alt_selected)) {
													$pfb_found = TRUE;
													break;
												}
											}

											if (!$pfb_found) {
												$icon = "&emsp;"
												. "<a href=\"/pfblockerng/pfblockerng_category_edit.php?type={$ftype}"
												. "&act=add&atype={$feed['header']}\""
												. "title=\"Add feed: [ {$feed['header']} ]\">"
												. "<i class=\"fa fa-plus-circle\"></i>"
												. "</a>";
											}
										}

										$feed_alternate	= '';
										$feed_header	= $feed['header'];
										$feed_radio	= "&emsp;<input type=\"radio\" name=\"alt_{$feed['header']}\""
												. " value=\"alt_{$feed['header']}\" checked=\"checked\" />&emsp;";

										print ("<input type=\"hidden\" name=\"alt_" . "${feed['header']}" . "\" id=\"alt_"
											. "${feed['header']}" . "\" value=\"\" />");
									}
									else {
										if (empty($icon)) {
											$icon = "&emsp;"
												. "<a href=\"/pfblockerng/pfblockerng_category_edit.php?type={$ftype}"
												. "&act=add&atype={$feed['header']}\""
												. "title=\"Add feed: [ {$feed['header']} ]\">"
												. "<i class=\"fa fa-plus-circle\"></i>"
												. "</a>";
										}

										$feed_alternate	= '';
										$feed_header	= $feed['header'];
										$feed_radio	= '';
									}
								}

								// Extract 'Alternate Feed' details from alt_feed array.
								else {
									$status = '';
									$alt_feed	= $alt_feeds[$ftype][$aliasname][$feed['header']][$i -1];
									$icon 		= $alt_feed['icon'];
	
									if (in_array($alt_feed['header'], $feed_alt_selected)) {
										$checked = 'checked=\"checked\"';
										if (empty($icon)) {
											$icon = "&emsp;"
												. "<a href=\"/pfblockerng/pfblockerng_category_edit.php?type={$ftype}"
												. "&act=add&atype={$alt_feed['header']}\""
												. "title=\"Add feed: [ {$alt_feed['header']} ]\">"
												. "<i class=\"fa fa-plus-circle\"></i>"
												. "</a>";
										}
									}
									else {
										$checked = '';
									}

									$feed['url']	= $alt_feed['url'];
									$feed_alternate = "&emsp;<i class=\"fa fa-angle-right\" style=\"cursor: default\""
											. " title=\"Alternate Feed option\"></i>&emsp;";
									$feed_header	= $alt_feed['header'];
									$feed_radio	= "&emsp;<input type=\"radio\" name=\"alt_{$feed['header']}\""
											. " value=\"alt_{$alt_feed['header']}\" {$checked} />&emsp;";
								}
							?>
					</td>

					<td>
							<?php
								if ($aliasname != $p_aliasname) {
									print ("{$aliasname}");
								} else {
									print ("<p style=\"font-size: 75%\">{$aliasname}<p>");
								}
							?>
					</td>

					<td>
						<?=$feed_alternate;?>
						<a target="_blank" href="<?=$feed['website'];?>"><?=$feed['feed'];?></a>
							<?php
								// Add any Alternate Feed icons
								if (!empty($feed_alternate)) {
									$feed['status'] 	= $alt_feed['status'];
									$feed['info']		= $alt_feed['info'];
									$feed['register']	= $alt_feed['register'];
								}

								// Print info about Feed if available
								if (isset($feed['info'])) {
									print ("&emsp;<i class=\"fa fa-info-circle icon-primary\" title=\"{$feed['info']}\"></i>");
								}

								// Print Feed offline status
								if (isset($feed['status'])) {
									print ("&emsp;<i class=\"fa fa-bug text-danger\" title=\"Feed temporarily unavailable\"></i>");
								}

								// Add link to Donate/support link
								if (isset($feed['donate'])) {
									print ("&emsp;<a target=\"_blank\" href=\"{$feed['donate']}\" title=\"Click to donation/support page.\">
									<i class=\"fa fa-cart-plus\"></i></a>");
								}

								// Add link to Feed registration
								if (isset($feed['register'])) {
									print ("&emsp;<a target=\"_blank\" href=\"{$feed['register']}\" title=\"Click to register\">
										<i class=\"fa fa-sign-in\"></i></a>");
								}
							?>
					</td>

					<td>
						<?=$feed_radio;?>
						<a target="_blank" href="<?=$feed['url'];?>"
							onclick="return confirm('Download feed [ <?=$feed['url'];?> ] ?')"><?=$feed_header;?>
						</a>
					</td>

					<td><?=$icon;?></td>
				</tr>
							<?php
								// Collect previous values
								$p_type		= $ftype;
								$p_aliasname	= $aliasname;
								$p_tr_style	= $tr_style;
								$feed_info_row++;
							endfor;
						endif;
					endforeach;
				endforeach;
			endforeach;
			?>

			</tbody>
		</table>
		</div>
	</div>
</div>

<?php $alt_selected = rtrim($alt_selected, ',');?>
<input type="hidden" name="alt_selected" id="alt_selected" value="<?=$alt_selected;?>">
</form>

<!-- Print table of Unknown User defined Feeds -->
<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext("Unknown user defined Feeds")?></h2>
	</div>
	<div id="pfb_table2" class="panel-body">
		<div class="table-responsive">
			<table id="pfb_table2" class="table table-striped table-hover table-compact sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr id="pfb_header2">
					<th><?=gettext('Category');?></th>
					<th><?=gettext('Alias/Group');?></th>
					<th><?=gettext('URL');?></th>
					<th><?=gettext('Header');?></th>
				</tr>
			</thead>
			<tbody>

				<?php
				$p_aliasname = '';
				if (!empty($ex_feeds)):
					foreach (array('ipv4' => 'IPv4', 'ipv6' => 'IPv6', 'dnsbl' => 'DNSBL') as $feedtype => $type):

						if (!empty($ex_feeds[$feedtype])):
							foreach ($ex_feeds[$feedtype] as $row):

								if (empty($row) || (isset($row['found']) && $row['found'])) {
									continue;
								}

								$tr_style = '';
								if ($row['aliasname'] != $p_aliasname) {
									$tr_style = 'background-color: #B8B8B8;';   // #D8D8D8;';
								}
				?>

			<tr style="<?=$tr_style;?>">
				<td>
					<?php
								if ($type != $p_type) {
									print ("{$type}");
								} else {
									print ("<p style=\"font-size:75%\">{$type}<p>");
								}
					?>
				</td>

				<td>
					<?php
								$title = '';
								$row['aliasname'] = htmlspecialchars($row['aliasname']);
								if (strlen($row['aliasname']) >= 15) {
									$title 			= $row['aliasname'];
									$row['aliasname']	= substr($row['aliasname'], 0, 15) . '...';
								}

								if ($row['aliasname'] != $p_aliasname) {
									$aliasname = "<a href=\"/pfblockerng/pfblockerng_category_edit.php?type={$feedtype}"
											. "&rowid={$row['rowid']}\""
											. "title=\"{$title}\">"
											. "{$row['aliasname']}</a>";
									print ($aliasname);
								}
								else {
									print ("<p style=\"font-size:75%\">{$row['aliasname']}<p>");
								}
					?>
				</td>

				<td>
					<?php
								if (strpos($row['url'], 'http') !== FALSE) {
									print ("<a target=\"_blank\" href=\"{$row['url']}\">{$row['url']}</a>");
								} else {
									print ("{$row['url']}");
								}
					?>
				</td>

				<td>
					<?php
								print ($row['header']);
					?>
				</td>
			</tr>

				<?php
							// Collect previous values
							$p_type  	= $type;
							$p_aliasname	= $row['aliasname'];
				
							endforeach;
						endif;
					endforeach;
				endif;
				?>

			<tbody>
			</table>
		</div>
	</div>
</div>
<?php include('foot.inc');?>

<script type="text/javascript">
//<![CDATA[

events.push(function() {

	$('input:radio[name^=alt_]').click(function() {
		$('#save').trigger('click');
	});
});

//]]>
</script>
