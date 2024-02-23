<?php
/*
 * vpn_wg_settings.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2021 R. Christian McDonald (https://github.com/rcmcdonald91)
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

##|+PRIV
##|*IDENT=page-vpn-wireguard
##|*NAME=VPN: WireGuard: Settings
##|*DESCR=Allow access to the 'VPN: WireGuard' page.
##|*MATCH=vpn_wg_settings.php*
##|-PRIV

// pfSense includes
require_once('functions.inc');
require_once('guiconfig.inc');

// WireGuard includes
require_once('wireguard/includes/wg.inc');
require_once('wireguard/includes/wg_guiconfig.inc');

global $wgg;

// Initialize $wgg state
wg_globals();

$save_success = false;

if ($_POST) {
	if (isset($_POST['apply'])) {
		$ret_code = 0;

		if (is_subsystem_dirty($wgg['subsystems']['wg'])) {
			if (wg_is_service_running()) {
				$tunnels_to_apply = wg_apply_list_get('tunnels');
				$sync_status = wg_tunnel_sync($tunnels_to_apply, true, true);
				$ret_code |= $sync_status['ret_code'];
			}

			if ($ret_code == 0) {
				clear_subsystem_dirty($wgg['subsystems']['wg']);
			}
		}
	}

	if (isset($_POST['act'])) {
		switch ($_POST['act']) {
			case 'save':
				$res = wg_do_settings_post($_POST);
				$input_errors = $res['input_errors'];
				$pconfig = $res['pconfig'];

				if (empty($input_errors) && $res['changes']) {
					wg_toggle_wireguard();
					mark_subsystem_dirty($wgg['subsystems']['wg']);
					$save_success = true;
				}

				break;

			default:
				// Shouldn't be here, so bail out.
				header('Location: /wg/vpn_wg_settings.php');
				break;
		}
	}
}

// A dirty string hack
$s = fn($x) => $x;

// Just to make sure defaults are properly assigned if anything is missing
wg_defaults_install();

// Grab current configuration from the XML
$pconfig = $wgg['config'];

$shortcut_section = 'wireguard';

$pgtitle = array(gettext('VPN'), gettext('WireGuard'), gettext('Settings'));
$pglinks = array('', '/wg/vpn_wg_tunnels.php', '@self');

$tab_array = array();
$tab_array[] = array(gettext('Tunnels'), false, '/wg/vpn_wg_tunnels.php');
$tab_array[] = array(gettext('Peers'), false, '/wg/vpn_wg_peers.php');
$tab_array[] = array(gettext('Settings'), true, '/wg/vpn_wg_settings.php');
$tab_array[] = array(gettext('Status'), false, '/wg/status_wireguard.php');

include('head.inc');

wg_print_service_warning();

if ($save_success) {
	//print_info_box(gettext('The changes have been applied successfully.'), 'success');
}

if (isset($_POST['apply'])) {
	print_apply_result_box($ret_code);
}

wg_print_config_apply_box();

if (!empty($input_errors)) {
	print_input_errors($input_errors);
}

display_top_tabs($tab_array);

$form = new Form(false);

$section = new Form_Section(gettext('General Settings'));

$wg_enable = new Form_Checkbox(
	'enable',
	gettext('Enable'),
	gettext('Enable WireGuard'),
	wg_is_service_enabled()
);

$wg_enable->setHelp("<span class=\"text-danger\">{$s(gettext('Note:'))} </span>
		     {$s(gettext('WireGuard cannot be disabled when one or more tunnels is assigned to a pfSense interface.'))}");

if (wg_is_wg_assigned()) {
	$wg_enable->setDisabled();

	// We still want to POST this field, make it a hidden field now
	$form->addGlobal(new Form_Input(
		'enable',
		'',
		'hidden',
		(wg_is_service_enabled() ? 'yes' : 'no')
	));
}

$section->addInput($wg_enable);

$section->addInput(new Form_Checkbox(
	'keep_conf',
	gettext('Keep Configuration'),
	gettext('Enable'),
	$pconfig['keep_conf'] == 'yes'
))->setHelp("<span class=\"text-danger\">{$s(gettext('Note:'))} </span>
	     {$s(gettext("With 'Keep Configurations' enabled (default), all tunnel configurations and package settings will persist on install/de-install."))}");

$group = new Form_Group(gettext('Endpoint Hostname Resolve Interval'));

$group->add(new Form_Input(
	'resolve_interval',
	gettext('Endpoint Hostname Resolve Interval'),
	'text',
	wg_get_endpoint_resolve_interval(),
	['placeholder' => wg_get_endpoint_resolve_interval()]
))->addClass('trim')
  ->setHelp("{$s(gettext('Interval (in seconds) for re-resolving endpoint host/domain names.'))}<br />
	     <span class=\"text-danger\">{$s(gettext('Note:'))} </span> {$s(sprintf('The default is %s seconds (0 to disable).', $wgg['default_resolve_interval']))}");

$group->add(new Form_Checkbox(
	'resolve_interval_track',
	null,
	gettext('Track System Resolve Interval'),
	($pconfig['resolve_interval_track'] == 'yes')
))->setHelp("{$s(gettext("Tracks the system 'Aliases Hostnames Resolve Interval' setting."))}<br />
	     <span class=\"text-danger\">{$s(gettext('Note:'))} </span> See System &gt; Advanced &gt; <a href=\"/system_advanced_firewall.php\">Firewall &amp; NAT</a>");

$section->add($group);

$interface_group_list = array('all' => gettext('All Tunnels'), 'unassigned' => gettext('Only Unassigned Tunnels'), 'none' => gettext('None'));

$section->addInput($input = new Form_Select(
	'interface_group',
	gettext('Interface Group Membership'),
	$pconfig['interface_group'],
	$interface_group_list
))->setHelp("{$s(gettext('Configures which WireGuard tunnels are members of the WireGuard interface group.'))}<br />
	     <span class=\"text-danger\">{$s(gettext('Note:'))} </span> {$s(sprintf(gettext("Group firewall rules are evaluated before interface firewall rules. Default is '%s.'"), $interface_group_list['all']))}");

$form->add($section);

$section = new Form_Section(gettext('User Interface Settings'));

$section->addInput(new Form_Checkbox(
	'hide_secrets',
	gettext('Hide Secrets'),
    	gettext('Enable'),
    	$pconfig['hide_secrets'] == 'yes'
))->setHelp("<span class=\"text-danger\">{$s(gettext('Note:'))} </span>
		{$s(gettext("With 'Hide Secrets' enabled, all secrets (private and pre-shared keys) are hidden in the user interface."))}");

$section->addInput(new Form_Checkbox(
	'hide_peers',
	gettext('Hide Peers'),
	gettext('Enable'),
	$pconfig['hide_peers'] == 'yes'
))->setHelp("<span class=\"text-danger\">{$s(gettext('Note:'))} </span>
		{$s(gettext("With 'Hide Peers' enabled (default), all peers for all tunnels will initially be hidden on the status page."))}");
		
$form->add($section);

$form->addGlobal(new Form_Input(
	'act',
	'',
	'hidden',
	'save'
));

print($form);

?>

<nav class="action-buttons">
	<button type="submit" id="saveform" name="saveform" class="btn btn-sm btn-primary" value="save" title="<?=gettext('Save Settings')?>">
		<i class="fa-solid fa-save icon-embed-btn"></i>
		<?=gettext('Save')?>
	</button>
</nav>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	wgRegTrimHandler();

	// Save the form
	$('#saveform').click(function () {
		$(form).submit();
	});

	$('#resolve_interval_track').click(function () {
		updateResolveInterval(this.checked);
	});

	function updateResolveInterval(state) {
		$('#resolve_interval').prop( "disabled", state);
	}

	updateResolveInterval($('#resolve_interval_track').prop('checked'));
});
//]]>
</script>

<?php
include('wireguard/includes/wg_foot.inc');
include('foot.inc');
?>
