<?php
/*
 * snort_interfaces_global.php
 * part of pfSense
 *
 * Copyright (C) 2003-2006 Manuel Kasper <mk@neon1.net>.
 * Copyright (C) 2011-2012 Ermal Luci
 * Copyright (C) 2014 Bill Meeks
 * All rights reserved.
 *
 * Copyright (C) 2008-2009 Robert Zelaya
 * Modified for the Pfsense snort package.
 * All rights reserved.
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

global $g;

$snortdir = SNORTDIR;
$snort_openappdir = SNORT_APPID_ODP_PATH;

// Grab any previous input values if doing a SAVE operation
if ($_POST['save'])
	$pconfig = $_POST;
else {
	$pconfig['snortdownload'] = $config['installedpackages']['snortglobal']['snortdownload'] == "on" ? 'on' : 'off';
	$pconfig['oinkmastercode'] = $config['installedpackages']['snortglobal']['oinkmastercode'];
	$pconfig['etpro_code'] = $config['installedpackages']['snortglobal']['etpro_code'];
	$pconfig['emergingthreats'] = $config['installedpackages']['snortglobal']['emergingthreats'] == "on" ? 'on' : 'off';
	$pconfig['emergingthreats_pro'] = $config['installedpackages']['snortglobal']['emergingthreats_pro'] == "on" ? 'on' : 'off';
	$pconfig['rm_blocked'] = $config['installedpackages']['snortglobal']['rm_blocked'];
	$pconfig['autorulesupdate7'] = $config['installedpackages']['snortglobal']['autorulesupdate7'];
	$pconfig['rule_update_starttime'] = $config['installedpackages']['snortglobal']['rule_update_starttime'];
	$pconfig['forcekeepsettings'] = $config['installedpackages']['snortglobal']['forcekeepsettings'] == "on" ? 'on' : 'off';
	$pconfig['snortcommunityrules'] = $config['installedpackages']['snortglobal']['snortcommunityrules'] == "on" ? 'on' : 'off';
	$pconfig['clearblocks'] = $config['installedpackages']['snortglobal']['clearblocks'] == "on" ? 'on' : 'off';
	$pconfig['verbose_logging'] = $config['installedpackages']['snortglobal']['verbose_logging'] == "on" ? 'on' : 'off';
	$pconfig['openappid_detectors'] = $config['installedpackages']['snortglobal']['openappid_detectors'] == "on" ? 'on' : 'off';
	$pconfig['hide_deprecated_rules'] = $config['installedpackages']['snortglobal']['hide_deprecated_rules'] == "on" ? 'on' : 'off';
}

/* Set sensible values for any empty default params */
if (!isset($pconfig['rule_update_starttime']))
	$pconfig['rule_update_starttime'] = '00:05';
if (!isset($config['installedpackages']['snortglobal']['forcekeepsettings']))
	$pconfig['forcekeepsettings'] = 'on';

/* Grab OpenAppID version info if enabled and downloaded */
if ($pconfig['openappid_detectors'] == "on") {
	if (file_exists("{$snort_openappdir}odp/version.conf")) {
		$openappid_ver = gettext("Installed Detection Package ");
		$openappid_ver .= gettext(ucfirst(strtolower(file_get_contents("{$snort_openappdir}odp/version.conf"))));
	}
	else
		$openappid_ver = gettext("N/A (Not Downloaded)");
}

if ($_POST['rule_update_starttime']) {
	if (!preg_match('/^([01]?[0-9]|2[0-3]):?([0-5][0-9])$/', $_POST['rule_update_starttime']))
		$input_errors[] = "Invalid Rule Update Start Time!  Please supply a value in 24-hour format as 'HH:MM'.";
}

if ($_POST['snortdownload'] == "on" && empty($_POST['oinkmastercode']))
		$input_errors[] = "You must supply an Oinkmaster code in the box provided in order to enable Snort VRT rules!";

if ($_POST['emergingthreats_pro'] == "on" && empty($_POST['etpro_code']))
		$input_errors[] = "You must supply a subscription code in the box provided in order to enable Emerging Threats Pro rules!";

/* if no errors move foward with save */
if (!$input_errors) {
	if ($_POST["save"]) {

		$config['installedpackages']['snortglobal']['snortdownload'] = $_POST['snortdownload'] ? 'on' : 'off';
		$config['installedpackages']['snortglobal']['snortcommunityrules'] = $_POST['snortcommunityrules'] ? 'on' : 'off';
		$config['installedpackages']['snortglobal']['emergingthreats'] = $_POST['emergingthreats'] ? 'on' : 'off';
		$config['installedpackages']['snortglobal']['emergingthreats_pro'] = $_POST['emergingthreats_pro'] ? 'on' : 'off';
		$config['installedpackages']['snortglobal']['clearblocks'] = $_POST['clearblocks'] ? 'on' : 'off';
		$config['installedpackages']['snortglobal']['verbose_logging'] = $_POST['verbose_logging'] ? 'on' : 'off';
		$config['installedpackages']['snortglobal']['openappid_detectors'] = $_POST['openappid_detectors'] ? 'on' : 'off';
		$config['installedpackages']['snortglobal']['hide_deprecated_rules'] = $_POST['hide_deprecated_rules'] ? 'on' : 'off';

		// If any rule sets are being turned off, then remove them
		// from the active rules section of each interface.  Start
		// by building an arry of prefixes for the disabled rules.
		$disabled_rules = array();
		$disable_ips_policy = false;
		if ($config['installedpackages']['snortglobal']['snortdownload'] == 'off') {
			$disabled_rules[] = VRT_FILE_PREFIX;
			$disable_ips_policy = true;
		}
		if ($config['installedpackages']['snortglobal']['snortcommunityrules'] == 'off')
			$disabled_rules[] = GPL_FILE_PREFIX;
		if ($config['installedpackages']['snortglobal']['emergingthreats'] == 'off')
			$disabled_rules[] = ET_OPEN_FILE_PREFIX;
		if ($config['installedpackages']['snortglobal']['emergingthreats_pro'] == 'off')
			$disabled_rules[] = ET_PRO_FILE_PREFIX;

		// Now walk all the configured interface rulesets and remove
		// any matching the disabled ruleset prefixes.
		if (is_array($config['installedpackages']['snortglobal']['rule'])) {
			foreach ($config['installedpackages']['snortglobal']['rule'] as &$iface) {
				// Disable Snort IPS policy if VRT rules are disabled
				if ($disable_ips_policy) {
					$iface['ips_policy_enable'] = 'off';
					unset($iface['ips_policy']);
				}
				$enabled_rules = explode("||", $iface['rulesets']);
				foreach ($enabled_rules as $k => $v) {
					foreach ($disabled_rules as $d)
						if (strpos(trim($v), $d) !== false)
							unset($enabled_rules[$k]);
				}
				$iface['rulesets'] = implode("||", $enabled_rules);
			}
		}

		// If deprecated rules should be removed, then do it
		if ($config['installedpackages']['snortglobal']['hide_deprecated_rules'] == "on") {
			log_error(gettext("[Snort] Hide Deprecated Rules is enabled.  Removing obsoleted rules categories."));
			snort_remove_dead_rules();
		}

		$config['installedpackages']['snortglobal']['oinkmastercode'] = $_POST['oinkmastercode'];
		$config['installedpackages']['snortglobal']['etpro_code'] = $_POST['etpro_code'];

		$config['installedpackages']['snortglobal']['rm_blocked'] = $_POST['rm_blocked'];
		$config['installedpackages']['snortglobal']['autorulesupdate7'] = $_POST['autorulesupdate7'];

		/* Check and adjust format of Rule Update Starttime string to add colon and leading zero if necessary */
		if ($_POST['rule_update_starttime']) {
			$pos = strpos($_POST['rule_update_starttime'], ":");
			if ($pos === false) {
				$tmp = str_pad($_POST['rule_update_starttime'], 4, "0", STR_PAD_LEFT);
				$_POST['rule_update_starttime'] = substr($tmp, 0, 2) . ":" . substr($tmp, -2);
			}
			$config['installedpackages']['snortglobal']['rule_update_starttime'] = str_pad($_POST['rule_update_starttime'], 4, "0", STR_PAD_LEFT);
		}

		$config['installedpackages']['snortglobal']['forcekeepsettings'] = $_POST['forcekeepsettings'] ? 'on' : 'off';

		$retval = 0;

		write_config("Snort pkg: modified global settings.");

		/* create whitelist and homenet file, then sync files */
		conf_mount_rw();
		sync_snort_package_config();
		conf_mount_ro();

		/* forces page to reload new settings */
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: /snort/snort_interfaces_global.php");
		exit;
	}
}

$pgtitle = gettext("Snort: Global Settings");
include_once("head.inc");

?>

<body link="#000000" vlink="#000000" alink="#000000">

<?php
include_once("fbegin.inc");

if($pfsense_stable == 'yes')
	echo '<p class="pgtitle">' . $pgtitle . '</p>';

/* Display Alert message, under form tag or no refresh */
if ($input_errors)
	print_input_errors($input_errors);

?>

<form action="snort_interfaces_global.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td>
<?php
        $tab_array = array();
        $tab_array[0] = array(gettext("Snort Interfaces"), false, "/snort/snort_interfaces.php");
        $tab_array[1] = array(gettext("Global Settings"), true, "/snort/snort_interfaces_global.php");
        $tab_array[2] = array(gettext("Updates"), false, "/snort/snort_download_updates.php");
        $tab_array[3] = array(gettext("Alerts"), false, "/snort/snort_alerts.php");
        $tab_array[4] = array(gettext("Blocked"), false, "/snort/snort_blocked.php");
	$tab_array[5] = array(gettext("Pass Lists"), false, "/snort/snort_passlist.php");
        $tab_array[6] = array(gettext("Suppress"), false, "/snort/snort_interfaces_suppress.php");
	$tab_array[7] = array(gettext("IP Lists"), false, "/snort/snort_ip_list_mgmt.php");
	$tab_array[8] = array(gettext("SID Mgmt"), false, "/snort/snort_sid_mgmt.php");
	$tab_array[9] = array(gettext("Log Mgmt"), false, "/snort/snort_log_mgmt.php");
	$tab_array[10] = array(gettext("Sync"), false, "/pkg_edit.php?xml=snort/snort_sync.xml");
        display_top_tabs($tab_array, true);
?>
</td></tr>
<tr>
	<td>
	<div id="mainarea">
	<table id="maintable" class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
<tr>
	<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Please Choose The Type Of Rules You Wish To Download");?></td>
</tr>
<tr>
	<td width="22%" valign="top" class="vncell"><?php echo gettext("Install ") . "<strong>" . gettext("Snort VRT") . "</strong>" . gettext(" rules");?></td>
	<td width="78%" class="vtable">
		<table width="100%" border="0" cellpadding="2" cellspacing="0">
		<tr>
			<td><input name="snortdownload" type="checkbox" id="snortdownload" value="on" onclick="enable_snort_vrt();" 
			<?php if($pconfig['snortdownload']=='on') echo 'checked'; ?> /></td>
			<td><span class="vexpl"><?php echo gettext("Snort VRT free Registered User or paid Subscriber rules"); ?></span></td>
		<tr>
			<td>&nbsp;</td>
			<td><a href="https://www.snort.org/users/sign_up" target="_blank"><?php echo gettext("Sign Up for a free Registered User Rule Account"); ?> </a><br/>
			<a href="https://www.snort.org/products" target="_blank">
			<?php echo gettext("Sign Up for paid Sourcefire VRT Certified Subscriber Rules"); ?></a></td>
		</tr>
		</table>
		<table id="snort_oink_code_tbl" width="100%" border="0" cellpadding="2" cellspacing="0">
		<tr>
			<td colspan="2">&nbsp;</td>
		</tr>
		<tr>
			<td colspan="2" valign="top"><b><span class="vexpl"><?php echo gettext("Snort VRT Oinkmaster Configuration"); ?></span></b></td>
		</tr>
		<tr>
			<td valign="top"><span class="vexpl"><strong><?php echo gettext("Code:"); ?></strong></span></td>
			<td><input name="oinkmastercode" type="text" 
				class="formfld unknown" id="oinkmastercode" size="52" 
				value="<?=htmlspecialchars($pconfig['oinkmastercode']);?>" /><br/>
			<?php echo gettext("Obtain a snort.org Oinkmaster code and paste it here."); ?></td>
		</tr>
		</table>
</tr>
<tr>
	<td width="22%" valign="top" class="vncell"><?php echo gettext("Install ") . "<strong>" . gettext("Snort Community") . "</strong>" . gettext(" rules");?></td>
	<td width="78%" class="vtable">
		<table width="100%" border="0" cellpadding="2" cellspacing="0">
			<tr>
				<td valign="top" width="8%"><input name="snortcommunityrules" type="checkbox" value="on" 
				<?php if ($pconfig['snortcommunityrules']=="on") echo "checked";?> /></td>
				<td class="vexpl"><?php echo gettext("The Snort Community Ruleset is a GPLv2 VRT certified ruleset that is distributed free of charge " . 
				"without any VRT License restrictions.  This ruleset is updated daily and is a subset of the subscriber ruleset.");?>
				<br/><br/><?php echo "<span class=\"red\"><strong>" . gettext("Note:  ") . "</strong></span>" . 
				gettext("If you are a Snort VRT Paid Subscriber, the community ruleset is already built into your download of the ") . 
				gettext("Snort VRT rules, and there is no benefit in adding this rule set.");?><br/></td>
			</tr>
		</table></td>
</tr>
<tr>
	<td width="22%" valign="top" class="vncell"><?php echo gettext("Install ") . "<strong>" . gettext("Emerging Threats") . "</strong>" . gettext(" rules");?></td>
	<td width="78%" class="vtable">
		<table width="100%" border="0" cellpadding="2" cellspacing="0">
			<tr>
				<td valign="top" width="8%"><input name="emergingthreats" type="checkbox" value="on" onclick="enable_et_rules();" 
				<?php if ($pconfig['emergingthreats']=="on") echo "checked"; ?> /></td>
				<td><span class="vexpl"><?php echo gettext("ETOpen is an open source set of Snort rules whose coverage " .
				"is more limited than ETPro."); ?></span></td>
			</tr>
			<tr>
				<td valign="top" width="8%"><input name="emergingthreats_pro" type="checkbox" value="on" onclick="enable_etpro_rules();" 
				<?php if ($pconfig['emergingthreats_pro']=="on") echo "checked"; ?>/></td>
				<td><span class="vexpl"><?php echo gettext("ETPro for Snort offers daily updates and extensive coverage of current malware threats."); ?></span></td>
			</tr>
		<tr>
			<td>&nbsp;</td>
			<td><a href="http://www.emergingthreats.net/solutions/etpro-ruleset/" target="_blank"><?php echo gettext("Sign Up for an ETPro Account"); ?> </a></td>
		</tr>
		<tr>
			<td>&nbsp;</td>
			<td class="vexpl"><?php echo "<span class='red'><strong>" . gettext("Note:") . "</strong></span>" . "&nbsp;" . 
			gettext("The ETPro rules contain all of the ETOpen rules, so the ETOpen rules are not required and are automatically disabled when the ETPro rules are selected."); ?></td>
		</tr>
		</table>
		<table id="etpro_code_tbl" width="100%" border="0" cellpadding="2" cellspacing="0">
		<tr>
			<td colspan="2">&nbsp;</td>
		</tr>
		<tr>
			<td colspan="2" valign="top"><b><span class="vexpl"><?php echo gettext("ETPro Subscription Configuration"); ?></span></b></td>
		</tr>
		<tr>
			<td valign="top"><span class="vexpl"><strong><?php echo gettext("Code:"); ?></strong></span></td>
			<td><input name="etpro_code" type="text"
				class="formfld unknown" id="etpro_code" size="52"
				value="<?=htmlspecialchars($pconfig['etpro_code']);?>"/><br/>
			<?php echo gettext("Obtain an ETPro subscription code and paste it here."); ?></td>
		</tr>
		</table>
	</td>
</tr>
<tr>
	<td width="22%" valign="top" class="vncell"><?php echo gettext("Install ") . "<strong>" . gettext("OpenAppID") . "</strong>" . gettext(" detectors");?></td>
	<td width="78%" class="vtable">
		<table width="100%" border="0" cellpadding="2" cellspacing="0">
			<tr>
				<td valign="top" width="8%"><input name="openappid_detectors" type="checkbox" value="on" onclick="enable_openappid_dnload();" 
				<?php if ($pconfig['openappid_detectors']=="on") echo "checked";?> /></td>
				<td class="vexpl"><?php echo gettext("The OpenAppID package contains the application signatures required by " . 
				"the AppID preprocessor.");?>
				<br/><br/><?php echo "<span class=\"red\"><strong>" . gettext("Note:  ") . "</strong></span>" . 
				gettext("You must enable download of the OpenAppID detectors package in order to utilize the Application ID ") . 
				gettext("preprocessor and any user-provided application detection rules.  Once enabled, go to the ") . 
				"<a href='/snort/snort_download_updates.php'>" . gettext("UPDATES") . "</a>" . gettext(" tab and click to download updates.");?></td>
			</tr>
			<tbody id="openappid_rows">
			<tr>
				<td class="vexpl" colspan="2"><br/><strong><?=gettext("OpenAppID Detection Package");?></strong></td>
			</tr>
			<tr>
				<td class="vexpl" valign="top"><strong><?=gettext("VER:");?></strong></td>
				<td class="vexpl"><?=htmlspecialchars($openappid_ver);?></td>
			</tr>
			</tbody>
		</table>
	</td>
</tr>
<tr>
	<td width="22%" valign="top" class="vncell"><?php echo gettext("Hide Deprecated Rules Categories"); ?></td>
	<td width="78%" class="vtable"><input name="hide_deprecated_rules" id="hide_deprecated_rules" type="checkbox" value="yes" 
		<?php if ($pconfig['hide_deprecated_rules']=="on") echo "checked"; ?> />
		&nbsp;&nbsp;<?php echo gettext("Hide deprecated rules categories in the GUI and remove them from the configuration.  Default is ") . 
		"<strong>" . gettext("Not Checked") . "</strong>" . gettext("."); ?></td>
</tr>
<tr>
	<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Rules Update Settings"); ?></td>
</tr>
<tr>
	<td width="22%" valign="top" class="vncell"><?php echo gettext("Update Interval"); ?></td>
	<td width="78%" class="vtable">
		<select name="autorulesupdate7" class="formselect" id="autorulesupdate7" onchange="enable_change_rules_upd()">
		<?php
		$interfaces3 = array('never_up' => gettext('NEVER'), '6h_up' => gettext('6 HOURS'), '12h_up' => gettext('12 HOURS'), '1d_up' => gettext('1 DAY'), '4d_up' => gettext('4 DAYS'), '7d_up' => gettext('7 DAYS'), '28d_up' => gettext('28 DAYS'));
		foreach ($interfaces3 as $iface3 => $ifacename3): ?>
		<option value="<?=$iface3;?>"
		<?php if ($iface3 == $pconfig['autorulesupdate7']) echo "selected"; ?> />
			<?=htmlspecialchars($ifacename3);?></option>
			<?php endforeach; ?>
	</select><span class="vexpl">&nbsp;&nbsp;<?php echo gettext("Please select the interval for rule updates. Choosing ") . 
	"<strong>" . gettext("NEVER") . "</strong>" . gettext(" disables auto-updates."); ?><br/><br/>
	<?php echo "<span class=\"red\"><strong>" . gettext("Hint: ") . "</strong></span>" . gettext("in most cases, every 12 hours is a good choice."); ?></span></td>
</tr>
<tr>
	<td width="22%" valign="top" class="vncell"><?php echo gettext("Update Start Time"); ?></td>
	<td width="78%" class="vtable"><input type="text" class="formfld time" name="rule_update_starttime" id="rule_update_starttime" size="4" 
	maxlength="5" value="<?=htmlspecialchars($pconfig['rule_update_starttime']);?>" <?php if ($pconfig['autorulesupdate7'] == "never_up") {echo "disabled";} ?> /><span class="vexpl">&nbsp;&nbsp;
	<?php echo gettext("Enter the rule update start time in 24-hour format (HH:MM). ") . "<strong>" . 
	gettext("Default") . "&nbsp;</strong>" . gettext("is ") . "<strong>" . gettext("00:05") . "</strong></span>"; ?>.<br/><br/>
	<?php echo gettext("Rules will update at the interval chosen above starting at the time specified here. For example, using the default " . 
	"start time of 00:05 and choosing 12 Hours for the interval, the rules will update at 00:05 and 12:05 each day."); ?></td>
</tr>
<tr>
	<td colspan="2" valign="top" class="listtopic"><?php echo gettext("General Settings"); ?></td>
</tr>
<tr>
	<td width="22%" valign="top" class="vncell"><?php echo gettext("Remove Blocked Hosts Interval"); ?></td>
	<td width="78%" class="vtable">
		<select name="rm_blocked" class="formselect" id="rm_blocked">
		<?php
		$interfaces3 = array('never_b' => gettext('NEVER'), '15m_b' => gettext('15 MINS'), '30m_b' => gettext('30 MINS'), '1h_b' => gettext('1 HOUR'), '3h_b' => gettext('3 HOURS'), '6h_b' => gettext('6 HOURS'), '12h_b' => gettext('12 HOURS'), '1d_b' => gettext('1 DAY'), '4d_b' => gettext('4 DAYS'), '7d_b' => gettext('7 DAYS'), '28d_b' => gettext('28 DAYS'));
		foreach ($interfaces3 as $iface3 => $ifacename3): ?>
		<option value="<?=$iface3;?>"
		<?php if ($iface3 == $pconfig['rm_blocked']) echo "selected"; ?> />
			<?=htmlspecialchars($ifacename3);?></option>
			<?php endforeach; ?>
	</select>&nbsp;
	<?php echo gettext("Please select the amount of time you would like hosts to be blocked."); ?><br/><br/>
	<?php echo "<span class=\"red\"><strong>" . gettext("Hint:") . "</strong></span>" . gettext(" in most cases, 1 hour is a good choice.");?></td>
</tr>
<tr>
	<td width="22%" valign="top" class="vncell"><?php echo gettext("Remove Blocked Hosts After Deinstall"); ?></td>
	<td width="78%" class="vtable"><input name="clearblocks" id="clearblocks" type="checkbox" value="yes" 
	<?php if ($pconfig['clearblocks']=="on") echo " checked"; ?> />&nbsp;
	<?php echo gettext("All blocked hosts added by Snort will be removed during package deinstallation."); ?></td>
</tr>
<tr>
	<td width="22%" valign="top" class="vncell"><?php echo gettext("Keep Snort Settings After Deinstall"); ?></td>
	<td width="78%" class="vtable"><input name="forcekeepsettings"
		id="forcekeepsettings" type="checkbox" value="yes" 
		<?php if ($pconfig['forcekeepsettings']=="on") echo "checked"; ?> />
		&nbsp;&nbsp;<?php echo gettext("Settings will not be removed during package deinstallation."); ?></td>
</tr>
<tr>
	<td width="22%" valign="top" class="vncell"><?php echo gettext("Startup/Shutdown Logging"); ?></td>
	<td width="78%" class="vtable"><input name="verbose_logging"
		id="verbose_logging" type="checkbox" value="yes" 
		<?php if ($pconfig['verbose_logging']=="on") echo "checked"; ?> />
		&nbsp;&nbsp;<?php echo gettext("Output detailed messages to the system log when Snort is starting and stopping.  Default is ") . 
		"<strong>" . gettext("Not Checked") . "</strong>" . gettext("."); ?></td>
</tr>
<tr>
	<td width="22%" valign="top">
	<td width="78%">
		<input name="save" type="submit" class="formbtn" value="Save" />
	</td>
</tr>
<tr>
	<td width="22%" valign="top">&nbsp;</td>
	<td width="78%" class="vexpl"><span class="red"><strong><?php echo gettext("Note:");?></strong>&nbsp;
	</span><?php echo gettext("Changing any settings on this page will affect all Snort-configured interfaces.");?></td>
</tr>
	</table>
</div><br/>
</td></tr>
</table>
</form>
<?php include("fend.inc"); ?>

<script language="JavaScript">
<!--
function enable_snort_vrt() {
	var endis = !(document.iform.snortdownload.checked);
	if (endis)
		document.getElementById("snort_oink_code_tbl").style.display = "none";
	else
		document.getElementById("snort_oink_code_tbl").style.display = "table";
}

function enable_et_rules() {
	var endis = document.iform.emergingthreats.checked;
	if (endis) {
		document.iform.emergingthreats_pro.checked = !(endis);
		document.getElementById("etpro_code_tbl").style.display = "none";
	}
}

function enable_etpro_rules() {
	var endis = document.iform.emergingthreats_pro.checked;
	if (endis) {
		document.iform.emergingthreats.checked = !(endis);
		document.iform.etpro_code.disabled = "";
		document.getElementById("etpro_code_tbl").style.display = "table";
	}
	else {
		document.iform.etpro_code.disabled = "true";
		document.getElementById("etpro_code_tbl").style.display = "none";
	}
}

function enable_change_rules_upd() {
	if (document.iform.autorulesupdate7.selectedIndex == 0)
		document.iform.rule_update_starttime.disabled="true";
	else
		document.iform.rule_update_starttime.disabled="";		
}

function enable_openappid_dnload() {
	var endis = document.iform.openappid_detectors.checked;
	if (endis)
		document.getElementById("openappid_rows").style.display = "";
	else
		document.getElementById("openappid_rows").style.display = "none";
}

// Initialize the form controls state based on saved settings
enable_snort_vrt();
enable_et_rules();
enable_etpro_rules();
enable_change_rules_upd();
enable_openappid_dnload();

//-->
</script>

</body>
</html>
