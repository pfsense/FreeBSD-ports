<?php

/*
 * e2guardian_about.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2017 Marcello Coutinho
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

require_once("/etc/inc/util.inc");
require_once("/etc/inc/functions.inc");
require_once("/etc/inc/pkg-utils.inc");
require_once("/etc/inc/globals.inc");
require_once("guiconfig.inc");

$pgtitle = array(gettext("Package"), gettext("Services: E2guardian"), gettext("About"));
$shortcut_section = "e2guardian";
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Daemon"), false, "/pkg_edit.php?xml=e2guardian.xml&id=0");
$tab_array[] = array(gettext("General"), false, "/pkg_edit.php?xml=e2guardian/e2guardian_config.xml&id=0");
$tab_array[] = array(gettext("Limits"), false, "/pkg_edit.php?xml=e2guardian/e2guardian_limits.xml&id=0");
$tab_array[] = array(gettext("Blacklist"), false, "/pkg_edit.php?xml=e2guardian/e2guardian_blacklist.xml&id=0");
$tab_array[] = array(gettext("ACLs"), false, "/pkg.php?xml=e2guardian/e2guardian_site_acl.xml");
$tab_array[] = array(gettext("LDAP"), false, "/pkg.php?xml=e2guardian/e2guardian_ldap.xml&id=0");
$tab_array[] = array(gettext("Groups"), false, "/pkg.php?xml=e2guardian/e2guardian_groups.xml&id=0");
$tab_array[] = array(gettext("Users"), false, "/pkg_edit.php?xml=e2guardian/e2guardian_users.xml&id=0");
$tab_array[] = array(gettext("IPs"), false, "/pkg_edit.php?xml=e2guardian/e2guardian_ips.xml&id=0");
$tab_array[] = array(gettext("Real Time"), false, "/e2guardian/e2guardian_monitor.php");
$tab_array[] = array(gettext("Report and Log"), false, "/pkg_edit.php?xml=e2guardian/e2guardian_log.xml&id=0");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=e2guardian/e2guardian_sync.xml&id=0");
$tab_array[] = array(gettext("Help"), true, "/e2guardian/e2guardian_about.php");
display_top_tabs($tab_array);

?>
<div class="panel panel-default">
        <div class="panel-heading"><h2 class="panel-title"><?=gettext("About E2guardian"); ?></h2></div>
        <div class="panel-body">
        <div class="table-responsive">
                <table class="table table-hover table-condensed">
                                <tbody>

						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Blacklists");?></td>
							<td width="78%" class="vtable"><?=gettext("<a target=_new href='http://www.squidguard.org/blacklists.html'>E2guardian Blacklists</a><br><br>");?>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Whatis");?></td>
							<td width="78%" class="vtable"><a target=_new href='http://e2guardian.org/'><?=gettext("What is E2guardian</a><br><br>");?>
						</tr>
                                                <tr>
                                                        <td width="22%" valign="top" class="vncell"><?=gettext("Configuration");?></td>
                                                        <td width="78%" class="vtable"><a target=_new href='https://github.com/e2guardian/e2guardian/wiki/Configuration'><?=gettext("How to configure")?></a><br><br>
                                                </tr>
                                                <tr>
                                                        <td width="22%" valign="top" class="vncell"><?=gettext("Wiki");?></td>
                                                        <td width="78%" class="vtable"><a target=_new href='https://github.com/e2guardian/e2guardian/wiki/Configuration'><?=gettext("Configuration Wiki");?></a><br><br>
                                                </tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Credits ");?></td>
							<td width="78%" class="vtable"><?=gettext("Package Created by <a target=_new href='http://forum.pfsense.org/index.php?action=profile;u=4710'>Marcello Coutinho</a><br><br>");?></td>
						</tr>
						</table>
				</div>
			</td>
		</tr>
	</table>
	<br>
</div>
<?php include("foot.inc"); ?>
</body>
</html>


