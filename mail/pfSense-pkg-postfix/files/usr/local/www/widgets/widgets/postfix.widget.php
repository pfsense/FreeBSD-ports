<?php
/*
  		postfix.widget.php
	    Copyright 2011-2014 Marcello Coutinho
        Part of pfSense widgets (www.pfsense.org)

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
@require_once("guiconfig.inc");
@require_once("pfsense-utils.inc");
@require_once("functions.inc");

$uname=posix_uname();
if ($uname['machine']=='amd64')
        ini_set('memory_limit', '250M');

function open_table(){
	echo "<table style=\"padding-top:0px; padding-bottom:0px; padding-left:0px; padding-right:0px\" width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">";
	echo"  <tr>";
}
function close_table(){
	echo"  </tr>";
	echo"</table>";

}

$pfb_table=array();
$img['Sick']="<img src ='/themes/{$g['theme']}/images/icons/icon_interface_down.gif'>";
$img['Healthy']="<img src ='/themes/{$g['theme']}/images/icons/icon_interface_up.gif'>";


#var_dump($pfb_table);
#exit;
?><div id='postfix'><?php
global $config;


$size=$config['installedpackages']['postfix']['config'][0]['widget_size'];
if (preg_match('/\d+/',$config['installedpackages']['postfix']['config'][0]['widget_days']))
	$days=$config['installedpackages']['postfix']['config'][0]['widget_days'] * -1;
else
	$days=-3;
if (preg_match('/\d+/',$config['installedpackages']['postfix']['config'][0]['widget_size']))
	$size=$config['installedpackages']['postfix']['config'][0]['widget_size'];
else
	$size='100000000';#100mb

$postfix_dir="/var/db/postfix/";
$curr_time = time();
for ($z = 0; $z > $days; $z--){

if ($z==0)
	$postfix_db=date("Y-m-d");
else
	$postfix_db=date("Y-m-d",strtotime("$z day",$curr_time));

if (file_exists($postfix_dir.'/'.$postfix_db.".db")){
	#noqueue
	open_table();
	print "<td class=\"vncellt\"><strong><center>$postfix_db</center></strong></td>";
	close_table();
	open_table();
	if (@filesize($postfix_dir.'/'.$postfix_db.".db")< $size){
		$dbhandle = sqlite_open($postfix_dir.'/'.$postfix_db.".db", 0666, $error);
		$stm="select count(*) as total from mail_noqueue";
		$result = sqlite_query($dbhandle, $stm);
		$row_noqueue = sqlite_fetch_array($result, SQLITE_ASSOC);

		#queue
		$result = sqlite_query($dbhandle, $stm);
		$stm="select mail_status.info as status,count(*) as total from mail_to,mail_status where mail_to.status=mail_status.id group by status order by mail_status.info";
		$result = sqlite_query($dbhandle, $stm);
		$reader="";
		$count="";
		for ($i = 1; $i <= 15; $i++) {
					$row = sqlite_fetch_array($result, SQLITE_ASSOC);
					 if (is_array($row)){
					 	if (preg_match("/\w+/",$row['status'])){
					 	$reader.="<td class=\"listlr\"width=50%><strong>".ucfirst($row['status'])."</strong></td>\n";
					 	if ($row['status']=="reject")
					 		$row['total']=+$row_noqueue['total'];
						$count.="<td class=\"listlr\">".$row['total']."</td>\n";
					 	}
					 }
					}
		print "<tr>".$reader."</tr>";
		print "<tr>".$count."</tr>";
		$result = sqlite_query($dbhandle, $stm);
		sqlite_close($dbhandle);
		}
	else{
		print "<td class=\"listlr\"width=100%><center>File size is too large.</center></td>";
		}
	close_table();
	echo "<br>";

}
}
echo"  </tr>";
echo"</table></div>";

?>
<script type="text/javascript">
	function getstatus_postfix() {
		var url = "/widgets/widgets/postfix.widget.php";
		var pars = 'getupdatestatus=yes';
		var myAjax = new Ajax.Request(
			url,
			{
				method: 'get',
				parameters: pars,
				onComplete: activitycallback_postfix
			});
		}
	function activitycallback_postfix(transport) {
		$('postfix').innerHTML = transport.responseText;
		setTimeout('getstatus_postfix()', 60000);
	}
	getstatus_postfix();
</script>
