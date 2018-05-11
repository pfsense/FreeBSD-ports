<?php
/*
 * suricata_interfaces.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper
 * Copyright (c) 2005 Bill Marquette
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2018 Bill Meeks
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
require_once("/usr/local/pkg/suricata/suricata.inc");

global $g, $rebuild_rules;

$suricatadir = SURICATADIR;
$suricatalogdir = SURICATALOGDIR;
$rcdir = RCFILEPREFIX;
$suri_starting = array();
$by2_starting = array();

if ($_POST['id'])
	$id = $_POST['id'];
else
	$id = 0;

if (!is_array($config['installedpackages']['suricata']['rule'])) {
	$config['installedpackages']['suricata']['rule'] = array();
}

$a_nat = &$config['installedpackages']['suricata']['rule'];
$id_gen = count($config['installedpackages']['suricata']['rule']);

// Get list of configured firewall interfaces
$ifaces = get_configured_interface_list();

if (isset($_POST['del_x'])) {
	/* delete selected interfaces */
	if (is_array($_POST['rule']) && count($_POST['rule'])) {
		conf_mount_rw();
		foreach ($_POST['rule'] as $rulei) {
			$if_real = get_real_interface($a_nat[$rulei]['interface']);
			$if_friendly = convert_friendly_interface_to_friendly_descr($a_nat[$rulei]['interface']);
			$suricata_uuid = $a_nat[$rulei]['uuid'];
			suricata_stop($a_nat[$rulei], $if_real);
			rmdir_recursive("{$suricatalogdir}suricata_{$if_real}{$suricata_uuid}");
			rmdir_recursive("{$suricatadir}suricata_{$suricata_uuid}_{$if_real}");
			unset($a_nat[$rulei]);
		}

		/* If all the Suricata interfaces are removed, then unset the config array. */
		if (empty($a_nat))
			unset($a_nat);

		write_config("Suricata pkg: deleted one or more Suricata interfaces.");
		sleep(2);

		conf_mount_rw();
		sync_suricata_package_config();
		conf_mount_ro();

		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: /suricata/suricata_interfaces.php");
		exit;
	}
} else {
	unset($delbtn_list);
	foreach ($_POST as $pn => $pd) {
		if (preg_match("/ldel_(\d+)/", $pn, $matches)) {
			$delbtn_list = $matches[1];
		}
	}

	if (is_numeric($delbtn_list) && $a_nat[$delbtn_list]) {
		$if_real = get_real_interface($a_nat[$delbtn_list]['interface']);
		$if_friendly = convert_friendly_interface_to_friendly_descr($a_nat[$delbtn_list]['interface']);
		$suricata_uuid = $a_nat[$delbtn_list]['uuid'];
		log_error("Stopping Suricata on {$if_friendly}({$if_real}) due to interface deletion...");
		suricata_stop($a_nat[$delbtn_list], $if_real);
		rmdir_recursive("{$suricatalogdir}suricata_{$if_real}{$suricata_uuid}");
		rmdir_recursive("{$suricatadir}suricata_{$suricata_uuid}_{$if_real}");

		// Finally delete the interface's config entry entirely
		unset($a_nat[$delbtn_list]);
		log_error("Deleted Suricata instance on {$if_friendly}({$if_real}) per user request...");

		// Save updated configuration
		write_config("Suricata pkg: deleted one or more Suricata interfaces.");
		sleep(2);
		conf_mount_rw();
		sync_suricata_package_config();
		conf_mount_ro();
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: /suricata/suricata_interfaces.php");
		exit;
	}
}

/* start/stop Barnyard2 */
if ($_POST['by2toggle']) {
	$suricatacfg = $config['installedpackages']['suricata']['rule'][$id];
	$if_real = get_real_interface($suricatacfg['interface']);
	$if_friendly = convert_friendly_interface_to_friendly_descr($suricatacfg['interface']);

	if (!suricata_is_running($suricatacfg['uuid'], $if_real, 'barnyard2')) {
		if ($if_friendly == $suricatacfg['descr']) {
			log_error("Toggle (barnyard starting) for {$if_friendly}...");
		}
		else {
			log_error("Toggle (barnyard starting) for {$if_friendly}({$suricatacfg['descr']})...");
		}
		// No need to rebuild Suricata rules for Barnyard2,
		// so flag that task as "off" to save time.
		$rebuild_rules = false;
		conf_mount_rw();
		sync_suricata_package_config();
		conf_mount_ro();
		suricata_barnyard_start($suricatacfg, $if_real);
		$by2_starting[$id] = 'TRUE';
	} else {
		if ($if_friendly == $suricatacfg['descr']) {
			log_error("Toggle (barnyard stopping) for {$if_friendly}...");
		}
		else {
			log_error("Toggle (barnyard stopping) for {$if_friendly}({$suricatacfg['descr']})...");
		}
		suricata_barnyard_stop($suricatacfg, $if_real);
		unset($by2_starting[$id]);
	}
}

/* start/stop Suricata */
if ($_POST['toggle']) {
	$suricatacfg = $config['installedpackages']['suricata']['rule'][$_POST['id']];
	$if_real = get_real_interface($suricatacfg['interface']);
	$if_friendly = convert_friendly_interface_to_friendly_descr($suricatacfg['interface']);
	$id = $_POST['id'];

	// Suricata can take several seconds to startup, so to
	// make the GUI more responsive, startup commands are
	// executed as a background process.  The commands
	// are written to a PHP file in the 'tmp_path' which
	// is executed by a PHP command line session launched
	// as a background task.

	// Create steps for the background task to start Suricata.
	// These commands will be handed off to a CLI PHP session
	// for background execution as a self-deleting PHP file.
	$start_lck_file = "{$g['varrun_path']}/suricata_{$if_real}{$suricatacfg['uuid']}_starting.lck";
	$suricata_start_cmd = <<<EOD
	<?php
	require_once('/usr/local/pkg/suricata/suricata.inc');
	require_once('service-utils.inc');
	global \$g, \$rebuild_rules, \$config;
	\$suricatacfg = \$config['installedpackages']['suricata']['rule'][{$id}];
	\$rebuild_rules = true;
	touch('{$start_lck_file}');
	conf_mount_rw();
	sync_suricata_package_config();
	conf_mount_ro();
	\$rebuild_rules = false;
	suricata_start(\$suricatacfg, '{$if_real}');
	unlink_if_exists('{$start_lck_file}');
	unlink(__FILE__);
	?>
EOD;

	switch ($_POST['toggle']) {
		case 'start':
			file_put_contents("{$g['tmp_path']}/suricata_{$if_real}{$suricatacfg['uuid']}_startcmd.php", $suricata_start_cmd);
			if (suricata_is_running($suricatacfg['uuid'], $if_real)) {
				log_error("Restarting Suricata on {$if_friendly}({$if_real}) per user request...");
				suricata_stop($suricatacfg, $if_real);
				mwexec_bg("/usr/local/bin/php -f {$g['tmp_path']}/suricata_{$if_real}{$suricatacfg['uuid']}_startcmd.php");
			}
			else {
				log_error("Starting Suricata on {$if_friendly}({$if_real}) per user request...");
				mwexec_bg("/usr/local/bin/php -f {$g['tmp_path']}/suricata_{$if_real}{$suricatacfg['uuid']}_startcmd.php");
			}
			$suri_starting[$id] = 'TRUE';
			if ($suricatacfg['barnyard_enable'] == 'on' && !isvalidpid("{$g['varrun_path']}/barnyard2_{$if_real}{$suricata_uuid}.pid")) {
				$by2_starting[$id] = 'TRUE';
			}
			break;
		case 'stop':
			if (suricata_is_running($suricatacfg['uuid'], $if_real)) {
				log_error("Stopping Suricata on {$if_friendly}({$if_real}) per user request...");
				suricata_stop($suricatacfg, $if_real);
			}
			unset($suri_starting[$id]);
			unset($by2_starting[$id]);
			unlink_if_exists($start_lck_file);
			break;
		default:
			unset($suri_starting[$id]);
			unset($by2_starting[$id]);
			unlink_if_exists('{$start_lck_file}');
	}
	unset($suricata_start_cmd);
}

/* Ajax call to periodically check Suricata status */
/* on each configured interface.                   */
if ($_POST['status'] == 'check') {
	$list = array();

	// Iterate configured Suricata interfaces and get status of each
	// into an associative array.  Return the array to the Ajax
	// caller as a JSON object.
	foreach ($a_nat as $natent) {
		$intf_key = "suricata_" . get_real_interface($natent['interface']) . $natent['uuid'];
		if ($natent['enable'] == "on") {
			if (suricata_is_running($natent['uuid'], get_real_interface($natent['interface']))) {
				$list[$intf_key] = "RUNNING";
			}
			elseif (file_exists("{$g['varrun_path']}/{$intf_key}_starting.lck") || file_exists("{$g['varrun_path']}/suricata_pkg_starting.lck")) {
				$list[$intf_key] = "STARTING";
			}
			else {
				$list[$intf_key] = "STOPPED";
			}
		}
		else {
			$list[$intf_key] = "DISABLED";
		}
	}

	// Iterate configured Barnyard2 interfaces and add status of each
	// to the JSON array object.
	foreach ($a_nat as $natent) {
		$intf_key = "barnyard2_" . get_real_interface($natent['interface']) . $natent['uuid'];
		if ($natent['barnyard_enable'] == "on") {
			if (suricata_is_running($natent['uuid'], get_real_interface($natent['interface']), 'barnyard2')) {
				$list[$intf_key] = "RUNNING";
			}
			elseif (file_exists("{$g['varrun_path']}/{$intf_key}_starting.lck") || file_exists("{$g['varrun_path']}/suricata_pkg_starting.lck")) {
				$list[$intf_key] = "STARTING";
			}
			else {
				$list[$intf_key] = "STOPPED";
			}
		}
		else {
			$list[$intf_key] = "DISABLED";
		}
	}

	// Return a JSON encoded array as the page output
	echo json_encode($list);
	exit;
}

// May decide to use these again for display, but for now they are not used
$suri_bin_ver = SURICATA_BIN_VERSION;
$suri_pkg_ver = SURICATA_PKG_VER;

$pgtitle = array(gettext("Services"), gettext("Suricata"), gettext("Interfaces"));
include_once("head.inc"); ?>

<?php
	/* Display Alert message */
	if ($input_errors)
		print_input_errors($input_errors);

	if ($savemsg)
		print_info_box($savemsg);
?>

<?php
	$tab_array = array();
	$tab_array[] = array(gettext("Interfaces"), true, "/suricata/suricata_interfaces.php");
	$tab_array[] = array(gettext("Global Settings"), false, "/suricata/suricata_global.php");
	$tab_array[] = array(gettext("Updates"), false, "/suricata/suricata_download_updates.php");
	$tab_array[] = array(gettext("Alerts"), false, "/suricata/suricata_alerts.php");
	$tab_array[] = array(gettext("Blocks"), false, "/suricata/suricata_blocked.php");
	$tab_array[] = array(gettext("Pass Lists"), false, "/suricata/suricata_passlist.php");
	$tab_array[] = array(gettext("Suppress"), false, "/suricata/suricata_suppress.php");
	$tab_array[] = array(gettext("Logs View"), false, "/suricata/suricata_logs_browser.php");
	$tab_array[] = array(gettext("Logs Mgmt"), false, "/suricata/suricata_logs_mgmt.php");
	$tab_array[] = array(gettext("SID Mgmt"), false, "/suricata/suricata_sid_mgmt.php");
	$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
	$tab_array[] = array(gettext("IP Lists"), false, "/suricata/suricata_ip_list_mgmt.php");
	display_top_tabs($tab_array, true);
?>

<form action="suricata_interfaces.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
<input type="hidden" name="id" id="id" value="">
<input type="hidden" name="toggle" id="toggle" value="">
<input type="hidden" name="by2toggle" id="by2toggle" value="">

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Interface Settings Overview")?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table id="maintable" class="table table-striped table-hover table-condensed">
				<thead>
				<tr id="frheader">
					<th>&nbsp;</th>
					<th><?=gettext("Interface"); ?></th>
					<th><?=gettext("Suricata"); ?></th>
					<th><?=gettext("Pattern Match"); ?></th>
					<th><?=gettext("Block"); ?></th>
					<th><?=gettext("Barnyard2"); ?></th>
					<th><?=gettext("Description"); ?></th>
					<th><?=gettext("Actions")?></th>
				</tr>
				</thead>
				<tbody>
				<?php $nnats = $i = 0;

				// Turn on buffering to speed up rendering
				ini_set('output_buffering','true');

				// Start buffering to fix display lag issues in IE9 and IE10
				ob_start(null, 0);

				/* If no interfaces are defined, then turn off the "no rules" warning */
				$no_rules_footnote = false;

				if ($id_gen == 0) {
					$no_rules = false;
				} else {
					$no_rules = true;
				}

				foreach ($a_nat as $natent):
?>
				<tr id="fr<?=$nnats?>">
<?php
					/* convert fake interfaces to real and check if iface is up */
					/* There has to be a smarter way to do this */
					$if_real = get_real_interface($natent['interface']);
					$natend_friendly= convert_friendly_interface_to_friendly_descr($natent['interface']);
					$suricata_uuid = $natent['uuid'];

					/* See if interface has any rules defined and set boolean flag */
					$no_rules = true;

					if (isset($natent['customrules']) && !empty($natent['customrules'])) {
						$no_rules = false;
					}

					if (isset($natent['rulesets']) && !empty($natent['rulesets'])) {
						$no_rules = false;
					}

					if (isset($natent['ips_policy']) && !empty($natent['ips_policy'])) {
						$no_rules = false;
					}

					/* Do not display the "no rules" warning if interface disabled */
					if ($natent['enable'] == "off") {
						$no_rules = false;
					}

					if ($no_rules) {
						$no_rules_footnote = true;
					}
?>
					<td>
						<input type="checkbox" id="frc<?=$nnats?>" name="rule[]" value="<?=$i?>" onClick="fr_bgcolor('<?=$nnats?>')" style="margin: 0; padding: 0;">
					</td>
					<td id="frd<?=$nnats?>"
					ondblclick="document.location='suricata_interfaces_edit.php?id=<?=$nnats?>';">
<?php
						echo $natend_friendly;
?>
					</td>

					<td id="frd<?=$nnats?>"
					ondblclick="document.location='suricata_interfaces_edit.php?id=<?=$nnats?>';">
<?php
					$check_suricata_info = $config['installedpackages']['suricata']['rule'][$nnats]['enable'];
?>
					<?php if ($check_suricata_info == "on") : ?>
						<?php if (suricata_is_running($suricata_uuid, $if_real)) : ?>
							<i id="suricata_<?=$if_real.$suricata_uuid;?>" class="fa fa-check-circle text-success icon-primary" title="<?=gettext('suricata is running on this interface');?>"></i>
							&nbsp;
							<i id="suricata_<?=$if_real.$suricata_uuid;?>_restart" class="fa fa-repeat icon-pointer icon-primary text-info" onclick="javascript:suricata_iface_toggle('start', '<?=$nnats?>');" title="<?=gettext('Restart suricata on this interface');?>"></i>
							<i id="suricata_<?=$if_real.$suricata_uuid;?>_start" class="fa fa-play-circle icon-pointer icon-primary text-info hidden" onclick="javascript:suricata_iface_toggle('start', '<?=$nnats?>');" title="<?=gettext('Start suricata on this interface');?>"></i>
							<i id="suricata_<?=$if_real.$suricata_uuid;?>_stop" class="fa fa-stop-circle-o icon-pointer icon-primary text-info" onclick="javascript:suricata_iface_toggle('stop', '<?=$nnats?>');" title="<?=gettext('Stop suricata on this interface');?>"></i>
						<?php elseif ($suri_starting[$nnats] == 'TRUE' || file_exists("{$g['varrun_path']}/suricata_pkg_starting.lck")) : ?>
							<i id="suricata_<?=$if_real.$suricata_uuid;?>" class="fa fa-cog fa-spin text-success icon-primary" title="<?=gettext('suricata is starting on this interface');?>"></i>
							&nbsp;
							<i id="suricata_<?=$if_real.$suricata_uuid;?>_restart" class="fa fa-repeat icon-pointer icon-primary text-info hidden" onclick="javascript:suricata_iface_toggle('start', '<?=$nnats?>');" title="<?=gettext('Restart suricata on this interface');?>"></i>
							<i id="suricata_<?=$if_real.$suricata_uuid;?>_start" class="fa fa-play-circle icon-pointer icon-primary text-info hidden" onclick="javascript:suricata_iface_toggle('start', '<?=$nnats?>');" title="<?=gettext('Start suricata on this interface');?>"></i>
							<i id="suricata_<?=$if_real.$suricata_uuid;?>_stop" class="fa fa-stop-circle-o icon-pointer icon-primary text-info" onclick="javascript:suricata_iface_toggle('stop', '<?=$nnats?>');" title="<?=gettext('Stop suricata on this interface');?>"></i>
						<?php else: ?>
							<i class="fa fa-times-circle text-danger icon-primary" title="<?=gettext('suricata is stopped on this interface');?>"></i>
							&nbsp;
							<i id="suricata_<?=$if_real.$suricata_uuid;?>_restart" class="fa fa-repeat icon-pointer icon-primary text-info hidden" onclick="javascript:suricata_iface_toggle('start', '<?=$nnats?>');" title="<?=gettext('Restart suricata on this interface');?>"></i>
							<i id="suricata_<?=$if_real.$suricata_uuid;?>_start" class="fa fa-play-circle icon-pointer icon-primary text-info" onclick="javascript:suricata_iface_toggle('start', '<?=$nnats?>');" title="<?=gettext('Start suricata on this interface');?>"></i>
							<i id="suricata_<?=$if_real.$suricata_uuid;?>_stop" class="fa fa-stop-circle-o icon-pointer icon-primary text-info hidden" onclick="javascript:suricata_iface_toggle('stop', '<?=$nnats?>');" title="<?=gettext('Stop suricata on this interface');?>"></i>
						<?php endif; ?>
					<?php else : ?>
						<?=gettext('DISABLED');?>&nbsp;
					<?php endif; ?>

					</td>

					<td
					id="frd<?=$nnats?>"
					ondblclick="document.location='suricata_interfaces_edit.php?id=<?=$nnats?>';">
<?php
					$check_performance_info = $config['installedpackages']['suricata']['rule'][$nnats]['mpm_algo'];

					if ($check_performance_info != "") {
						$check_performance = $check_performance_info;
					}else{
						$check_performance = "unknown";
					}

?>
						<?=strtoupper($check_performance)?>
					</td>

					<td
					id="frd<?=$nnats?>"
					ondblclick="document.location='suricata_interfaces_edit.php?id=<?=$nnats?>';">
					<?php
					$check_blockoffenders_info = $config['installedpackages']['suricata']['rule'][$nnats]['blockoffenders'];

					if ($check_blockoffenders_info == "on")
					{
						$check_blockoffenders = 'enabled';
					} else {
						$check_blockoffenders = 'disabled';
					}
?>
					<?=strtoupper($check_blockoffenders)?>
					</td>

					<td id="frd<?=$nnats?>" ondblclick="document.location='suricata_interfaces_edit.php?id=<?=$nnats?>';">
						<?php if ($config['installedpackages']['suricata']['rule'][$nnats]['barnyard_enable'] == 'on') : ?>
							<?php if (suricata_is_running($suricata_uuid, $if_real, 'barnyard2')) : ?>
								<i id="barnyard2_<?=$if_real.$suricata_uuid;?>" class="fa fa-check-circle text-success icon-primary" title="<?=gettext('barnyard2 is running on this interface');?>"></i>
								&nbsp;
								<i id="barnyard2_<?=$if_real.$suricata_uuid;?>_restart" class="fa fa-repeat icon-pointer text-info icon-primary" onclick="javascript:by2_iface_toggle('start', '<?=$nnats?>');" title="<?=gettext('Restart barnyard2 on this interface');?>"></i>
								<i id="barnyard2_<?=$if_real.$suricata_uuid;?>_start" class="fa fa-play-circle icon-pointer text-info icon-primary hidden" onclick="javascript:by2_iface_toggle('start', '<?=$nnats?>');" title="<?=gettext('Start barnyard2 on this interface');?>"></i>
								<i id="barnyard2_<?=$if_real.$suricata_uuid;?>_stop" class="fa fa-stop-circle-o icon-pointer text-info icon-primary" onclick="javascript:by2_iface_toggle('stop', '<?=$nnats?>');" title="<?=gettext('Stop barnyard2 on this interface');?>"></i>
							<?php elseif ($by2_starting[$nnats] == 'TRUE'  || file_exists("{$g['varrun_path']}/suricata_pkg_starting.lck")) : ?>
								<i id="barnyard2_<?=$if_real.$suricata_uuid;?>" class="fa fa-check-circle text-success icon-primary" title="<?=gettext('barnyard2 is starting on this interface');?>"></i>
								&nbsp;
								<i id="barnyard2_<?=$if_real.$suricata_uuid;?>_restart" class="fa fa-repeat icon-pointer text-info icon-primary hidden" onclick="javascript:by2_iface_toggle('start', '<?=$nnats?>');" title="<?=gettext('Restart barnyard2 on this interface');?>"></i>
								<i id="barnyard2_<?=$if_real.$suricata_uuid;?>_start" class="fa fa-play-circle icon-pointer text-info icon-primary hidden" onclick="javascript:by2_iface_toggle('start', '<?=$nnats?>');" title="<?=gettext('Start barnyard2 on this interface');?>"></i>
								<i id="barnyard2_<?=$if_real.$suricata_uuid;?>_stop" class="fa fa-stop-circle-o icon-pointer text-info icon-primary" onclick="javascript:by2_iface_toggle('stop', '<?=$nnats?>');" title="<?=gettext('Stop barnyard2 on this interface');?>"></i>
							<?php else: ?>
								<i class="fa fa-times-circle text-danger icon-primary" title="<?=gettext('barnyard2 is stopped on this interface');?>"></i>
								&nbsp;
								<i id="barnyard2_<?=$if_real.$suricata_uuid;?>_restart" class="fa fa-repeat icon-pointer text-info icon-primary hidden" onclick="javascript:by2_iface_toggle('start', '<?=$nnats?>');" title="<?=gettext('Restart barnyard2 on this interface');?>"></i>
								<i id="barnyard2_<?=$if_real.$suricata_uuid;?>_start" class="fa fa-play-circle icon-pointer text-info icon-primary" onclick="javascript:by2_iface_toggle('start', '<?=$nnats?>');" title="<?=gettext('Start barnyard2 on this interface');?>"></i>
								<i id="barnyard2_<?=$if_real.$suricata_uuid;?>_stop" class="fa fa-stop-circle-o icon-pointer text-info icon-primary hidden" onclick="javascript:by2_iface_toggle('stop', '<?=$nnats?>');" title="<?=gettext('Stop barnyard2 on this interface');?>"></i>
							<?php endif; ?>
						<?php else : ?>
							<?=gettext('DISABLED');?>&nbsp;
						<?php endif; ?>
					</td>

					<td ondblclick="document.location='suricata_interfaces_edit.php?id=<?=$nnats?>';">
					<?=htmlspecialchars($natent['descr'])?>
					</td>

					<td>
						<a href="suricata_interfaces_edit.php?id=<?=$nnats;?>" class="fa fa-pencil icon-primary" title="<?=gettext('Edit this Suricata interface mapping');?>"></a>
						<?php if ($id_gen < count($ifaces)): ?>
							<a href="suricata_interfaces_edit.php?id=<?=$nnats?>&action=dup" class="fa fa-clone" title="<?=gettext('Clone this Suricata instance to an available interface');?>"></a>
						<?php endif; ?>
						<a style="cursor:pointer;" class="fa fa-trash no-confirm icon-primary" id="Xldel_<?=$nnats?>" title="<?=gettext('Delete this Suricata interface mapping'); ?>"></a>
						<button style="display: none;" class="btn btn-xs btn-warning" type="submit" id="ldel_<?=$nnats?>" name="ldel_<?=$nnats?>" value="ldel_<?=$nnats?>" title="<?=gettext('Delete this Suricata interface mapping'); ?>">Delete this Suricata interface mapping</button>
					</td>

				</tr>
				<?php $i++; $nnats++; endforeach; ob_end_flush(); unset($suri_starting); unset($by2_starting); ?>
				<tr>
					<td></td>
					<td colspan="7">
						<?php if ($no_rules_footnote): ?><span class="text-danger"><?=gettext("WARNING: Marked interface currently has no rules defined for Suricata"); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>

<nav class="action-buttons">
	<?php if ($id_gen < count($ifaces)): ?>
		<a href="suricata_interfaces_edit.php?id=<?=$id_gen?>" class="btn btn-sm btn-success" title="<?=gettext('Add Suricata interface mapping')?>">
			<i class="fa fa-plus icon-embed-btn" ></i><?=gettext("Add")?>
		</a>
	<?php endif; ?>

	<?php if ($id_gen != 0): ?>
		<button type="submit" name="del_x" id="del_x" class="btn btn-danger btn-sm no-confirm" title="<?=gettext('Delete selected Suricata interface mapping(s)');?>" onclick="return intf_del()">
			<i class="fa fa-trash no-confirm icon-embed-btn"></i>
			<?=gettext('Delete');?>
		</button>
	<?php endif; ?>
</nav>
</form>

<div class="infoblock">
	<?=print_info_box('<div class="row">
		<div class="col-md-12">
			<p>This is where you can see an overview of all your interface settings. Please configure the parameters on the <strong>Global Settings</strong> tab before adding an interface.</p>
			<p><strong>Warning: New settings will not take effect until interface restart</strong></p>
		</div>
	</div>
	<div class="row">
		<div class="col-md-6">
			<p>
				Click on the <i class="fa fa-lg fa-pencil" alt="Edit Icon"></i> icon to edit an interface and settings.<br/>
				Click on the <i class="fa fa-lg fa-trash" alt="Delete Icon"></i> icon to delete an interface and settings.<br/>
				Click on the <i class="fa fa-lg fa-clone" alt="Clone Icon"></i> icon to clone an existing interface.
			</p>
		</div>
		<div class="col-md-6">
			<p>
				<i class="fa fa-lg fa-check-circle" alt="Running"></i> <i class="fa fa-lg fa-times" alt="Not Running"></i> icons will show current Suricata and barnyard2 status<br/>
				Click the <i class="fa fa-lg fa-play-circle" alt="Start"></i> or <i class="fa fa-lg fa-repeat" alt="Restart"></i> or <i class="fa fa-lg fa-stop-circle-o" alt="Stop"></i> icons to start/restart/stop Suricata and Barnyard2.
			</p>
		</div>
	</div>', 'info')?>
</div>

<script type="text/javascript">
//<![CDATA[
	function check_status() {

		// This function uses Ajax to post a query to
		// this page requesting the status of each
		// configured interface.  The result is returned
		// as a JSON array object.  A timer is set upon
		// completion to call the function again in
		// 2 seconds.  This allows dynamic updating
		// of interface status in the GUI.
		$.ajax(
			"<?=$_SERVER['SCRIPT_NAME'];?>",
			{
				type: 'post',
				data: {
					status: 'check'
				},
				success: showStatus,
				complete: function() {
					setTimeout(check_status, 2000);
				}
			}
		);
	}

	function showStatus(responseData) {

		// The JSON object returned by check_status() is an associative array
		// of interface unique IDs and corresponding service status.  The
		// "key" is the service name followed by the physical interface and a UUID.
		// The "value" of the key is either "DISABLED, STOPPED, STARTING, or RUNNING".
		//
		// Example keys:  suricata_em1998 or barnyard2_em1998
		//
		// Within the HTML of this page, icon controls for displaying status
		// and for starting/restarting/stopping the service are tagged with
		// control IDs using "key" followed by the icon's function.  These
		// control IDs are used in the code below to alter icon appearance
		// depending on the service status.

		var data = jQuery.parseJSON(responseData);

		// Iterate the associative array and update interface status icons
		for(var key in data) {
			var service_name = key.substring(0, key.indexOf('_'));
			if (data[key] != 'DISABLED') {
				if (data[key] == 'STOPPED') {
					$('#' + key).removeClass('fa-check-circle fa-cog fa-spin text-success text-info');
					$('#' + key).addClass('fa-times-circle text-danger');
					$('#' + key).prop('title', service_name + ' is stopped on this interface');
					$('#' + key + '_restart').addClass('hidden');
					$('#' + key + '_stop').addClass('hidden');
					$('#' + key + '_start').removeClass('hidden');
				}
				if (data[key] == 'STARTING') {
					$('#' + key).removeClass('fa-check-circle fa-times-circle text-info text-danger');
					$('#' + key).addClass('fa-cog fa-spin text-success');
					$('#' + key).prop('title', service_name + ' is starting on this interface');
					$('#' + key + '_restart').addClass('hidden');
					$('#' + key + '_start').addClass('hidden');
					$('#' + key + '_stop').removeClass('hidden');
				}
				if (data[key] == 'RUNNING') {
					$('#' + key).addClass('fa-check-circle text-success');
					$('#' + key).removeClass('fa-times-circle fa-cog fa-spin text-danger text-info');
					$('#' + key).prop('title', service_name + ' is running on this interface');
					$('#' + key + '_restart').removeClass('hidden');
					$('#' + key + '_stop').removeClass('hidden');
					$('#' + key + '_start').addClass('hidden');
				}
			}
		}
	}

	function suricata_iface_toggle(action, id) {
		$('#toggle').val(action);
		$('#id').val(id);
		$('#iform').submit();
	}

	function by2_iface_toggle(action, id) {
		$('#by2toggle').val(action);
		$('#id').val(id);
		$('#iform').submit();
	}

	function intf_del() {
		var isSelected = false;
		var inputs = document.iform.elements;
		for (var i = 0; i < inputs.length; i++) {
			if (inputs[i].type == "checkbox") {
				if (inputs[i].checked)
					isSelected = true;
			}
		}
		if (isSelected)
			return confirm('Do you really want to delete the selected Suricata mapping?');
		else
			alert("There is no Suricata mapping selected for deletion.  Click the checkbox beside the Suricata mapping(s) you wish to delete.");
	}

	events.push(function() {
		$('[id^=Xldel_]').click(function (event) {
			if(confirm("<?=gettext('Delete this Suricata interface mapping?')?>")) {
				$('#' + event.target.id.slice(1)).click();
			}
		});

	});

	// Set a timer to call the check_status()
	// function in two seconds.
	setTimeout(check_status, 2000);

//]]>
</script>

<?php
include("foot.inc");
?>
