<?php
/*
 * pfblockerng_update.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016-2022 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2022 BBcan177@gmail.com
 * All rights reserved.
 *
 * Portions of this code are based on original work done for
 * pfSense from the following contributors:
 *
 * pkg_mgr_install.php
 * Part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2005 Colin Smith
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

// Disable NGINX output buffering
header("X-Accel-Buffering: no");

require_once('guiconfig.inc');
require_once('globals.inc');
require_once('pfsense-utils.inc');
require_once('functions.inc');
require_once('util.inc');
require_once('/usr/local/pkg/pfblockerng/pfblockerng.inc');

pfb_global();

// Collect pfBlockerNG log file and post live output to terminal window.
function pfbupdate_output($text) {
	$text = htmlspecialchars(str_replace("\n", "\\n", $text));
	print("\n<script type=\"text/javascript\">");
	print("\n//<![CDATA[");
	print("\nthis.document.forms[0].pfb_output.value = \"" . $text . "\";");
	print("\nthis.document.forms[0].pfb_output.scrollTop = this.document.forms[0].pfb_output.scrollHeight;");
	print("\n//]]>");
	print("\n</script>");
	/* ensure that contents are written out */
	ob_flush();
}


// Post status message to terminal window.
function pfbupdate_status($status) {
	$status = htmlspecialchars(str_replace("\n", "\\n", $status));
	print("\n<script type=\"text/javascript\">");
	print("\n//<![CDATA[");
	print("\nthis.document.forms[0].pfb_status.value=\"" . $status . "\";");
	print("\n//]]>");
	print("\n</script>");
	/* ensure that contents are written out */
	ob_flush();
}


// Function to perform a Force Update, Cron or Reload
function pfb_cron_update($type) {
	global $pfb, $pconfig;

	// Query for any active pfBlockerNG CRON jobs
	exec('/bin/ps -wx', $result_cron);
	if (preg_grep("/pfblockerng[.]php\s+?(cron|update)/", $result_cron)) {
		pfbupdate_status(gettext("Force {$type} Terminated - Failed due to Active Running Task. Click 'View' for running process"));
		exit;
	}

	if (!file_exists("{$pfb['log']}")) {
		touch("{$pfb['log']}");
	}

	// Update status window with correct task
	if ($type == 'update') {
		pfbupdate_status(gettext('Running Force Update Task'));
	} elseif ($type == 'reload') {
		pfbupdate_status(gettext("Running Force Reload Task - {$pconfig['pfb_reload_option']}"));
		switch ($pconfig['pfb_reload_option']) {
			case 'IP':
				$type = 'updateip';
				break;
			case 'DNSBL':
				$type = 'updatednsbl';
				rmdir_recursive("{$pfb['dnsdir']}");
				break;
			case 'All':
			default:
				$type = 'update';
				rmdir_recursive("{$pfb['dnsdir']}");
		}
	} else {
		pfbupdate_status(gettext('Running Force CRON Task'));
	}

	// Remove any existing pfBlockerNG CRON Jobs
	install_cron_job('pfblockerng.php cron', false);

	// Execute PHP process in the background
	pfb_logger("\n [ Force Reload Task - {$pconfig['pfb_reload_option']} ]\n", 1);
	mwexec_bg("/usr/local/bin/php /usr/local/www/pfblockerng/pfblockerng.php {$type} >> {$pfb['log']} 2>&1");

	// Execute Live Tail function
	pfb_livetail($pfb['log'], 'force');
}

$pgtitle = array(gettext('Firewall'), gettext('pfBlockerNG'), gettext('Update'));
$pglinks = array('', '/pfblockerng/pfblockerng_general.php', '@self');
include_once('head.inc');

$pconfig = array();
if ($_POST) {
	$pconfig = $_POST;
}

// Load Wizard settings and reload pfBlockerNG
$pfb_wizard = FALSE;
if (isset($_GET) && isset($_GET['wizard']) && $_GET['wizard'] == 'reload') {
	$pconfig['run']			= '';
	$pconfig['pfb_force']		= 'reload';
	$pconfig['pfb_reload_option']	= 'All';
	$pfb_wizard			= TRUE;
}

// Define default Alerts Tab href link (Top row)
$get_req = pfb_alerts_default_page();

$tab_array	= array();
$tab_array[]	= array(gettext('General'),	false,	'/pfblockerng/pfblockerng_general.php');
$tab_array[]	= array(gettext('IP'),		false,	'/pfblockerng/pfblockerng_ip.php');
$tab_array[]	= array(gettext('DNSBL'),	false,	'/pfblockerng/pfblockerng_dnsbl.php');
$tab_array[]	= array(gettext('Update'),	true,	'/pfblockerng/pfblockerng_update.php');
$tab_array[]	= array(gettext('Reports'),	false,	"/pfblockerng/pfblockerng_alerts.php{$get_req}");
$tab_array[]	= array(gettext('Feeds'),	false,	'/pfblockerng/pfblockerng_feeds.php');
$tab_array[]	= array(gettext('Logs'),	false,	'/pfblockerng/pfblockerng_log.php');
$tab_array[]	= array(gettext('Sync'),	false,	'/pfblockerng/pfblockerng_sync.php');
display_top_tabs($tab_array, true);

if ($pfb['enable'] == 'on') {
	/* Legend - Time variables

	$pfb['interval']	Hour interval setting	(1,2,3,4,6,8,12,24)
	$pfb['min']		Cron minute start time	(0-23)
	$pfb['hour']		Cron start hour		(0-23)
	$pfb['24hour']		Cron daily/wk start hr	(0-23)

	$currenthour		Current hour
	$currentmin		Current minute
	$currentsec		Current second
	$currentdaysec		Total number of seconds elapsed so far in the day
	$cron_hour_begin	First cron hour setting (interval 2-24)
	$cron_hour_next		Next cron hour setting  (interval 2-24)

	$nextcron		Next cron event in hour:mins
	$cronreal		Time remaining to next cron in hours:mins:secs		*/

	$currenthour	= date('G');
	$currentmin	= date('i');
	$currentsec	= date('s');
	$currentdaysec	= ($currenthour * 3600) + ($currentmin * 60) + $currentsec;

	if ($pfb['interval'] == 1) {
		if ($currentmin < $pfb['min']) {
			$cron_hour_next = $currenthour;
		} else {
			$cron_hour_next = ($currenthour + 1) % 24;
		}
	}
	elseif ($pfb['interval'] == 24) {
		$cron_hour_next = $cron_hour_begin = $pfb['24hour'] ?: '00';
	}
	else {
		// Find next cron hour schedule
		$crondata = pfb_cron_base_hour($pfb['interval']);
		$cron_hour_begin = 0;
		$cron_hour_next  = '';
		if (!empty($crondata)) {
			foreach ($crondata as $key => $line) {
				if ($key == 0) {
					$cron_hour_begin = $line;
				}
				if (($line * 3600) + ($pfb['min'] * 60) > $currentdaysec) {
					$cron_hour_next = $line;
					break;
				}
			}
		}
		// Roll over to the first cron hour setting
		if (empty($cron_hour_next)) {
			$cron_hour_next = $cron_hour_begin;
		}
	}

	$cron_seconds_next = ($cron_hour_next * 3600) + ($pfb['min'] * 60);
	if ($currentdaysec < $cron_seconds_next) {
		// The next cron job is ahead of us in the day
		$sec_remain = $cron_seconds_next - $currentdaysec;
	} else {
		// The next cron job is tomorrow
		$sec_remain = (24*60*60) + $cron_seconds_next - $currentdaysec;
	}

	// Ensure hour:min:sec variables are two digit
	$pfb['min']	= str_pad($pfb['min'], 2, '0', STR_PAD_LEFT);
	$sec_final	= str_pad(($sec_remain % 60),  2, '0', STR_PAD_LEFT);
	$min_remain	= str_pad(floor($sec_remain / 60), 2, '0', STR_PAD_LEFT);
	$min_final	= str_pad(($min_remain % 60), 2, '0', STR_PAD_LEFT);
	$hour_final	= str_pad(floor($min_remain / 60), 2, '0', STR_PAD_LEFT);
	$cron_hour_next = str_pad($cron_hour_next, 2, '0', STR_PAD_LEFT);

	$cronreal = "{$cron_hour_next}:{$pfb['min']}";
	$nextcron = "{$hour_final}:{$min_final}:{$sec_final}";
}

$pfb_cmd = "/usr/local/bin/php /usr/local/www/pfblockerng/pfblockerng.php cron >> {$pfb['log']} 2>&1";
if ($pfb['interval'] == 1) {
	$pfb_hour = '*';
} elseif ($pfb['interval'] == 24) {
	$pfb_hour = $pfb['24hour'];
} else {
	$pfb_hour = implode(',', pfb_cron_base_hour($pfb['interval']));
}

// Determine if CRON job is missing
if ($pfb['enable'] == 'on' && $pfb['interval'] != 'Disabled' &&
    !pfblockerng_cron_exists($pfb_cmd, $pfb['min'], $pfb_hour, '*', '*')) {
	$cronreal = ' [ Missing cron task ]';
	$nextcron = '--';
}

// Determine if CRON job is disabled
elseif (empty($pfb['enable']) || empty($cron_hour_next) || $pfb['interval'] == 'Disabled') {
	$cronreal = ' [ Disabled ]';
	$nextcron = '--';
}

$status = 'NEXT Scheduled CRON Event will run at'
	. "&emsp;<strong>{$cronreal}</strong>&emsp;with<strong><span style=\"color: red;\">&emsp;{$nextcron}"
	. '&emsp;</span></strong> time remaining.';

// Query for any active pfBlockerNG CRON jobs
exec('/bin/ps -wax', $result_cron);
if (preg_grep("/pfblockerng[.]php\s+?(cron|update)/", $result_cron)) {
	$status = '<span style="color: red;">&emsp;&emsp;'
		. 'Active pfBlockerNG CRON JOB'
		. '</span>&emsp;<i class="fa fa-spinner fa-pulse fa-lg"></i>';
}
$status .= '<br />&emsp;<small><span style="color: red;">Refresh to update current status and time remaining.</span></small>';

$options = '<div class="infoblock"><dl class="dl-horizontal">'
	. '	<dt>Update:</dt><dd>will process new changes and download new Alias/Lists.</dd>'
	. '	<dt>Cron:</dt><dd>will download any Alias/Lists that are within the Frequency Setting (due for Update).</dd>'
	. '	<dt>Reload:</dt><dd>will reload all Lists using the existing Downloaded files.<br />'
	. '	This is useful when Lists are out of <q>sync</q>, Whitelisting, Blacklisting, Suppression, TLD or Reputation changes were made.</dd>'
	. '</dl></div>';

// Create Form
$form = new Form(false);

$section = new Form_Section('Update Settings');
$section->addInput(new Form_StaticText(
	'Links',
	'<small>'
	. '<a href="/firewall_aliases.php" target="_blank">Firewall Aliases</a>&emsp;'
	. '<a href="/firewall_rules.php" target="_blank">Firewall Rules</a>&emsp;'
	. '<a href="/status_logs_filter.php" target="_blank">Firewall Logs</a></small>'
));

// Build Status section
$section->addInput(new Form_StaticText(
	'Status',
	$status
));
$form->add($section);

// Build Options section
$group = new Form_Group('Force Options');
$group->add(new Form_StaticText(
	NULL,
	'<span style="color: red;">** AVOID ** </span>&nbsp;Running these <q>Force</q> options - when CRON is expected to RUN!&emsp;'
	. $options
));

$section->add($group);

$group = new Form_Group('Select \'Force\' option');
$group->add(new Form_Checkbox(
	'pfb_force',
	NULL,
	'Update',
	TRUE,
	'update'
))->displayAsRadio('pfb_force_update')->setAttribute('title', 'Force Update: IP & DNSBL.')->setWidth(1);

$group->add(new Form_Checkbox(
	'pfb_force',
	NULL,
	'Cron',
	FALSE,
	'cron'
))->displayAsRadio('pfb_force_cron')->setAttribute('title', 'Force Cron: IP & DNSBL.')->setWidth(1);

$group->add(new Form_Checkbox(
	'pfb_force',
	NULL,
	'Reload',
	FALSE,
	'reload'
))->displayAsRadio('pfb_force_reload')->setAttribute('title', 'Force Reload: IP & DNSBL.')->setWidth(1);
$section->add($group);


// Build 'Force Options' group section
$group = new Form_Group('Select \'Reload\' option');
$group->add(new Form_Checkbox(
	'pfb_reload_option',
	NULL,
	'All',
	TRUE,
	'All'
))->displayAsRadio('pfb_reload_option_all')->setAttribute('title', 'Reload: IP & DNSBL.')->setWidth(1);

$group->add(new Form_Checkbox(
	'pfb_reload_option',
	NULL,
	'IP',
	FALSE,
	'IP'
))->displayAsRadio('pfb_reload_option_ip')->setAttribute('title', 'Reload: IP only.')->setWidth(1);

$group->add(new Form_Checkbox(
	'pfb_reload_option',
	NULL,
	'DNSBL',
	FALSE,
	'DNSBL'
))->displayAsRadio('pfb_reload_option_dnsbl')->setAttribute('title', 'Reload: DNSBL only.')->setWidth(1);
$section->add($group);


$group = new Form_Group(NULL);
$btn_run = new Form_Button(
	'run',
	'Run',
	NULL,
	'fa-play-circle'
);
$btn_run->removeClass('btn-primary')->addClass('btn-primary btn-xs')->setWidth(1);

// Alternate view/end view button text
if (!isset($pconfig['log_view'])) {
	$pconfig['log_view'] = 'View';
} elseif($pconfig['log_view'] == 'View') {
	$pconfig['log_view'] = 'End View' ;
} else {
	$pconfig['log_view'] = 'View';
}

// Alternate view/end view title text
$btn_logview_title = 'Click to End Log View';
if ($pconfig['log_view'] == 'View') {
	$btn_logview_title = 'Click to View a running Cron Update.';
}

$btn_logview = new Form_Button(
	'log_view',
	$pconfig['log_view'],
	NULL,
	'fa-play-circle-o'
);
$btn_logview->removeClass('btn-primary')->addClass('btn-primary btn-xs')->setWidth(1)
	    ->setAttribute('title', $btn_logview_title);
$group->add(new Form_StaticText(
		NULL,
		$btn_run . $btn_logview
));

$section->add($group);

// Build 'textarea' windows
$section = new Form_Section('Log');
$section->addInput(new Form_Textarea(
	'pfb_status',
	NULL,
	'Log Viewer Standby'
))->removeClass('form-control')->addClass('row-fluid col-sm-12')->setAttribute('rows', '1')->setAttribute('wrap', 'off')
  ->setAttribute('style', 'background:#fafafa; width: 100%');

$section->addInput(new Form_Textarea(
	'pfb_output',
	NULL,
	NULL
))->removeClass('form-control')->addClass('row-fluid col-sm-12')->setAttribute('rows', '30')->setAttribute('wrap', 'off')
  ->setAttribute('style', 'background:#fafafa; width: 100%');

$form->add($section);
print($form);

// Execute the viewer output window
if (isset($pconfig['log_view'])) {
	if ($pconfig['log_view'] !== 'View') {
		pfbupdate_status(gettext("Log Viewing in progress.    ** Press 'END VIEW' to Exit ** "));
		pfb_livetail($pfb['log'], 'view');
	} else {
		// End the viewer output Window
		clearstatcache(false, $pfb['log']);
		ob_flush();
		flush();
		@fclose("{$pfb['log']}");
	}
}

if ($pfb['enable'] == 'on' && isset($pconfig['run']) && !empty($pconfig['pfb_force'])) {
	// Run appropriate 'Force command'
	if ($pconfig['pfb_force'] == 'update') {
		pfb_cron_update('update');
	} elseif ($pconfig['pfb_force'] == 'cron') {
		pfb_cron_update('cron');
	} elseif ($pconfig['pfb_force'] == 'reload') {
		$config['installedpackages']['pfblockerng']['config'][0]['pfb_reuse'] = 'on';
		write_config('pfBlockerNG: Running Force Reload');
		pfb_cron_update('reload');
	}

	if ($pfb_wizard) {

		$wizard_log =
'<div class="pull-left alert alert-info clearfix" style="width: 100%;" role="alert">
	<p>pfBlockerNG has been successfully configured and updated. This installation will now block IPs based on some recommended
		Feed source providers. It will also block most ADverts based on Feed sources including EasyList/EasyPrivacy. Some additional
		Feed source providers include some malicious domain blocking.</p>
	<p>Please note that this is an entry level configuration for pfBlockerNG IP and DNSBL components. It is designed to allow new
		users to get running quickly to learn how effective pfBlockerNG can be for their networks.</p>
	<p>The Feeds tab includes many different types of IP and DNSBL feed sources. Careful review should be completed to select which feeds are
		appropriate for your needs.</p><br />
	<p><u>NOTE</u>:</p><br />
	<ul>
		<li>Please review the update log above for any errors.</li>
		<li>For DNSBL, ensure that all of your LAN devices are pointed at pfSense ONLY for DNS resolution.</li>
		<li>For users who have VLANS, please enable the DNSBL permit firewall rule option to allow all subnets to access the
			DNSBL Webserver, or there may be some browser timeouts.</li>
		<li>All IP/DNSBL events will be reported to the Reports/Alerts Tab. You can whitelist from the Alerts tab directly.</li>
		<li>Review the Reports/Statistics tabs for an in-depth summary of all IP and DNSBL events</li>
	</ul><br />
	<p>The Wizard is now finalized!</p>
	<p><small>A copy of this message has been saved to the wizard.log file</small></p>
</div>';
		print ("{$wizard_log}");

		$wizard_log = str_replace(array("\x09", '</p><br />'), array('', '<br />'), $wizard_log);
		$wizard_log = str_replace(array('</p>', '<br />'), "\n", $wizard_log);
		$wizard_log = str_replace('<li>', ' - ', $wizard_log);
		$wizard_log = strip_tags($wizard_log);
		@file_put_contents('/var/log/pfblockerng/wizard.log', "{$wizard_log}", LOCK_EX);
	}
}
?>

<script type="text/javascript">
//<![CDATA[

events.push(function(){

	// Expand textarea to full width
	$('label[class="col-sm-2 control-label"]:eq(6)').remove();
	$('div[class="col-sm-10"]:eq(4), div[class="col-sm-10"]:eq(5)').removeClass('col-sm-10').addClass('col-sm-12');

	// Hide/Show 'Force Reload' radios
	function mode_change(mode) {
		if (mode == 'on') {
			hideCheckbox('pfb_reload_option_all', false);
		} else {
			hideCheckbox('pfb_reload_option_all',  true);
		}
	}
	mode_change();

	// On-click - toggle radios on/off
	$('#pfb_force_update').click(function() {
		$('#pfb_force_cron').prop('checked', false);
		$('#pfb_force_reload').prop('checked', false);
		mode_change();
	});
	$('#pfb_force_cron').click(function() {
		$('#pfb_force_update').prop('checked', false);
		$('#pfb_force_reload').prop('checked', false);
		mode_change();
	});
	$('#pfb_force_reload').click(function() {
		$('#pfb_force_update').prop('checked', false);
		$('#pfb_force_cron').prop('checked', false);
		mode_change('on');
	});

	// On-click - toggle 'Reload' radios on/off
	$('#pfb_reload_option_all').click(function() {
		$('#pfb_reload_option_ip').prop('checked', false);
		$('#pfb_reload_option_dnsbl').prop('checked', false);
	});
	$('#pfb_reload_option_ip').click(function() {
		$('#pfb_reload_option_all').prop('checked', false);
		$('#pfb_reload_option_dnsbl').prop('checked', false);
	});
	$('#pfb_reload_option_dnsbl').click(function() {
		$('#pfb_reload_option_all').prop('checked', false);
		$('#pfb_reload_option_ip').prop('checked', false);
	});

	// Scroll to the bottom of the page
	var pfb_wizard = "<?=$pfb_wizard;?>";
	if (pfb_wizard) {
		$("html, body").animate({ scrollTop: $(document).height() }, 2000);
	}

	// Scroll to the bottom of the page
	$('#run').click(function() {
		$("html, body").animate({ scrollTop: $(document).height() }, 2000);
	});
});
//]]>
</script>
<?php include('foot.inc'); ?>
