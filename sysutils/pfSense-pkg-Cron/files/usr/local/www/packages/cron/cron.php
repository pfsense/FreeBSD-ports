<?php
/*
	cron.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2008 Mark J Crane
	Copyright (C) 2015 ESF, LLC
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
require_once("guiconfig.inc");
require_once("/usr/local/pkg/cron.inc");

$a_cron = &$config['cron']['item'];

if ($_GET['act'] == "del") {
	if ($_GET['type'] == 'php') {
		if ($a_cron[$_GET['id']]) {
			unset($a_cron[$_GET['id']]);
			write_config();
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
				write_config();
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
							<a class="btn btn-small btn-success" href="cron_edit.php"><i class="fa fa-plus" alt="edit"></i> Add</a>
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
						<td><?=$ent['minute']?></td>
						<td><?=$ent['hour']?></td>
						<td><?=$ent['mday']?></td>
						<td><?=$ent['month']?></td>
						<td><?=$ent['wday']?></td>
						<td><?=$ent['who']?></td>
						<td><?=$ent['command']?></td>
						<td>
							<a href="cron_edit.php?id=<?=$i?>"><i class="fa fa-pencil" alt="edit"></i></a>
							<a href="cron_edit.php?type=php&amp;act=del&amp;id=<?=$i?>"><i class="fa fa-trash" alt="delete"></i></a>
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
							<a class="btn btn-small btn-success" href="cron_edit.php"><i class="fa fa-plus" alt="add"></i> Add</a>
						</td>
					</tr>
				</tbody>
			</table>
			</form>
		</div>
	</div>
</div>

<div class="infoblock">
	<?=print_info_box('For more information see: <a href="http://www.freebsd.org/doc/en/books/handbook/configtuning-cron.html">FreeBSD Handbook - Configuring cron(8)</a> and <a href="https://www.freebsd.org/cgi/man.cgi?query=crontab&amp;sektion=5">crontab(5) man page</a>.', info)?>
</div>

<?php include("foot.inc"); ?>
