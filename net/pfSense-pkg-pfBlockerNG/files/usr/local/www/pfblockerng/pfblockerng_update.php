<?php

/* pfBlockerNG_Update.php

	pfBlockerNG
	Copyright (c) 2015 BBcan177@gmail.com
	All rights reserved.

	Portions of this code are based on original work done for
	pfSense from the following contributors:

	pkg_mgr_install.php
	Part of pfSense (https://www.pfsense.org)
	Copyright (c) 2015 Electric Sheep Fencing, LLC. All rights reserved.
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
	echo "\n<script type=\"text/javascript\">";
	echo "\n//<![CDATA[";
	echo "\nthis.document.forms[0].pfb_output.value = \"" . $text . "\";";
	echo "\nthis.document.forms[0].pfb_output.scrollTop = this.document.forms[0].pfb_output.scrollHeight;";
	echo "\n//]]>";
	echo "\n</script>";
	/* ensure that contents are written out */
	ob_flush();
}

// Post status message to terminal window.
function pfbupdate_status($status) {
	$status = str_replace("\n", "\\n", $status);
	echo "\n<script type=\"text/javascript\">";
	echo "\n//<![CDATA[";
	echo "\nthis.document.forms[0].pfb_status.value=\"" . $status . "\";";
	echo "\n//]]>";
	echo "\n</script>";
	/* ensure that contents are written out */
	ob_flush();
}


// Function to perform a Force Update, Cron or Reload
function pfb_cron_update($type) {
	global $pfb;

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
		$reload_type = htmlspecialchars($_POST['rmode']);
		pfbupdate_status(gettext("Running Force Reload Task - {$reload_type}"));

		switch ($reload_type) {
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
	mwexec_bg("/usr/local/bin/php-cgi /usr/local/www/pfblockerng/pfblockerng.php {$type} >> {$pfb['log']} 2>&1");

	// Execute Live Tail function
	pfb_livetail($pfb['log'], 'force');
}


$pgtitle = gettext('pfBlockerNG: Update');
include_once('head.inc');
include_once('fbegin.inc');
?>
<body link="#000000" vlink="#0000CC" alink="#000000">
<form action="/pfblockerng/pfblockerng_update.php" method="post">
	<table width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr>
			<td>
				<?php
					$tab_array = array();
					$tab_array[] = array(gettext("General"), false, "/pkg_edit.php?xml=pfblockerng.xml");
					$tab_array[] = array(gettext("Update"), true, "/pfblockerng/pfblockerng_update.php");
					$tab_array[] = array(gettext("Alerts"), false, "/pfblockerng/pfblockerng_alerts.php");
					$tab_array[] = array(gettext("Reputation"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_reputation.xml");
					$tab_array[] = array(gettext("IPv4"), false, "/pkg.php?xml=/pfblockerng/pfblockerng_v4lists.xml");
					$tab_array[] = array(gettext("IPv6"), false, "/pkg.php?xml=/pfblockerng/pfblockerng_v6lists.xml");
					$tab_array[] = array(gettext("DNSBL"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_dnsbl.xml");
					$tab_array[] = array(gettext("Country"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_top20.xml");
					$tab_array[] = array(gettext("Logs"), false, "/pfblockerng/pfblockerng_log.php");
					$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_sync.xml");
					display_top_tabs($tab_array, true);
				?>
			</td>
		</tr>
	</table>
	<div id="mainareapkg">
		<table id="maintable" class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="2">
			<tr>
				<td colspan="2" class="vncell" align="left"><?php echo gettext("LINKS :"); ?>&emsp;
					<a href='/firewall_aliases.php' target="_blank"><?php echo gettext("Firewall Alias"); ?></a>&emsp;
					<a href='/firewall_rules.php' target="_blank"><?php echo gettext("Firewall Rules"); ?></a>&emsp;
					<a href='/diag_logs_filter.php' target="_blank"><?php echo gettext("Firewall Logs"); ?></a><br />
				</td>
			</tr>
			<tr>
				<td colspan="2" class="listtopic"><?php echo gettext("CRON Status"); ?></td>
			</tr>
			<tr>
				<td colspan="2" class="listr">
				<?php

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

					echo "NEXT Scheduled CRON Event will run at <font size=\"3\">&nbsp;{$cronreal}</font>&nbsp; with
						<font size=\"3\"><span class=\"red\">&nbsp;{$nextcron}&nbsp;</span></font> time remaining.";

					// Query for any active pfBlockerNG CRON jobs
					exec('/bin/ps -wax', $result_cron);
					if (preg_grep("/pfblockerng[.]php\s+?(cron|update)/", $result_cron)) {
						echo "<font size=\"2\"><span class=\"red\">&emsp;&emsp;
							Active pfBlockerNG CRON Job </span></font>&emsp;";
						echo "<img src = '/themes/{$g['theme']}/images/icons/icon_pass.gif' alt='' width='15' height='15'
							border='0' title='pfBockerNG Cron Task is Running.'/>";
					}
					echo "<br /><font size=\"3\"><span class=\"red\">Refresh</span></font> to update current Status and time remaining";
				?>
				</td>
			</tr>
			<tr>
				<td colspan="2" class="vncell"><?php echo gettext("<br />"); ?></td>
			</tr>
			<tr>
				<td colspan="2" class="listtopic"><?php echo gettext("Update Options"); ?></td>
			</tr>
			<tr>
				<td colspan="2" class="listr">
					<!-- Update Option Text -->
					<?php echo "<span class='red'><strong>" . gettext("** AVOID ** ") . "&nbsp;" . "</strong></span>" .
						gettext("Running these Options - when CRON is expected to RUN!") . gettext("<br /><br />") .
						"<strong>" . gettext("Force Update") . "</strong>" . gettext(" will download any new Alias/Lists.") .
						gettext("<br />") . "<strong>" . gettext("Force Cron") . "</strong>" .
						gettext(" will download any Alias/Lists that are within the Frequency Setting (due for Update).") . gettext("<br />") .
						"<strong>" . gettext("Force Reload") . "</strong>" .
						gettext("  will reload all Lists using the existing Downloaded files.") .
						gettext(" This is useful when Lists are out of 'sync' or Reputation changes were made.") ;?><br />
				</td>
			</tr>
			<tr>
				<td colspan="2" class="vncell">
					<!-- Update Option Buttons -->
					<input type="submit" class="formbtns" name="pfbupdate" id="pfbupdate" value="Force Update" 
						title="<?=gettext("Run Force Update");?>" />
					<input type="submit" class="formbtns" name="pfbcron" id="pfbcron" value="Force Cron" 
						title="<?=gettext("Run Force Cron Update");?>" />
					<input type="submit" class="formbtns" name="pfbreload" id="pfbreload" value="Force Reload" 
						title="<?=gettext("Run Force Reload");?>" />&emsp;<?=gettext("Reload Options:");?>
					<input name="rmode" type="radio" value="All" <?php echo 'checked'; ?> /><?=gettext("All");?>
					<input name="rmode" type="radio" value="IP" /><?=gettext("IP");?>
					<input name="rmode" type="radio" value="DNSBL" /><?=gettext("DNSBL"); ?>
				</td>
			</tr>
			<tr>
				<td colspan="2" class="vncell"><?php echo gettext("<br />"); ?></td>
			</tr>
			<tr>
				<td colspan="2" class="listtopic"><?php echo gettext("Live Log Viewer only"); ?></td>
			</tr>
			<tr>
				<td colspan="2" class="listr"><?php echo gettext("Selecting 'Live Log Viewer' will allow viewing a running Cron Update"); ?></td>
			</tr>
			<tr>
				<td colspan="2" class="vncell">
					<!-- Log Viewer Buttons -->
					<input type="submit" class="formbtns" name="pfbview" id="pfbview" value="VIEW" 
						title="<?=gettext("VIEW pfBlockerNG LOG");?>"/>
					<input type="submit" class="formbtns" name="pfbviewcancel" id="pfbviewcancel" value="End View" 
						title="<?=gettext("END VIEW of pfBlockerNG LOG");?>"/>
					<?php echo "&emsp;" . gettext(" Select 'view' to open ") . "<strong>" . gettext(' pfBlockerNG ') . "</strong>" .
						gettext(" Log. &emsp; (Select 'End View' to terminate the viewer.)"); ?><br /><br />
				</td>
			</tr>
			<tr>
				<td class="tabcont" align="left">
					<!-- status box -->
					<textarea cols="90" rows="1" name="pfb_status" id="pfb_status"
						wrap="hard"><?=gettext("Log Viewer Standby");?></textarea>
				</td>
			</tr>
			<tr>
				<td>
					<!-- command output box -->
					<textarea cols="90" rows="35" name="pfb_output" id="pfb_output" wrap="hard"></textarea>
				</td>
			</tr>
		</table>
	</div>

<?php
include('fend.inc');


// Execute the viewer output window
if (isset($_POST['pfbview'])) {
	pfbupdate_status(gettext("Log Viewing in progress.    ** Press 'END VIEW' to Exit ** "));
	pfb_livetail($pfb['log'], 'view');
}

// End the viewer output Window
if (isset($_POST['pfbviewcancel'])) {
	clearstatcache(false, $pfb['log']);
	ob_flush();
	flush();
	fclose("{$pfb['log']}");
}

// Execute a Force Update 
if (isset($_POST['pfbupdate']) && $pfb['enable'] == 'on') {
	pfb_cron_update(update);
}

// Execute a CRON command to update any lists within the frequency settings
if (isset($_POST['pfbcron']) && $pfb['enable'] == 'on') {
	pfb_cron_update(cron);
}

// Execute a reload of all aliases and lists
if (isset($_POST['pfbreload']) && $pfb['enable'] == 'on') {
	// Set 'Reuse' flag for reload process
	$config['installedpackages']['pfblockerng']['config'][0]['pfb_reuse'] = 'on';
	write_config('pfBlockerNG: Executing Force Reload');
	pfb_cron_update(reload);
}

?>
</form>
</body>
</html>