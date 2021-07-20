<?php
/*
	sarg_reports.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2012 Marcello Coutinho <marcellocoutinho@gmail.com>
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
require("guiconfig.inc");

if ($savemsg) {
    print_info_box($savemsg);
}

if ($uname['machine'] == 'amd64') {
        ini_set('memory_limit', '512M');
}


$pgtitle = array(gettext("Package"), gettext("Sarg"), gettext("Reports"));
$shortcut_section = "sarg";
include("head.inc");

if (file_exists("/usr/local/www/sarg_ng.php")) {
    $sarg_frame = "sarg_ng.php";
    $wd = "106%";
    $ht = "7640";
} else {
    $sarg_frame = "sarg_frame.php";
    $wd = "100%";
    $ht = "600";
}

if ($_REQUEST['dir'] != "") {
    $sarg_frame .= "?dir=" . preg_replace("/\W/", "", $_REQUEST['dir']) . "&";
} else {
    $sarg_frame .= "?";
}
    
?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<form>
<div id="mainlevel">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr><td>
		<?php
		$tab_array = array();
		$tab_array[] = array(gettext("General"), false, "/pkg_edit.php?xml=sarg.xml&id=0");
		$tab_array[] = array(gettext("Users"), false, "/pkg_edit.php?xml=sarg_users.xml&id=0");
		$tab_array[] = array(gettext("Schedule"), false, "/pkg.php?xml=sarg_schedule.xml");
		$tab_array[] = array(gettext("View Report"), true, "/sarg_reports.php");
		$tab_array[] = array(gettext("XMLRPC Sync"), false, "/pkg_edit.php?xml=sarg_sync.xml&id=0");
		display_top_tabs($tab_array);
		$rdirs = array();
		mwexec('/bin/rm -f /usr/local/www/sarg-images/temp/*');
		if (is_array($config['installedpackages']['sargschedule']['config'])) {
		    $scs = $config['installedpackages']['sargschedule']['config'];
		    foreach ($scs as $sc) {
		        if ($sc['foldersuffix'] == "") {
		            $rdirs['default'] = "";
		        } else {
		            $rdirs[$sc['foldersuffix']] = "?dir={$sc['foldersuffix']}";
		        }
		    }
		}
		foreach ($rdirs as $rdir_i => $rdir_d ) {
		    $m_sel = false;
		    if (preg_match("/dir=$rdir_i/",$_SERVER['REQUEST_URI'])) {
		      $m_sel = true;
		    }
		    if ($rdir_i == "default"  && ! preg_match ("/dir/", $_SERVER['REQUEST_URI'])) {
		        $m_sel = true;
		    }
		    $tab_array2[] = array(gettext("$rdir_i Reports"), $m_sel, "/sarg_reports.php$rdir_d");
		    
		}
		if (count ($rdirs) > 1 || !array_key_exists("default",$rdirs)) {
		  display_top_tabs($tab_array2);
		}
		?>
	</td></tr>
	<tr><td>
		<div id="mainarea">
		</div>
		<br />
		<script type="text/javascript">
		//<![CDATA[
		var axel = Math.random() + "";
		var num = axel * 1000000000000000000;
		document.writeln('<iframe src="/<?=$sarg_frame ?>prevent='+ num +'?"  frameborder="0" width="<?=$wd ?>" height="<?=$ht ?>"></iframe>');
		//]]>
		</script>
		<div id="file_div"></div>
	</td></tr>
</table>
</div>
</form>
</body>
</html>
