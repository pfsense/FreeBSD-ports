<?php
/*
	postfix_view_config.php
	part of pfSense (https://www.pfsense.org/)
	Copyright (C) 2011-2015 Marcello Coutinho <marcellocoutinho@gmail.com>
	based on varnish_view_config.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code MUST retain the above copyright notice,
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
$shortcut_section = "postfix";
require("guiconfig.inc");

$uname=posix_uname();
if ($uname['machine']=='amd64')
        ini_set('memory_limit', '250M');

$pfs_version = substr(trim(file_get_contents("/etc/version")),0,3);
if ($pfs_version == "2.1" || $pfs_version == "2.2") {
	define('POSTFIX_LOCALBASE', '/usr/pbi/postfix-' . php_uname("m"));
} else {
	define('POSTFIX_LOCALBASE','/usr/local');
}
function get_cmd(){
	if ($_REQUEST['cmd'] =='mailq'){
		#exec("/usr/local/bin/mailq" . escapeshellarg('^'.$m.$j." ".$hour.".*".$grep)." /var/log/maillog", $lists);
		exec(POSTFIX_LOCALBASE."/bin/mailq", $mailq);
		print '<table class="tabcont" width="100%" border="0" cellpadding="8" cellspacing="0">';
		print '<tr><td colspan="6" valign="top" class="listtopic">'.gettext($_REQUEST['cmd']." Results").'</td></tr>';
		print '<tr><td class="listlr"><strong>SID</strong></td>';
		print '<td class="listlr"><strong>size</strong></td>';
		print '<td class="listlr"><strong>date</strong></td>';
		print '<td class="listlr"><strong>sender</strong></td>';
		print '<td class="listlr"><strong>info</strong></td>';
		print '<td class="listlr"><strong>Recipient </strong></td></tr>';
		#print '<table class="tabcont" width="100%" border="0" cellpadding="8" cellspacing="0">';
		$td='<td valign="top" class="listlr">';
		$sid="";
		foreach ($mailq as $line){
			if(preg_match("/-Queue ID- --Size--/",$line,$matches))
				print"";
			elseif (preg_match("/(\w+)\s+(\d+)\s+(\w+\s+\w+\s+\d+\s+\d+:\d+:\d+)\s+(.*)/",$line,$matches)){
				print '<tr>'.$td.$matches[1].'</td>'.$td.$matches[2].'</td>'.$td.$matches[3].'</td>'.$td.$matches[4];
				$sid=$matches[1];
				}
			elseif (preg_match("/(\s+|)(\W\w+.*)/",$line,$matches) && $sid !="")
				print $td.$matches[2].'</td>';
			elseif (preg_match("/\s+(\w+.*)/",$line,$matches) && $sid !=""){
				print $td.$matches[1].'</td></tr>';
				$sid="";
			}
		}
		print '</table>';
	}
	if ($_REQUEST['cmd'] =='qshape'){
		if ($_REQUEST['qshape']!="")
			exec(POSTFIX_LOCALBASE."/bin/qshape -".preg_replace("/\W/","",$_REQUEST['type'])." ". preg_replace("/\W/","",$_REQUEST['qshape']), $qshape);
		else
			exec(POSTFIX_LOCALBASE."/bin/qshape", $qshape);
		print '<table class="tabcont" width="100%" border="0" cellpadding="8" cellspacing="0">';
		print '<tr><td colspan="12" valign="top" class="listtopic">'.gettext($_REQUEST['cmd']." Results").'</td></tr>';
		$td='<td valign="top" class="listlr">';
		$sid="";
		foreach ($qshape as $line){
			if (preg_match("/\s+(T\s.*)/",$line,$matches)){
				print '<tr><td class="listlr"></td>';
				foreach (explode (" ",preg_replace("/\s+/"," ",$matches[1])) as $count)
					print '<td class="listlr"><strong>'.$count.'</strong></td>';
				print "</tr>";
			}
			else{
				print "<tr>";
				$line=preg_replace("/^\s+/","",$line);
				$line=preg_replace("/\s+/"," ",$line);
				foreach (explode (" ",$line) as $count)
					print '<td class="listlr"><strong>'.$count.'</strong></td>';
				print "</tr>";
			}

		}
	}
}

if ($_REQUEST['cmd']!=""){
	get_cmd();
	}
else{
	$pf_version=substr(trim(file_get_contents("/etc/version")),0,3);
	if ($pf_version < 2.0)
		$one_two = true;

	$pgtitle = "Status: Postfix Mail Queue";
	include("head.inc");

	?>
	<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
	<?php include("fbegin.inc"); ?>

	<?php if($one_two): ?>
	<p class="pgtitle"><?=$pgtitle?></font></p>
	<?php endif; ?>

	<?php if ($savemsg) print_info_box($savemsg); ?>

	<form action="postfix_view_config.php" method="post">

	<div id="mainlevel">
		<table width="100%" border="0" cellpadding="0" cellspacing="0">
			<tr><td>
	<?php
		$tab_array = array();
		$tab_array[] = array(gettext("General"), false, "/pkg_edit.php?xml=postfix.xml&id=0");
		$tab_array[] = array(gettext("Domains"), false, "/pkg_edit.php?xml=postfix_domains.xml&id=0");
		$tab_array[] = array(gettext("Recipients"), false, "/pkg_edit.php?xml=postfix_recipients.xml&id=0");
		$tab_array[] = array(gettext("Access Lists"), false, "/pkg_edit.php?xml=postfix_acl.xml&id=0");
		$tab_array[] = array(gettext("Antispam"), false, "/pkg_edit.php?xml=postfix_antispam.xml&id=0");
		$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=postfix_sync.xml&id=0");
		$tab_array[] = array(gettext("View config"), false, "/postfix_view_config.php");
		$tab_array[] = array(gettext("Search mail"), false, "/postfix_search.php");
		$tab_array[] = array(gettext("Queue"), true, "/postfix_queue.php");
		$tab_array[] = array(gettext("About"), false, "/postfix_about.php");
		display_top_tabs($tab_array);
	?>
			</td></tr>
	 		<tr>
	    		<td>
					<div id="mainarea">
						<table class="tabcont" width="100%" border="0" cellpadding="8" cellspacing="0">
						<tr><td></td></tr>
						<tr>
						<td colspan="2" valign="top" class="listtopic"><?=gettext("Postfix Queue"); ?></td></tr>
					<tr>
                        <td width="22%" valign="top" class="vncell"><?=gettext("queue command: ");?></td>
                        <td width="78%" class="vtable">
                        <select name="drop3" id="cmd">
                        	<option value="mailq" selected="selected">mailq</option>
                        	<option value="qshape" selected>qshape</option>
						</select><br><?=gettext("Select queue command to run.");?></td>
					</tr>
					<tr>
                        <td width="22%" valign="top" class="vncell"><?=gettext("update frequency: ");?></td>
                        <td width="78%" class="vtable">
                        <select name="drop3" id="updatef">
                        	<option value="5" selected="selected">05 seconds</option>
                        	<option value="15">15 Seconds</option>
							<option value="30">30 Seconds</option>
							<option value="60">One minute</option>
							<option value="1" selected>Never</option>
						</select><br><?=gettext("Select how often queue cmd will run.");?></td>
					</tr>
					<tr>
                        <td width="22%" valign="top" class="vncell"><?=gettext("qshape Report flags: ");?></td>
                        <td width="78%" class="vtable">
                        <select name="drop3" id="qshape" multiple="multiple" size="5">
                        	<option value="hold">hold</option>
							<option value="incoming">incoming</option>
							<option value="active">active</option>
							<option value="deferred">deferred</option>
							<option value="maildrop">maildrop</option>
						</select><br><?=gettext("Select how often queue will be queried.");?></td>
					</tr>
					<tr>
                        <td width="22%" valign="top" class="vncell"><?=gettext("qshape Report type: ");?></td>
                        <td width="78%" class="vtable">
                        <select name="drop3" id="qtype">
							<option value="s" selected>sender domain</option>
							<option value="p">parent domain</option>
						</select><br><?=gettext("Select between sender or parent domains to order by.");?></td>
					</tr>

					<tr>
							<td width="22%" valign="top"></td>
                        <td width="78%"><input name="Submit" type="button" class="formbtn" id="run" value="<?=gettext("show queue");?>" onclick="get_queue('mailq')"><div id="search_help"></div></td>
				</table>
				</div>
				</td>
			</tr>
			</table>
			<br>
				<div>
				<table class="tabcont" width="100%" border="0" cellpadding="8" cellspacing="0">
								<tr>
	     						<td class="tabcont" >
	     						<div id="file_div"></div>

								</td>
							</tr>
						</table>
					</div>
	</div>
	</form>
	<script type="text/javascript">
	function loopSelected(id)
	{
	  var selectedArray = new Array();
	  var selObj = document.getElementById(id);
	  var i;
	  var count = 0;
	  for (i=0; i<selObj.options.length; i++) {
	    if (selObj.options[i].selected) {
	      selectedArray[count] = selObj.options[i].value;
	      count++;
	    }
	  }
	  return(selectedArray);
	}

	function get_queue(loop) {
			//prevent multiple instances
			if ($('run').value=="show queue" || loop== 'running'){
				$('run').value="running...";
				$('search_help').innerHTML ="<br><strong>You can change options while running.<br>To Stop search, change update frequency to Never.</strong>";
				var q_args=loopSelected('qshape');
				var pars = 'cmd='+$('cmd').options[$('cmd').selectedIndex].value;
				var pars = pars + '&qshape='+q_args;
				var pars = pars + '&type='+$('qtype').options[$('qtype').selectedIndex].value;
				var url = "/postfix_queue.php";
				var myAjax = new Ajax.Request(
					url,
					{
						method: 'post',
						parameters: pars,
						onComplete: activitycallback_queue_file
					});
				}
			}
		function activitycallback_queue_file(transport) {
			$('file_div').innerHTML = transport.responseText;
			var update=$('updatef').options[$('updatef').selectedIndex].value * 1000;
			if (update > 1000){
				setTimeout('get_queue("running")', update);
				}
			else{
				$('run').value="show queue";
				$('search_help').innerHTML ="";
			}
		}
	</script>
	<?php
	include("fend.inc");
	}
	?>
	</body>
	</html>
