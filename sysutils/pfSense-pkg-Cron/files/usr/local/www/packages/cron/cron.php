<?php
/*
 * cron.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2008 Mark J Crane
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
require_once("/usr/local/pkg/cron.inc");

$a_cron = &$config['cron']['item'];

if ($_GET['act'] == "del") {
	if ($_GET['type'] == 'php') {
		if ($a_cron[$_GET['id']]) {
			unset($a_cron[$_GET['id']]);
			write_config(gettext("Crontab item deleted via cron package"));
			header("Location: cron.php");
			exit;
		}
	}
}

$pgtitle = array(gettext("Services"), gettext("Cron"), gettext("Settings"));
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Settings"), true, "/packages/cron/cron.php");
$tab_array[] = array(gettext("Add"), false, "/packages/cron/cron_edit.php");
display_top_tabs($tab_array);
?>



<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title">Cron Schedules</h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<form action="cron.php" method="post" name="iform" id="iform">
			<?php
			if ($config_change == 1) {
				write_config(gettext("Crontab edited via cron package"));
				$config_change = 0;
			}
			?>

			<table class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th width="5%">minute</th>
						<th width="5%">hour</th>
						<th width="5%">mday</th>
						<th width="5%">month</th>
						<th width="5%">wday</th>
						<th width="5%">who</th>
						<th width="60%">command</th>
						<th width="10%">
							<a class="btn btn-small btn-success" href="cron_edit.php"><i class="fa-solid fa-plus" alt="edit"></i> Add</a>
						</th>
					</tr>
				</thead>
				<tbody>

	<?php
		$i = 0;
		if (count($a_cron) > 0) {
			foreach ($a_cron as $ent) {
	?>
					<tr>
						<td><?= htmlspecialchars($ent['minute']) ?></td>
						<td><?= htmlspecialchars($ent['hour']) ?></td>
						<td><?= htmlspecialchars($ent['mday']) ?></td>
						<td><?= htmlspecialchars($ent['month']) ?></td>
						<td><?= htmlspecialchars($ent['wday']) ?></td>
						<td><?= htmlspecialchars($ent['who']) ?></td>
						<td><?= htmlspecialchars($ent['command']) ?></td>
						<td>
							<a href="cron_edit.php?id=<?=$i?>"><i class="fa-solid fa-pencil" alt="edit" title="<?=gettext('Edit this job')?>"></i></a>
							<a href="cron_edit.php?dup=<?=$i?>"><i class="fa-regular fa-clone" alt="copy" title="<?=gettext('Copy this job')?>"></i></a>
							<a href="cron_edit.php?type=php&amp;act=del&amp;id=<?=$i?>"><i class="fa-solid fa-trash-can" alt="delete" title="<?=gettext('Delete this job')?>"></i></a>
						</td>
					</tr>
	<?php
		$i++;
			}
		}
	?>
					<tr>
						<td colspan="7"></td>
						<td>
							<a class="btn btn-small btn-success" href="cron_edit.php"><i class="fa-solid fa-plus" alt="add"></i> Add</a>
						</td>
					</tr>
				</tbody>
			</table>
			</form>
		</div>
	</div>
</div>

<div class="infoblock">
	<?=print_info_box('For more information see: <a href="http://www.freebsd.org/doc/en/books/handbook/configtuning-cron.html">FreeBSD Handbook - Configuring cron(8)</a> and <a href="https://www.freebsd.org/cgi/man.cgi?query=crontab&amp;sektion=5">crontab(5) man page</a>.', 'info')?>
</div>

<?php include("foot.inc"); ?>
