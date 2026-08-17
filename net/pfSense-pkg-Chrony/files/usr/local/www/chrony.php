<?php
/*
 * chrony.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2026 Denny Page
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
require_once("chrony.inc");

$shortcut_section = 'chrony';

// Get the current configuration
$current_config = config_get_path(CHRONY_CONF_PATH, []);
$pconfig[CHRONY_CONF_TOK_ENABLE] = array_get_path($current_config, CHRONY_CONF_TOK_ENABLE);
$pconfig[CHRONY_CONF_TOK_IPPROTO] = array_get_path($current_config, CHRONY_CONF_TOK_IPPROTO);
$pconfig[CHRONY_CONF_TOK_NTS_CERT] = array_get_path($current_config, CHRONY_CONF_TOK_NTS_CERT);
$pconfig[CHRONY_CONF_TOK_LOCAL] = array_get_path($current_config, CHRONY_CONF_TOK_LOCAL);
$pconfig[CHRONY_CONF_TOK_LOCAL_ORPHAN] = array_get_path($current_config, CHRONY_CONF_TOK_LOCAL_ORPHAN);
$pconfig[CHRONY_CONF_TOK_LOCAL_STRATUM] = array_get_path($current_config, CHRONY_CONF_TOK_LOCAL_STRATUM);

// Handle a source toggle
if ($_REQUEST['act'] == "toggle") {
	$id = $_REQUEST['id'];
	if (is_numericint($id)) {
		$source_path = CHRONY_CONF_TOK_SOURCE . '/' . $id;
		if (array_get_path($current_config, $source_path)) {
			// Set the paths
			$source_disabled_path = $source_path . '/' . CHRONY_CONF_TOK_SOURCE_DISABLED;
			$source_name_path = $source_path . '/' . CHRONY_CONF_TOK_SOURCE_NAME;

			// Get the current state
			$source_enabled = array_get_path($current_config, $source_disabled_path) === null;
			$source_name = array_get_path($current_config, $source_name_path);

			// Update the config and cached array
			if ($source_enabled) {
				// Disable the source
				config_set_path(CHRONY_CONF_PATH . '/' . $source_disabled_path, 'yes');
				array_set_path($current_config, $source_disabled_path, 'yes');
				$changedesc = sprintf(gettext("Chrony daemon: disabled source %s"), $source_name);
			} else {
				// Enable the source
				config_del_path(CHRONY_CONF_PATH . '/' . $source_disabled_path);
				array_del_path($current_config, $source_disabled_path);
				$changedesc = sprintf(gettext("Chrony daemon: enabled source %s"), $source_name);
			}

			// Write the config
			write_config($changedesc);
			mark_subsystem_dirty('chrony');
		}
	}
}

// Handle a source delete
elseif ($_REQUEST['act'] == "delete") {
	$id = $_REQUEST['id'];
	if (is_numericint($id)) {
		$source_path = CHRONY_CONF_TOK_SOURCE . '/' . $id;
		$source_type = array_get_path($current_config, $source_path, []);
		if ($source_type) {
			$changedesc = sprintf(gettext("Chrony daemon: deleted source %s"), $source_type[CHRONY_CONF_TOK_SOURCE_NAME]);

			// Update the config and cached array
			config_del_path(CHRONY_CONF_PATH . '/' . $source_path);
			array_del_path($current_config, $source_path);

			// Write the config and mark the subsystem dirty if appropriate
			write_config($changedesc);
			if (!isset($source_type[CHRONY_CONF_TOK_SOURCE_DISABLED])) {
				mark_subsystem_dirty('chrony');
			}
		}
	}
}

// Handle a save
elseif ($_POST['save']) {
	$pconfig = $_POST;

	// Check for NTPd conflict
	if ($pconfig[CHRONY_CONF_TOK_ENABLE] && config_get_path('ntpd/enable', 'disabled') != 'disabled') {
		$input_errors[] = gettext("The NTP daemon must be disabled before enabling Chrony");
	}

	// Update the config
	if (!$input_errors) {
		config_set_path(CHRONY_CONF_PATH_ENABLE, $pconfig[CHRONY_CONF_TOK_ENABLE]);
		config_set_path(CHRONY_CONF_PATH_IPPROTO, $pconfig[CHRONY_CONF_TOK_IPPROTO]);
		config_set_path(CHRONY_CONF_PATH_NTS_CERT, $pconfig[CHRONY_CONF_TOK_NTS_CERT]);
		config_set_path(CHRONY_CONF_PATH_LOCAL, $pconfig[CHRONY_CONF_TOK_LOCAL]);
		config_set_path(CHRONY_CONF_PATH_ORPHAN, $pconfig[CHRONY_CONF_TOK_LOCAL_ORPHAN]);
		config_set_path(CHRONY_CONF_PATH_STRATUM, $pconfig[CHRONY_CONF_TOK_LOCAL_STRATUM]);

		// Write the config
		write_config(gettext("Chrony daemon: general settings changed"));
		mark_subsystem_dirty('chrony');
	}
}

// Handle an apply
elseif ($_POST['apply']) {
	clear_subsystem_dirty('chrony');

	// Sync the running configuration
	chrony_sync_config();
}

// Available options for IP protocol
$ip_proto_options = array(
	'any' => gettext('Any'),
	'ipv4' => gettext('IPv4 only'),
	'ipv6' => gettext('IPv6 only'));

// Available options for local stratum
$stratum_options = array(
	'1' => gettext('1'),
	'2' => gettext('2'),
	'3' => gettext('3'),
	'4' => gettext('4'),
	'5' => gettext('5'),
	'6' => gettext('6'),
	'7' => gettext('7'),
	'8' => gettext('8'),
	'9' => gettext('9'),
	'10' => gettext('10'),
	'11' => gettext('11'),
	'12' => gettext('12'),
	'13' => gettext('13'),
	'14' => gettext('14'),
	'15' => gettext('15'));

$pgtitle = array(gettext("Services"), gettext("Chrony"));
include("head.inc");

if (is_subsystem_dirty('chrony')) {
	print_apply_box(gettext('The Chrony configuration has changed.') . '<br />' .
			gettext('The changes must be applied to take effect.'));
}

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form;
$section = new Form_Section('General Settings');
$section->addInput(new Form_Checkbox(
	'enable',
	'Enable',
	'Enable the Chrony daemon',
	$pconfig['enable']
));
$section->addInput(new Form_Select(
	CHRONY_CONF_TOK_IPPROTO,
	'IP Protocol',
	$pconfig[CHRONY_CONF_TOK_IPPROTO],
	$ip_proto_options
))->setHelp(gettext('The IP protocol(s) for Chrony to use. The default is Any.'));
$section->addInput(new Form_Select(
	CHRONY_CONF_TOK_NTS_CERT,
	'NTS Certificate',
	$pconfig[CHRONY_CONF_TOK_NTS_CERT],
	cert_build_list('cert', 'HTTPS', false, true)
))->setHelp(gettext('The certificate to use for Network Time Security (NTS), which ' .
	'allows NTP clients to authenticate NTP traffic to and from this server. ' .
	'The default is None, which disables NTS support for client connections.'));
$form->add($section);

$section = new Form_Section('Local Mode');
$section->addInput(new Form_Checkbox(
	CHRONY_CONF_TOK_LOCAL,
	'Local Mode',
	'Enable local mode',
	$pconfig[CHRONY_CONF_TOK_LOCAL]
))->setHelp(gettext('Local mode allows Chrony to operate as an NTP server for ' .
	'clients even when no external NTP servers are available, and is generally ' .
	'used in isolated or offline environments. For additional information, see ' .
	'the "local" directive in the Chrony documentation.'));
$group = new Form_Group('Orphan Mode');
$group->addClass('local');
$group->add(new Form_Checkbox(
	CHRONY_CONF_TOK_LOCAL_ORPHAN,
	'Orphan Option',
	'Enable orphan mode',
	$pconfig[CHRONY_CONF_TOK_LOCAL_ORPHAN]
))->setHelp(gettext('The orphan option enables a group of Chrony servers operating ' .
	'in local mode to automatically choose a single leader. All Chrony servers ' .
	'operating in orphan mode must use the same stratum, and must poll every ' .
	'other orphan server to operate correctly. For additional information, see ' .
	'the "orphan" option of the "local" directive in the Chrony documentation.'));
$section->add($group);
$group = new Form_Group('Local Stratum');
$group->addClass('local');
$group->add(new Form_Select(
	CHRONY_CONF_TOK_LOCAL_STRATUM,
	null,
	(isset($pconfig[CHRONY_CONF_TOK_LOCAL_STRATUM]) ? $pconfig[CHRONY_CONF_TOK_LOCAL_STRATUM] : '10'),
	$stratum_options
))->setHelp(gettext('The stratum to use in local mode. The default is 10.'));
$section->add($group);
$form->add($section);

print($form);
?>

<form method="post">
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('NTP Sources')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table id="ntp_sources" class="table table-striped table-hover table-condensed table-rowdblclickedit">
			<thead>
				<tr>
					<th></th>
					<th><?=gettext('Name')?></th>
					<th><?=gettext('Type')?></th>
					<th><?=gettext('Polling (min/max)')?></th>
					<th><?=gettext('Options')?></th>
					<th><?=gettext('Description')?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach (array_get_path($current_config, CHRONY_CONF_TOK_SOURCE, []) as $id => $source):
				if (isset($source[CHRONY_CONF_TOK_SOURCE_DISABLED])) {
					$icon = 'fa-solid fa-ban';
					$title = gettext('source disabled');
				} else {
					$icon = 'fa-regular fa-circle-check';
					$title = gettext('source enabled');
				}

				// Build the flags entry
				$flag_array = array();
				if (chrony_ip_protocols() == 'any') {
					if ($source[CHRONY_CONF_TOK_SOURCE_IPPROTO] == 'ipv4') {
						$flag_array[] = 'ipv4';
					}
					elseif ($source[CHRONY_CONF_TOK_SOURCE_IPPROTO] == 'ipv6') {
						$flag_array[] = 'ipv6';
					}
				}
				if ($source[CHRONY_CONF_TOK_SOURCE_XLEAVE]) {
					$flag_array[] = 'xleave';
				}
				switch($source[CHRONY_CONF_TOK_SOURCE_PRIORITY]) {
					case 'prefer':
						$flag_array[] = 'prefer';
						break;
					case 'noselect':
						$flag_array[] = 'noselect';
						break;
					case 'trust':
						$flag_array[] = 'trust';
						break;
				}
				if ($source[CHRONY_CONF_TOK_SOURCE_REQUIRE]) {
					$flag_array[] = 'require';
				}
				if ($source[CHRONY_CONF_TOK_SOURCE_FILTER]) {
					$flag_array[] = 'filter(' . $source[CHRONY_CONF_TOK_SOURCE_FILTER] . ')';
				}
				if ($source[CHRONY_CONF_TOK_SOURCE_NTS]) {
					$flag_array[] = 'nts';
				}
				$flags = implode(', ', $flag_array);
				?>

				<tr<?=($icon != 'fa-regular fa-circle-check')? ' class="disabled"' : ''?> onClick="fr_toggle(<?=$id;?>)" id="fr<?=$id;?>">
					<td title="<?=$title?>"><i class="<?=$icon?>"></i></td>
					<td>
						<?=htmlspecialchars($source[CHRONY_CONF_TOK_SOURCE_NAME])?>
					</td>
					<td>
						<?=htmlspecialchars($source[CHRONY_CONF_TOK_SOURCE_TYPE])?>
						<?php if ($source[CHRONY_CONF_TOK_SOURCE_TYPE] == 'pool') { ?>
							(<?=htmlspecialchars($source[CHRONY_CONF_TOK_SOURCE_MAXSOURCES])?>)
						<?php }?>
					</td>
					<td>
						<?=htmlspecialchars($source[CHRONY_CONF_TOK_SOURCE_POLLMIN])?> /
						<?=htmlspecialchars($source[CHRONY_CONF_TOK_SOURCE_POLLMAX])?>
					</td>
					<td>
						<?=htmlspecialchars($flags)?>
					</td>
					<td>
						<?=htmlspecialchars($source[CHRONY_CONF_TOK_SOURCE_DESC])?>
					</td>
					<td style="white-space: nowrap;">
						<a href="chrony_edit.php?id=<?=$id?>" class="fa-solid fa-pencil" title="<?=gettext('Edit source');?>"></a>
						<a href="chrony_edit.php?dup=<?=$id?>" class="fa-regular fa-clone" title="<?=gettext('Duplicate source')?>"></a>
						<?php if (isset($source[CHRONY_CONF_TOK_SOURCE_DISABLED])) { ?>
							<a href="?act=toggle&amp;id=<?=$id?>" class="fa-regular fa-square-check" title="<?=gettext('Enable source')?>" usepost></a>
						<?php } else { ?>
							<a href="?act=toggle&amp;id=<?=$id?>" class="fa-solid fa-ban" title="<?=gettext('Disable source')?>" usepost></a>
						<?php } ?>
						<a href="?act=delete&amp;id=<?=$id?>" class="fa-solid fa-trash-can" title="<?=gettext('Delete source')?>" usepost></a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
			</table>
		</div>
	</div>
</div>
<div class="panel-body">
<nav class="action-buttons">
	<a href="chrony_edit.php" role="button" class="btn btn-success">
		<i class="fa-solid fa-plus icon-embed-btn"></i>
		<?=gettext('Add');?>
	</a>
</nav>
</div>
</form>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	// Show/hide on local mode
	function hideOnLocal() {
		hideClass('local', !$('#local').prop('checked'));
	}

	// On changing local mode
	$('#local').change(function() {
		hideOnLocal();
	});

	// Initial page load
	hideOnLocal();
});
//]]>
</script>

<?php include("foot.inc");
