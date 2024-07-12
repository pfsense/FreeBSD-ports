<?php
/*
 * freeradius_view_config.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013 Alexander Wilke <nachtfalkeaw@web.de>
 * Copyright (c) 2011 Marcello Coutinho <marcellocoutinho@gmail.com>
 * All rights reserved.
 *
 * Originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (C) 2003-2006 Manuel Kasper <mk@neon1.net>.
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
require("freeradius.inc");

function get_file($file) {
	$files['radiusd'] = FREERADIUS_RADDB . "/radiusd.conf";
	$files['eap'] = FREERADIUS_MODSENABLED . "/eap";
	$files['sql'] = FREERADIUS_MODSENABLED . "/sql";
	$files['clients'] = FREERADIUS_RADDB . "/clients.conf";
	$files['users'] = FREERADIUS_RADDB . "/users";
	$files['macs'] = FREERADIUS_RADDB . "/authorized_macs";
	$files['virtual-server-default'] = FREERADIUS_RADDB . "/sites-enabled/default";
	$files['ldap'] = FREERADIUS_MODSENABLED . "/ldap";

	if ($files[$file] != "" && file_exists($files[$file])) {
		print '<pre>';
		print $files[$file] . "\n" . htmlspecialchars(file_get_contents($files[$file]));
		print '</pre>';
	}
}

if ($_REQUEST['file'] != "") {
	get_file($_REQUEST['file']);
	return;
}

$pgtitle = array(gettext("Services"), gettext("FreeRADIUS"), gettext("View Configuration"));
require("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Users"), false, "/pkg.php?xml=freeradius.xml");
$tab_array[] = array(gettext("MACs"), false, "/pkg.php?xml=freeradiusauthorizedmacs.xml");
$tab_array[] = array(gettext("NAS / Clients"), false, "/pkg.php?xml=freeradiusclients.xml");
$tab_array[] = array(gettext("Interfaces"), false, "/pkg.php?xml=freeradiusinterfaces.xml");
$tab_array[] = array(gettext("Settings"), false, "/pkg_edit.php?xml=freeradiussettings.xml&id=0");
$tab_array[] = array(gettext("EAP"), false, "/pkg_edit.php?xml=freeradiuseapconf.xml&id=0");
$tab_array[] = array(gettext("SQL"), false, "/pkg_edit.php?xml=freeradiussqlconf.xml&id=0");
$tab_array[] = array(gettext("LDAP"), false, "/pkg_edit.php?xml=freeradiusmodulesldap.xml&id=0");
$tab_array[] = array(gettext("View Config"), true, "/freeradius_view_config.php");
$tab_array[] = array(gettext("XMLRPC Sync"), false, "/pkg_edit.php?xml=freeradiussync.xml");
display_top_tabs($tab_array);

if ($savemsg) {
	print_info_box($savemsg);
}
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("View FreeRADIUS Configuration Files"); ?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<form action="freeradius_view_config.php" method="post">
			<table class="table table-hover table-condensed">
				<thead>
				<tr>
					<th class="text-center">
						<div class="btn-group">
							<button type="button" class="btn btn-default btn-sm" onClick="get_freeradius_file('radiusd');" id='btn_radiusd' value="radiusd.conf">
								<i class="fa-regular fa-file-lines"></i>
								radiusd.conf
							</button>
							<button type="button" class="btn btn-default btn-sm" onClick="get_freeradius_file('eap');" id='btn_eap' value="eap.conf">
								<i class="fa-regular fa-file-lines"></i>
								eap
							</button>
							<button type="button" class="btn btn-default btn-sm" onClick="get_freeradius_file('sql');" id='btn_sql' value="sql.conf">
								<i class="fa-regular fa-file-lines"></i>
								sql
							</button>
							<button type="button" class="btn btn-default btn-sm" onClick="get_freeradius_file('clients');" id='btn_clients' value="clients.conf">
								<i class="fa-regular fa-file-lines"></i>
								clients.conf
							</button>
							<button type="button" class="btn btn-default btn-sm" onClick="get_freeradius_file('users');" id='btn_users' value="users">
								<i class="fa-regular fa-file-lines"></i>
								users
							</button>
							<button type="button" class="btn btn-default btn-sm" onClick="get_freeradius_file('macs');" id='btn_macs' value="macs">
								<i class="fa-regular fa-file-lines"></i>
								macs
							</button>
							<button type="button" class="btn btn-default btn-sm" onClick="get_freeradius_file('virtual-server-default');" id='btn_virtual-server-default' value="virtual-server-default">
								<i class="fa-regular fa-file-lines"></i>
								virtual-server-default
							</button>
							<button type="button" class="btn btn-default btn-sm" onClick="get_freeradius_file('ldap');" id='btn_ldap' value="ldap">
								<i class="fa-regular fa-file-lines"></i>
								ldap
							</button>
						</div>
					</th>
				</tr>
				</thead>
				<tbody>
					<tr>
						<td colspan="12">
							<div id="file_div">Click one of the buttons above to display its contents.</div>
						</td>
					</tr>
				</tbody>
			</table>
			</form>
		</div>
	</div>
</div>
<script type="text/javascript">
//<![CDATA[
function get_freeradius_file(file) {
	$('#btn_'+file).value="reading...";
	var pars = 'file='+file;
	var url = "/freeradius_view_config.php";
	jQuery.ajax(url,
		{
		type: 'post',
		data: pars,
		success: activitycallback_freeradius_file,
		error: function() {
			$('#file_div').html("<div class=\"alert alert-danger\"><?=gettext('Unable to retrieve file'); ?></div>");
		}
	});
}
function activitycallback_freeradius_file(html) {
	$('#file_div').html(html);
	$('#btn_radiusd').value="radiusd.conf";
	$('#btn_eap').value="eap.conf";
	$('#btn_sql').value="sql.conf";
	$('#btn_clients').value="clients.conf";
	$('#btn_users').value="users";
	$('#btn_macs').value="macs";
	$('#btn_virtual').value="virtual-server-default";
	$('#btn_ldap').value="ldap";
	scroll(0,0);
}
//]]>
</script>

<?php include("foot.inc"); ?>
