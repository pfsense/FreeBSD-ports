<?php
/* $Id$ */
/* ========================================================================== */
/*
	teamspeak3_show_logs.php
	Copyright (C) 2016 Sander Peterse
	All rights reserved.
                                                                              */
/* ========================================================================== */
/*
	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1.	Redistributions of source code must retain the above copyright notice,
	this list of conditions and the following disclaimer.

	2.	Redistributions in binary form must reproduce the above copyright
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
/* ========================================================================== */

require_once("/usr/local/pkg/teamspeak3.inc");

if(isset($_GET['downloadlog']))
{
	teamspeak3_download_log_file($_GET['downloadlog']);
	exit;
}

require("guiconfig.inc");

$pgtitle = array(gettext("Package"), gettext("TeamSpeak 3"), gettext("Show Logs"));
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Settings"), false, "/pkg_edit.php?xml=teamspeak3.xml&amp;id=0");
$tab_array[] = array(gettext("Logs"), true, "/teamspeak3/teamspeak3_show_logs.php");
$tab_array[] = array(gettext("Backup &amp; Restore"), false, "/teamspeak3/teamspeak3_backup_restore.php");
display_top_tabs($tab_array)
?>

<p>Here you download the TeamSpeak 3 server log files. Global TeamSpeak 3 logging options can be changed using the ts3server.ini configuration file (<a href="/pkg_edit.php?xml=teamspeak3.xml&amp;id=0">Setting
 Tab</a>). Some virtual server options can also be changed by using the TeamSpeak 3 client. Right click on your server name (after connecting using the TeamSpeak 3 client), click on "Edit Virtual Server" and switch to the "Logs" tab.</p>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?= gettext('TeamSpeak 3 logs'); ?></h2>
	</div>
	<div class="panel-body">
		<div class="table table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
				<thead>
					<tr class="text-nowrap">
						<th style="width:100%">
							<?=gettext("Log file")?>
						</th>
						<th/>
					</tr>
				</thead>
				<tbody>
<?php
	$aLogFiles = teamspeak3_get_all_log_files();
	
	if(!empty($aLogFiles))
	{
	        foreach($aLogFiles as $aLogFile)
	        {
?>
					<tr>
						<td class="listr">
							<?= $aLogFile; ?>
						</td>
						<td class="listr">
							<button class="btn btn-success btn-sm" type="button" value="Download" name="download_log_file" 
									onclick="window.location.href='/teamspeak3/teamspeak3_show_logs.php?downloadlog=<?= $aLogFile; ?>'">
									<i class="fa fa-download icon-embed-btn"/> </i>
									Download
							</button>
						</td>	
					</tr>
<?php	                
	        }
	}
?>
				</tbody>
			</table>
<?php
	        if (count($aLogFiles) == 0) 
			{
				print_info_box(gettext('No logs to display.'));
	        }
?>
		</div>
	</div>
</div>

<?php
include("foot.inc");
?>
	