<?php
/* $Id$ */
/*
    autoconfigbackup_stats.php
    Copyright (C) 2008 Scott Ullrich
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

require("globals.inc");
require("guiconfig.inc");
require("autoconfigbackup.inc");

$pf_version=substr(trim(file_get_contents("/etc/version")),0,3);
if ($pf_version < 2.0)
	require("crypt_acb.php");

// Seperator used during client / server communications
$oper_sep			= "\|\|";

// Encryption password 
$decrypt_password 	= $config['installedpackages']['autoconfigbackup']['config'][0]['crypto_password'];

// Defined username
$username			= $config['installedpackages']['autoconfigbackup']['config'][0]['username'];

// Defined password
$password			= $config['installedpackages']['autoconfigbackup']['config'][0]['password'];

// URL to restore.php
$get_url			= "https://portal.pfsense.org/pfSconfigbackups/restore.php";

// URL to delete.php
$del_url			= "https://portal.pfsense.org/pfSconfigbackups/delete.php";

// URL to stats.php
$stats_url			= "https://portal.pfsense.org/pfSconfigbackups/showstats.php";

// Set hostname
$hostname			= $config['system']['hostname'] . "." . $config['system']['domain'];

if(!$username) {
	Header("Location: /pkg_edit.php?xml=autoconfigbackup.xml&id=0&savemsg=Please+setup+Auto+Config+Backup");
	exit;
}

if($_REQUEST['delhostname']) {
	$curl_session = curl_init();
	curl_setopt($curl_session, CURLOPT_URL, $del_url);
	curl_setopt($curl_session, CURLOPT_HTTPHEADER, array("Authorization: Basic " . base64_encode("{$username}:{$password}")));
	curl_setopt($curl_session, CURLOPT_POST, 2);				
	curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, 0);	
	curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, 1);	
	curl_setopt($curl_session, CURLOPT_POSTFIELDS, "action=deletehostname&delhostname=" . urlencode($_REQUEST['delhostname']));
	curl_setopt($curl_session, CURLOPT_USERAGENT, $g['product_name'] . '/' . rtrim(file_get_contents("/etc/version")));
	// Proxy
	curl_setopt_array($curl_session, configure_proxy());
	
	$data = curl_exec($curl_session);
	if (curl_errno($curl_session)) {
		$fd = fopen("/tmp/acb_deletedebug.txt", "w");
		fwrite($fd, $get_url . "" . "action=deletehostname&hostname=" . 
			urlencode($_REQUEST['delhostname']) . "\n\n");
		fwrite($fd, $data);
		fwrite($fd, curl_error($curl_session));
		fclose($fd);
		$savemsg = "An error occurred while trying to remove the item from portal.pfsense.org.";
	} else {
	    curl_close($curl_session);
		$savemsg = "ALL backup revisions for {$_REQUEST['delhostname']} have been removed.";
	}
}

$pgtitle = "Diagnostics: Auto Configuration Stats";

include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<script src="/javascript/scriptaculous/prototype.js" type="text/javascript"></script>
<div id='maincontent'>
<?php
	include("fbegin.inc"); 
	if ($pf_version < 2.0)
		echo "<p class=\"pgtitle\">{$pgtitle}</p>";
	if($savemsg) {
		print_info_box($savemsg);
	}	
	if ($input_errors)
		print_input_errors($input_errors);

?>
<form method="post" action="autoconfigbackup_stats.php">
<table width="100%" border="0" cellpadding="0" cellspacing="0">  
	<tr>
		<td>
			<div id='feedbackdiv'>
			</div>
			<?php
				$tab_array = array();
				$tab_array[] = array("Settings", false, "/pkg_edit.php?xml=autoconfigbackup.xml&amp;id=0");
				$tab_array[] = array("Restore", false, "/autoconfigbackup.php");
				$tab_array[] = array("Backup now", false, "/autoconfigbackup_backup.php");
				$tab_array[] = array("Stats", true, "/autoconfigbackup_stats.php");
				display_top_tabs($tab_array);
			?>			
  		</td>
	</tr>
  <tr>
    <td>
	<table id="backuptable" class="tabcont" align="center" width="100%" border="0" cellpadding="6" cellspacing="0">
	<tr>
		<td colspan="2" align="left">
			<div id="loading">
				<img src="themes/metallic/images/misc/loader.gif"> Loading, please wait...
			</div>
	</tr>
	<tr>
		<td width="30%" class="listhdrr">Hostname</td>
		<td width="70%" class="listhdrr">Backup count</td>
	</tr>			
<?php 
	// Populate available backups
	$curl_session = curl_init();
	curl_setopt($curl_session, CURLOPT_URL, $stats_url);  
	curl_setopt($curl_session, CURLOPT_HTTPHEADER, array("Authorization: Basic " . base64_encode("{$username}:{$password}")));
	curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, 0);	
	curl_setopt($curl_session, CURLOPT_POST, 1);
	curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl_session, CURLOPT_POSTFIELDS, "action=showstats");
	curl_setopt($curl_session, CURLOPT_USERAGENT, $g['product_name'] . '/' . rtrim(file_get_contents("/etc/version")));
        // Proxy
        curl_setopt_array($curl_session, configure_proxy());

	$data = curl_exec($curl_session);
	if (curl_errno($curl_session)) {
		$fd = fopen("/tmp/acb_statsdebug.txt", "w");
		fwrite($fd, $get_url . "" . "action=showstats" . "\n\n");
		fwrite($fd, $data);
		fwrite($fd, curl_error($curl_session));
		fclose($fd);
	} else {
	    curl_close($curl_session);
	}
	// Loop through and create new confvers
	$data_split = split("\n", $data);
	$statvers = array();
	foreach($data_split as $ds) {
		$ds_split = split($oper_sep, $ds);
		$tmp_array = array();
		$tmp_array['hostname'] = $ds_split[0];
		$tmp_array['hostnamecount'] = $ds_split[1];
		if($ds_split[0] && $ds_split[1])
			$statvers[] = $tmp_array;
	}
	$counter = 0;
	echo "<script type=\"text/javascript\">";
	echo "$('loading').innerHTML = '';";
	echo "</script>";
	$total_backups = 0;
	foreach($statvers as $cv): 
?>
		<tr valign="top">
			<td class="listlr">
				<?= $cv['hostname']; ?>
			</td>
			<td class="listbg"> 
				<?= $cv['hostnamecount']; ?>
			</td>
			<td>
			<nobr>
			  <a title="View all backups for this host" href="autoconfigbackup.php?hostname=<?=urlencode($cv['hostname'])?>">
				<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0">
			  </a>
			  <a title="Delete all backups for this host" onClick="return confirm('Are you sure you want to delete *ALL BACKUPS FOR THIS HOSTNAME* <?= $cv['hostname']; ?>?')" href="autoconfigbackup_stats.php?delhostname=<?=urlencode($cv['hostname'])?>">
				<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0">
			  </a>
			</nobr>
			</td>
		</tr>
<?php
		$total_backups = $total_backups + $cv['hostnamecount'];
		$counter++; 
	endforeach;
	if($counter == 0)
		echo "<tr><td colspan='3'><center>Sorry, we could not load the status information for the account ($username).</td></tr>";
?>
	<tr>
		<td align="right">
			Total
		</td>
		<td>
			<?=$total_backups?>
		</td>
	</tr>
	</td>
  </tr>
</table>
</td></tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
