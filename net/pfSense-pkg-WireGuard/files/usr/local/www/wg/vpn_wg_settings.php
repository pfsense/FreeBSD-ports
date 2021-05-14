<?php
/*
 * vpn_wg_settings.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2021 R. Christian McDonald
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
require_once('wireguard/wg.inc');
require_once('wireguard/wg_guiconfig.inc');

global $wgg;

wg_globals();

$save_success = false;

if ($_POST) {

	if ($_POST['act'] == 'save') {

		if (!$input_errors) {

			$pconfig = $_POST;

			$wgg['config']['keep_conf'] = $pconfig['keep_conf'];
			
			$wgg['config']['hide_secrets'] = $pconfig['hide_secrets'];

			write_config('[WireGuard] Save WireGuard settings');

			$save_success = true;

		}

	}

} else {

	// Default to yes if not set (i.e. a new installation)
	$pconfig['keep_conf'] = isset($wgg['config']['keep_conf']) ? $wgg['config']['keep_conf'] : 'yes';

	$pconfig['hide_secrets'] = $wgg['config']['hide_secrets'];

}

$shortcut_section = "wireguard";

$pgtitle = array(gettext("VPN"), gettext("WireGuard"), gettext("Settings"));
$pglinks = array("", "/wg/vpn_wg_tunnels.php", "@self");

$tab_array = array();
$tab_array[] = array(gettext("Tunnels"), false, "/wg/vpn_wg_tunnels.php");
$tab_array[] = array(gettext("Peers"), false, "/wg/vpn_wg_peers.php");
$tab_array[] = array(gettext("Settings"), true, "/wg/vpn_wg_settings.php");
$tab_array[] = array(gettext("Status"), false, "/wg/status_wireguard.php");

include("head.inc");

if ($save_success) {

	print_info_box(gettext("The changes have been applied successfully."), 'success');
	
}

if (count($wgg['tunnels']) > 0 && !is_module_loaded($wgg['kmod'])) {

	print_info_box(gettext('The WireGuard kernel module is not loaded!'), 'danger', null);

}

if ($input_errors) {

	print_input_errors($input_errors);
	
}

display_top_tabs($tab_array);

$form = new Form(false);

$section = new Form_Section("General Settings");

$section->addInput(new Form_Checkbox(
	'keep_conf',
	'Keep Configuration',
    	gettext('Enable'),
    	$pconfig['keep_conf'] == 'yes'
))->setHelp('<span class="text-danger">Note: </span>'
		. 'With \'Keep Configurations\' enabled (default), all tunnel configurations and package settings will persist on install/de-install.'
);

$form->add($section);

$section = new Form_Section("User Interface Settings");

$section->addInput(new Form_Checkbox(
	'hide_secrets',
	'Hide Secrets',
    	gettext('Enable'),
    	$pconfig['hide_secrets'] == 'yes'
))->setHelp('<span class="text-danger">Note: </span>'
		. 'With \'Hide Secrets\' enabled, all secrets (private and pre-shared keys) are hidden in the user interface.');

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
		<i class="fa fa-save icon-embed-btn"></i>
		<?=gettext("Save")?>
	</button>
</nav>

<!-- ============== JavaScript =================================================================================================-->
<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// Save the form
	$('#saveform').click(function () {

		$(form).submit();

	});

});
//]]>
</script>

<?php 

include('foot.inc');

// Must be included last
include('wireguard/wg_foot.inc');

?>