<?php
/*
	apcupsd_status.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2013-2016 Danilo G. Baio <dbaio@bsd.com.br>
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
require_once("/usr/local/pkg/apcupsd.inc");

$pgtitle = array(gettext("Package"), gettext("Services: Apcupsd"), gettext("Status"));
include("head.inc");

function puts($arg) {
	echo "$arg\n";
}

$tab_array = array();
$tab_array[] = array(gettext("General"), false, "/pkg_edit.php?xml=apcupsd.xml&amp;id=0");
$tab_array[] = array(gettext("Status"), true, "/apcupsd_status.php");
display_top_tabs($tab_array);

$nis_server = check_nis_running_apcupsd();

if ( $_POST['strapcaccess'] ) {
	puts("<div class=\"panel panel-success responsive\"><div class=\"panel-heading\"><h2 class=\"panel-title\">Status information from apcupsd</h2></div>");
	puts("<pre>");
	puts("Running: apcaccess -h {$_POST['strapcaccess']} <br />");
	putenv("PATH=/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin");
	$ph = popen("apcaccess -h {$_POST['strapcaccess']} 2>&1", "r" );
	while ($line = fgets($ph)) {
		echo htmlspecialchars($line);
	}
	pclose($ph);
	puts("</pre>");
	puts("</div>");
} elseif ($nis_server) {
	$nisip = (check_nis_ip_apcupsd() != ''? check_nis_ip_apcupsd() : "localhost");
	$nisport = (check_nis_port_apcupsd() != '' ? check_nis_port_apcupsd() : "3551");

	puts("<div class=\"panel panel-success responsive\"><div class=\"panel-heading\"><h2 class=\"panel-title\">Status information from apcupsd</h2></div>");
	puts("<pre>");
	puts("Running: apcaccess -h {$nisip}:{$nisport} <br />");
	putenv("PATH=/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin");
	$ph = popen("apcaccess -h {$nisip}:{$nisport} 2>&1", "r" );
	while ($line = fgets($ph)) {
		echo htmlspecialchars($line);
	}
	pclose($ph);
	puts("</pre>");
	puts("</div>");
} else {
	puts("<div class=\"panel panel-success responsive\"><div class=\"panel-heading\"><h2 class=\"panel-title\">Status information from apcupsd</h2></div>");
	puts("<pre>");
	puts("Network Information Server (NIS) not running, in order to run apcaccess on localhost, you need to enable it on APCupsd General settings. <br />");
	puts("</pre>");
	puts("</div>");
}

?>

<form action="apcupsd_status.php" method="post" enctype="multipart/form-data" name="frm_apcupsd_status">
	<div class="panel panel-default responsive">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('APC UPS Daemon Status Information')?></h2></div>
		<div class="panel-body">
			<div class="form-group">
				<label class="col-sm-2 control-label">
					Host
				</label>
				<div class="col-sm-10">
						<input class="form-control" name="strapcaccess" id="strapcaccess" type="text" 
							value="<?=htmlspecialchars($_POST['strapcaccess'])?>">
					<span class="help-block">
						Default: <strong>localhost</strong><br /><br />
						Note: apcaccess uses apcupsd's inbuilt Network Information Server (NIS) to obtain the current status information<br />
						from the UPS on the local or remote computer. It is therefore necessary to have the following configuration directives: <br />
						NETSERVER <strong>on</strong> <br />
						NISPORT <strong>3551</strong> <br />
						<br />
					</span>
				</div>
			</div>
		</div>
	</div>
	<div class="col-sm-10 col-sm-offset-2">
		<button name="submit" type="submit" class="btn btn-warning btn-sm" 
			value="EXECAPCACCESS" title="<?=gettext("Retrieve status information from apcupsd")?>">
			<i class="fa fa-bolt"></i>
			<?=gettext("Execute")?>
		</button>
	</div>
</form>

<?php
include("foot.inc");
?>

