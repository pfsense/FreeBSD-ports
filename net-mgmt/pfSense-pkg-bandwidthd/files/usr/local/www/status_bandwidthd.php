<?php
/*
 * diag_bandwidthd.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2024 Rubicon Communications, LLC (Netgate)
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
$shortcut_section = 'bandwidthd';
$pgtitle = array(gettext('Status'), gettext('BandwidthD'));
include("head.inc");


$tab_array = array();
$tab_array[] = array(gettext("Settings"), false, "/pkg_edit.php?xml=bandwidthd.xml");
$tab_array[] = array(gettext('Status'), true, '/status_bandwidthd.php');
add_package_tabs("BandwidthD", $tab_array);
display_top_tabs($tab_array);

?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('BandwithD Statistics - Framed View')?> <a href="/bandwidthd/index.html">[<?= gettext("click to remove frame") ?>]</a></h2></div>
	<div class="panel panel-body">
		<iframe id="bandwidthd" src="/bandwidthd/index.html" scrolling="no" style="overflow:hidden; width: 100%; height: 100%; max-width: 100%;"></iframe>
	</div>
</div>

<?php include("foot.inc"); ?>
<script>
$('#bandwidthd').on('load', function() {
	/* Find height of iframe contnet and then add 20px for padding */
	$(this).height( $(this).contents().find("body").height() + 20 );
});
</script>
