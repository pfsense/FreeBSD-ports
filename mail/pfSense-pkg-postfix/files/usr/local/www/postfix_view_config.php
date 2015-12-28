<?php
/*
	postfix_view_config.php
	part of pfSense (https://www.pfsense.org/)
	Copyright (C) 2011-2014 Marcello Coutinho <marcellocoutinho@gmail.com>
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

$pfs_version = substr(trim(file_get_contents("/etc/version")),0,3);
define('POSTFIX_LOCALBASE','/usr/local');

function get_file($file){
	$files['main']=POSTFIX_LOCALBASE."/etc/postfix/main.cf";
	$files['master']=POSTFIX_LOCALBASE."/etc/postfix/master.cf";
	$files['recipients']=POSTFIX_LOCALBASE."/etc/postfix/relay_recipients";
	$files['header']=POSTFIX_LOCALBASE."/etc/postfix/header_check";
	$files['mime']=POSTFIX_LOCALBASE."/etc/postfix/mime_check";
	$files['body']=POSTFIX_LOCALBASE."/etc/postfix/body_check";
	$files['cidr']=POSTFIX_LOCALBASE."/etc/postfix/cal_cidr";
	$files['pcre']=POSTFIX_LOCALBASE."/etc/postfix/cal_pcre";

	if ($files[$file]!="" && file_exists($files[$file])){
		print '<textarea rows="50" cols="100%">';
		print $files[$file]."\n".file_get_contents($files[$file]);
		print '</textarea>';
	}
}

if ($_REQUEST['file']!=""){
	get_file($_REQUEST['file']);
	}
else{
	$pfSversion = str_replace("\n", "", file_get_contents("/etc/version"));
	if ($pf_version < 2.0)
		$one_two = true;

	$pgtitle = "Services: Postfix View Configuration";
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
		$tab_array[] = array(gettext("View config"), true, "/postfix_view_config.php");
		$tab_array[] = array(gettext("Search mail"), false, "/postfix_search.php");
		$tab_array[] = array(gettext("Queue"), false, "/postfix_queue.php");
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
						<td colspan="2" valign="top" class="listtopic"><?=gettext("View Postfix configuration files"); ?></td></tr>
						<tr><td></td></tr>
						</tr>
							<tr>
							<td class="tabcont" >
							<input type="button" onClick="get_postfix_file('main');" id='btn_main' value="main.cf">&nbsp;
							<input type="button" onClick="get_postfix_file('master');" id='btn_master' value="master.cf">&nbsp;
							<input type="button" onClick="get_postfix_file('recipients');" id='btn_recipients' value="relay_recipients">&nbsp;
							<input type="button" onClick="get_postfix_file('header');" id='btn_header' value="header_check">&nbsp;
							<input type="button" onClick="get_postfix_file('mime');" id='btn_mime' value="mime_check">&nbsp;
							<input type="button" onClick="get_postfix_file('body');" id='btn_body' value="body_check">&nbsp;
							<input type="button" onClick="get_postfix_file('cidr');" id='btn_cidr' value="client CIDR">&nbsp;
							<input type="button" onClick="get_postfix_file('pcre');" id='btn_pcre' value="client PCRE">&nbsp;
							</td>
								</tr>
								<tr>
	     						<td class="tabcont" >
	     						<div id="file_div"></div>

								</td>
							</tr>
						</table>
					</div>
				</td>
			</tr>
		</table>
	</div>
	</form>
	<script type="text/javascript">
	function get_postfix_file(file) {
			$('btn_'+file).value="reading...";
			var pars = 'file='+file;
			var url = "/postfix_view_config.php";
			var myAjax = new Ajax.Request(
				url,
				{
					method: 'post',
					parameters: pars,
					onComplete: activitycallback_postfix_file
				});
			}
		function activitycallback_postfix_file(transport) {
			$('file_div').innerHTML = transport.responseText;
			$('btn_main').value="main.cf";
			$('btn_master').value="master.cf";
			$('btn_recipients').value="relay_recipients";
			$('btn_header').value="header_check";
			$('btn_mime').value="mime_check";
			$('btn_body').value="body_check";
			$('btn_cidr').value="client CIDR";
			$('btn_pcre').value="client PCRE";
			scroll(0,0);
		}
	</script>
	<?php
	include("fend.inc");
	}
	?>
	</body>
	</html>
