<?php

/* pfBlockerNG_Update.php

	pfBlockerNG
	Copyright (c) 2015-2016 BBcan177@gmail.com
	All rights reserved.

	Portions of this code are based on original work done for
	pfSense from the following contributors:

	pkg_mgr_install.php
	Part of pfSense (https://www.pfsense.org)
	Copyright (c) 2016 Electric Sheep Fencing, LLC. All rights reserved.
	Copyright (c) 2005 Colin Smith
	Copyright (c) 2004-2005 Scott Ullrich
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
	$text = str_replace("\n", "\\n", $text);
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
	$status = str_replace("\n", "\\n", $status);
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
	if (preg_grep("/pfblockerng[.]php\s+?(cron|update|updatednsbl)/", $result_cron)) {
		pfbupdate_status(gettext("Force {$type} Terminated - Failed due to Active Running Task. Click 'View' for running process"));
		header('Location: /pfblockerng/pfblockerng_update.php');
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
	mwexec_bg("/usr/local/bin/php /usr/local/www/pfblockerng/pfblockerng.php {$type} >> {$pfb['log']} 2>&1");

	// Execute Live Tail function
	pfb_livetail($pfb['log'], 'force');
}

$pgtitle = array(gettext('Firewall'), gettext('pfBlockerNG'), gettext('Update'));
include_once('head.inc');

$pconfig = array();
if ($_POST) {
	$pconfig = $_POST;
}
if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$tab_array	= array();
$tab_array[]	= array(gettext("General"), false, "/pkg_edit.php?xml=pfblockerng.xml");
$tab_array[]	= array(gettext("Update"), true, "/pfblockerng/pfblockerng_update.php");
$tab_array[]	= array(gettext("Alerts"), false, "/pfblockerng/pfblockerng_alerts.php");
$tab_array[]	= array(gettext("Reputation"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_reputation.xml");
$tab_array[]	= array(gettext("IPv4"), false, "/pkg.php?xml=/pfblockerng/pfblockerng_v4lists.xml");
$tab_array[]	= array(gettext("IPv6"), false, "/pkg.php?xml=/pfblockerng/pfblockerng_v6lists.xml");
$tab_array[]	= array(gettext("DNSBL"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_dnsbl.xml");
$tab_array[]	= array(gettext("Country"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_top20.xml");
$tab_array[]	= array(gettext("Logs"), false, "/pfblockerng/pfblockerng_log.php");
$tab_array[]	= array(gettext("Sync"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_sync.xml");
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
		$cron_hour_next = $cron_hour_begin = !empty($pfb['24hour']) ?: '00';
	}
	else {
		// Find next cron hour schedule
		$crondata = pfb_cron_base_hour();
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

if (empty($pfb['enable']) || empty($cron_hour_next)) {
	$cronreal = ' [ Disabled ]';
	$nextcron = '--';
}

$status  = 'NEXT Scheduled CRON Event will run at';
$status .= "&emsp;<strong>{$cronreal}</strong>&emsp;with<strong><font color=\"red\">&emsp;{$nextcron}";
$status .= '&emsp;</font></strong> time remaining.</font>';

// Query for any active pfBlockerNG CRON jobs
exec('/bin/ps -wax', $result_cron);
if (preg_grep("/pfblockerng[.]php\s+?(cron|update|updatednsbl)/", $result_cron)) {
	$status .= '<font color="red">&emsp;&emsp;';
	$status .= 'Active pfBlockerNG CRON JOB';
	$status .= '</font>&emsp;<i class="fa fa-spinner fa-pulse fa-lg"></i>';
}
$status .= '<br />&emsp;<small><font color="red">Refresh to update current status and time remaining.</font></small>';

$options  = '<div class="infoblock"><dl class="dl-horizontal">';
$options .= '	<dt>Update:</dt><dd>will download any new Alias/Lists.</dd>';
$options .= '	<dt>Cron:</dt><dd>will download any Alias/Lists that are within the Frequency Setting (due for Update).</dd>';
$options .= '	<dt>Reload:</dt><dd>will reload all Lists using the existing Downloaded files.<br />';
$options .= '		This is useful when Lists are out of <q>sync</q> or Reputation changes were made.</dd>';
$options .= '</dl></div>';

// Create Form
$form = new Form(false);
$form->setAction('/pfblockerng/pfblockerng_update.php');

$section = new Form_Section('Update Settings');
$section->addInput(new Form_StaticText(
	NULL,
	'<small>'
	. '<a href="/firewall_aliases.php" target="_blank">Firewall Alias</a>&emsp;'
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
	'<font color="red">** AVOID ** </font>&nbsp;Running these <q>Force</q> options - when CRON is expected to RUN!&emsp;'
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
	pfb_status,
	NULL,
	'Log Viewer Standby'
))->removeClass('form-control')->addClass('row-fluid col-sm-12')->setAttribute('rows', '1')->setAttribute('wrap', 'off')
  ->setAttribute('style', 'background:#fafafa;');

$section->addInput(new Form_Textarea(
	pfb_output,
	NULL,
	NULL
))->removeClass('form-control')->addClass('row-fluid col-sm-12')->setAttribute('rows', '30')->setAttribute('wrap', 'off')
  ->setAttribute('style', 'background:#fafafa;');

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
	// Execute appropriate 'Force command' 
	if ($pconfig['pfb_force'] == 'update') {
		pfb_cron_update(update);
	} elseif ($pconfig['pfb_force'] == 'cron') {
		pfb_cron_update(cron);
	} elseif ($pconfig['pfb_force'] == 'reload') {
		$config['installedpackages']['pfblockerng']['config'][0]['pfb_reuse'] = 'on';
		write_config('pfBlockerNG: Executing Force Reload');
		pfb_cron_update(reload);
	}
}

?>

<script type="text/javascript">
//<![CDATA[

events.push(function(){

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

	$('#run').click(function() {
		// Scroll to the bottom of the page
		$("html, body").animate({ scrollTop: $(document).height() }, 2000);
	});
});
//]]>
</script>
<?php include("foot.inc"); ?>
