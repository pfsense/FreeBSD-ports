<?php
/*
*	suricata_import_aliases.php
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

/************************************************************************************
	This file contains code for selecting and importing an existing Alias.
	It is included and injected inline from other Suricata PHP pages that
	use the Import Alias functionality.

	The following variables are assumed to exist and must be initialized
	as necessary in order to utilize this page.

	$g --> system global variables array
	$config --> global variable pointing to configuration information
	$a_aliases --> $config['aliases']['alias'] array
	$title --> title string for import alias engine type
	$used --> array of currently used engine 'bind_to' Alias names
	$selectalias --> boolean to display radio buttons instead of checkboxes
	$mode --> string value to indicate current operation mode

	Information is returned from this page via the following form fields:

	aliastoimport[] --> checkbox array containing selected alias names
	save_import_alias --> Submit button for save operation and exit
	cancel_import_alias --> Submit button to cancel operation and exit
 ************************************************************************************/
?>

<?php	$selectablealias = false;
	if (!is_array($a_aliases))
		$a_aliases = array();
	if ($mode != "")
		echo '<input type="hidden" name="mode" id="mode" value="' . $mode . '"/>';
	if ($selectalias == true) {
		$fieldtype = "radio";
		$header = gettext("Select an Alias to use as {$title} target from the list below.");
	}
	else {
		$fieldtype = "checkbox";
		$header = gettext("Select one or more Aliases to use as {$title} targets from the list below.");
	}
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=$header?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th></th>
						<th><?=gettext("Alias Name"); ?></th>
						<th><?=gettext("Values"); ?></th>
						<th><?=gettext("Description"); ?></th>
					</tr>
				</thead>
				<tbody>
				  <?php $i = 0; foreach ($a_aliases as $alias): ?>
					<?php if ($alias['type'] != "host" && $alias['type'] != "network")
						continue;
						  if (isset($used[$alias['name']]))
						continue;
						  elseif (trim(filter_expand_alias($alias['name'])) == "") {
							$textss = "<span class=\"gray\">";
							$textse = "</span>";
							$disable = true;
							$tooltip = gettext("Aliases representing a FQDN host cannot be used in Suricata Host OS Policy configurations.");
						  }
						  else {
							$textss = "";
							$textse = "";
							$disable = "";
							$selectablealias = true;
							$tooltip = gettext("Selected entries will be imported. Click to toggle selection of this entry.");
						  }
					?>
					<?php if ($disable): ?>
					<tr title="<?=$tooltip?>">
					  <td class="text-center">
					  	<img src="/icon_block_d.gif" width="11" height"11" border="0"/>
					<?php else: ?>
					<tr>
					  <td class="text-center">
					  	<input type="<?=$fieldtype?>" name="aliastoimport[]" value="<?=htmlspecialchars($alias['name'])?>" title="<?=$tooltip?>"/>
					  </td>
					<?php endif; ?>
					  <td>
					  	<?=$textss . htmlspecialchars($alias['name']) . $textse?>
					  </td>
					  <td>
						  <?php
						$tmpaddr = explode(" ", $alias['address']);
						$addresses = implode(", ", array_slice($tmpaddr, 0, 10));
						echo "{$textss}{$addresses}{$textse}";
						if(count($tmpaddr) > 10) {
							echo "...";
						}
						?>
					  </td>
					  <td>
						<?=$textss . htmlspecialchars($alias['descr']) . $textse?>
					  </td>
					</tr>
				  <?php $i++; endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<td colspan="4">
							<div class="infoblock blockopen">
							<?=print_info_box("<b>" . gettext("Note: ") . "</b>" . gettext("Fully-Qualified Domain Name (FQDN) host Aliases cannot be used as " . 
							"Suricata configuration parameters. Aliases resolving to a single FQDN value are disabled in the list above.  " . 
							"In the case of nested Aliases where one or more of the nested values is a FQDN host, the FQDN host will not " . 
							"be included in the {$title} configuration."), "info", false);?>
							</div>
						</td>
					</tr>
				</tfoot>
			</table>
		</div>

		<div class="content table-responsive">
			<?php if (!$selectablealias): ?>
				<div class="text-center">
					<b><?=gettext("There are currently no defined Aliases eligible for import.")?></b>
				</div>
				<div class="text-center">
					<button type="Submit" name="cancel_import_alias" value="Cancel" id="cancel_import_alias" class="btn btn-warning" title="<?=gettext("Cancel import operation and return")?>">
						<?=gettext("Cancel"); ?>
					</button>
				</div>
			<?php else: ?>
				<div class="text-left">
					<button type="Submit" name="save_import_alias" value="Save" id="save_import_alias" class="btn btn-primary" title="<?=gettext("Import selected item and return")?>">
						<i class="fa fa-save icon-embed-btn"></i>
						<?=gettext("Save"); ?>
					</button>
					<button type="Submit" name="cancel_import_alias" value="Cancel" id="cancel_import_alias" class="btn btn-warning" title="<?=gettext("Cancel import operation and return")?>">
						<?=gettext("Cancel"); ?>
					</button>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>


