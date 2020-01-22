<?php
/*
 * status_pimd.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2020 Rubicon Communications, LLC (Netgate)
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

require("guiconfig.inc");
global $config;

init_config_arr(array('installedpackages', 'pimd', 'config', 0));
$enabled = (!empty($config['installedpackages']['pimd']['config'][0]) &&
	    ($config['installedpackages']['pimd']['config'][0]['enable'] == 'on'));

$tab_array = array();

$tab_array[] = array(gettext("General"), false, "/pkg_edit.php?xml=pimd.xml");
$tab_array[] = array(gettext("Interfaces"), false, "/pkg.php?xml=pimd/pimd_interfaces.xml");
$tab_array[] = array(gettext("BSR Candidates"), false, "/pkg.php?xml=pimd/pimd_bsrcandidate.xml");
$tab_array[] = array(gettext("RP Candidates"), false, "/pkg.php?xml=pimd/pimd_rpcandidate.xml");
$tab_array[] = array(gettext("RP Addresses"), false, "/pkg.php?xml=pimd/pimd_rpaddress.xml");
$tab_array[] = array(gettext("Status"), true, "/status_pimd.php");

$pgtitle = array(gettext("Status"), gettext("pimd"));

$shortcut_section = "pimd";

include("head.inc");
display_top_tabs($tab_array);
?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext("PIMD Routes");?></h2>
	</div>
	<div class="panel-body">
		<div class="table-responsive">
<?php if ($enabled): ?>
	<?php if (is_service_running('pimd')): ?>
			<pre>
			<?= htmlspecialchars(shell_exec('/usr/local/sbin/pimd --show-routes')); ?>
			</pre>
	<?php else: ?>
			<br/>
			<p class="text-center"><?= gettext('PIMD is enabled but not running. Check the configuration.'); ?></p>
			<br/>
	<?php endif; ?>
<?php else: ?>
			<br/>
			<p class="text-center"><?= gettext('PIMD is disabled. Enable PIMD on the General tab.'); ?></p>
			<br/>
<?php endif; ?>
		</div>
	</div>
</div>

<?php include("foot.inc");
