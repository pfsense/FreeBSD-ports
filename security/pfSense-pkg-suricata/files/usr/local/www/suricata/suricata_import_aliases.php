<?php
/*
 * suricata_import_aliases.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper
 * Copyright (c) 2005 Bill Marquette
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2023 Bill Meeks
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
					  	<i class="fa-solid fa-times text-danger"></i>
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
						<i class="fa-solid fa-save icon-embed-btn"></i>
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


