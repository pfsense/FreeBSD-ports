<?php
/*
*  suricata_suppress.php
*
*  Copyright (c)  2004-2016  Electric Sheep Fencing, LLC. All rights reserved.
*
*  Redistribution and use in source and binary forms, with or without modification,
*  are permitted provided that the following conditions are met:
*
*  1. Redistributions of source code must retain the above copyright notice,
*      this list of conditions and the following disclaimer.
*
*  2. Redistributions in binary form must reproduce the above copyright
*      notice, this list of conditions and the following disclaimer in
*      the documentation and/or other materials provided with the
*      distribution.
*
*  3. All advertising materials mentioning features or use of this software
*      must display the following acknowledgment:
*      "This product includes software developed by the pfSense Project
*       for use in the pfSense software distribution. (http://www.pfsense.org/).
*
*  4. The names "pfSense" and "pfSense Project" must not be used to
*       endorse or promote products derived from this software without
*       prior written permission. For written permission, please contact
*       coreteam@pfsense.org.
*
*  5. Products derived from this software may not be called "pfSense"
*      nor may "pfSense" appear in their names without prior written
*      permission of the Electric Sheep Fencing, LLC.
*
*  6. Redistributions of any form whatsoever must retain the following
*      acknowledgment:
*
*  "This product includes software developed by the pfSense Project
*  for use in the pfSense software distribution (http://www.pfsense.org/).
*
*  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
*  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
*  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
*  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
*  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
*  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
*  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
*  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
*  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
*  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
*  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
*  OF THE POSSIBILITY OF SUCH DAMAGE.
*
*
* Portions of this code are based on original work done for the Snort package for pfSense by the following contributors:
*
* Copyright (C) 2003-2004 Manuel Kasper
* Copyright (C) 2005 Bill Marquette
* Copyright (C) 2006 Scott Ullrich (copyright assigned to ESF)
* Copyright (C) 2009 Robert Zelaya Sr. Developer
* Copyright (C) 2012 Ermal Luci  (copyright assigned to ESF)
* Copyright (C) 2016 Bill Meeks
*
*/

require_once("guiconfig.inc");
require_once("/usr/local/pkg/suricata/suricata.inc");

if (!is_array($config['installedpackages']['suricata']['rule']))
	$config['installedpackages']['suricata']['rule'] = array();
if (!is_array($config['installedpackages']['suricata']['suppress']))
	$config['installedpackages']['suricata']['suppress'] = array();
if (!is_array($config['installedpackages']['suricata']['suppress']['item']))
	$config['installedpackages']['suricata']['suppress']['item'] = array();
$a_suppress = &$config['installedpackages']['suricata']['suppress']['item'];
$id_gen = count($config['installedpackages']['suricata']['suppress']['item']);

function suricata_suppresslist_used($supplist) {

	/****************************************************************/
	/* This function tests if the passed Suppress List is currently */
	/* assigned to an interface.  It returns TRUE if the list is	*/
	/* in use.							*/
	/*								*/
	/* Returns:  TRUE if list is in use, else FALSE			*/
	/****************************************************************/

	global $config;

	$suricataconf = $config['installedpackages']['suricata']['rule'];
	if (empty($suricataconf))
		return false;
	foreach ($suricataconf as $value) {
		if ($value['suppresslistname'] == $supplist)
			return true;
	}
	return false;
}

function suricata_find_suppresslist_interface($supplist) {

	/****************************************************************/
	/* This function finds the first (if more than one) interface   */
	/* configured to use the passed Suppress List and returns the   */
	/* index of the interface in the ['rule'] config array.		*/
	/*								*/
	/* Returns: index of interface in ['rule'] config array or	*/
	/*		  FALSE if no interface found.			*/
	/****************************************************************/

	global $config;
	$suricataconf = $config['installedpackages']['suricata']['rule'];
	if (empty($suricataconf))
		return false;
	foreach ($suricataconf as $rule => $value) {
		if ($value['suppresslistname'] == $supplist)
			return $rule;
	}
	return false;
}

if (isset($_POST['del_btn'])) {
	$need_save = false;
	if (is_array($_POST['del']) && count($_POST['del'])) {
		foreach ($_POST['del'] as $itemi) {
			/* make sure list is not being referenced by any interface */
			if (suricata_suppresslist_used($a_suppress[$itemi]['name'])) {
				$input_errors[] = gettext("Suppression List '{$a_suppress[$itemi]['name']}' is currently assigned to a Suricata interface and cannot be deleted.  Unassign it from all Suricata interfaces first.");
			} else {
				unset($a_suppress[$itemi]);
				$need_save = true;
			}
		}
		if ($need_save) {
			write_config("Suricata pkg: deleted SUPPRESSION LIST.");
			conf_mount_rw();
			sync_suricata_package_config();
			conf_mount_ro();
			header("Location: /suricata/suricata_suppress.php");
			return;
		}
	}
}

$pgtitle = array(gettext("Services"), gettext("Suricata"), gettext("Suppression Lists"));
include_once("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[] = array(gettext("Interfaces"), false, "/suricata/suricata_interfaces.php");
$tab_array[] = array(gettext("Global Settings"), false, "/suricata/suricata_global.php");
$tab_array[] = array(gettext("Updates"), false, "/suricata/suricata_download_updates.php");
$tab_array[] = array(gettext("Alerts"), false, "/suricata/suricata_alerts.php");
$tab_array[] = array(gettext("Blocks"), false, "/suricata/suricata_blocked.php");
$tab_array[] = array(gettext("Pass Lists"), false, "/suricata/suricata_passlist.php");
$tab_array[] = array(gettext("Suppress"), true, "/suricata/suricata_suppress.php");
$tab_array[] = array(gettext("Logs View"), false, "/suricata/suricata_logs_browser.php");
$tab_array[] = array(gettext("Logs Mgmt"), false, "/suricata/suricata_logs_mgmt.php");
$tab_array[] = array(gettext("SID Mgmt"), false, "/suricata/suricata_sid_mgmt.php");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
$tab_array[] = array(gettext("IP Lists"), false, "/suricata/suricata_ip_list_mgmt.php");
display_top_tabs($tab_array, true);
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Configured Suppression Lists');?></h2></div>
	<div class="table-responsive panel-body">
		<form action="/suricata/suricata_suppress.php" method="post"><?php if ($savemsg) print_info_box($savemsg); ?>
			<input type="hidden" name="list_id" id="list_id" value=""/>

			<table id="maintable" class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th>&nbsp;</th>
						<th><?=gettext("List Name"); ?></th>
						<th><?=gettext("Description"); ?></th>
						<th><?=gettext("Actions"); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php $i = 0; foreach ($a_suppress as $list): ?>
					<?php
						if (suricata_suppresslist_used($list['name'])) {
							$icon = "&nbsp;<i class=\"fa fa-info-circle\" style=\"cursor: pointer;\" title=\"" . gettext("List is in use by an instance") . "\"></i>";
						}
						else
							$icon = "";
					?>
					<tr>
						<td>
							<input type="checkbox" name="del[]" value="<?=$i?>" />
						</td>
						<td>
							<?=htmlspecialchars($list['name'])?> <?=$icon?>
						</td>
						<td>
							<?=htmlspecialchars($list['descr'])?>
						</td>
						<td>
							<a href="suricata_suppress_edit.php?id=<?=$i?>">
								<i class="fa fa-pencil fa-lg" title="<?=gettext("Edit Suppress List"); ?>"></i>
							</a>
							<?php if (suricata_suppresslist_used($list['name'])) : ?>
							<a href="/suricata/suricata_interfaces_edit.php?id=<?=suricata_find_suppresslist_interface($list['name'])?>">
								<i class="fa fa-caret-square-o-right" title="<?=gettext('Goto first instance associated with this Suppress List')?>" style="cursor: pointer;"?></i>
							</a>
							<?php endif; ?>
						</td>
					</tr>
					<?php $i++; endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<nav class="action-buttons">
		<a href="suricata_suppress_edit.php?id=<?=$id_gen?>" class="btn btn-sm btn-success" title="<?=gettext('Add a new suppression list');?>">
			<i class="fa fa-plus icon-embed-btn"></i> <?=gettext("Add");?>
		</a>
		<?php if (count($a_suppress) > 0): ?>
		<button type="submit" name="del_btn" id="del_btn" class="btn btn-danger btn-sm" title="<?=gettext('Delete Selected Items');?>">
			<i class="fa fa-trash icon-embed-btn"></i>
			<?=gettext('Delete');?>
		</button>
		<?php endif; ?>
	</nav>
</form>


<div class="infoblock">
	<?=print_info_box('<p><strong>Note:</strong> Here you can create event filtering and suppression for your Suricata package rules.</p><p>Please note that you must restart a running Interface so that changes can take effect.</p><p>You cannot delete a Suppress List that is currently assigned to a Suricata interface (instance).</p><p>You must first unassign the Suppress List on the Interface Edit tab.</p>', info)?>
</div>

<?php include("foot.inc"); ?>
