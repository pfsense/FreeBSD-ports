<?php
/*
 * e2guardian.widget.php
 *
 * part of Unofficial packages for pfSense(R) softwate
 * Copyright (c) 2017 Marcello Coutinho
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


require_once("functions.inc");
require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("pkg-utils.inc");
require_once("service-utils.inc");

function e2g_open_table($thead=""){
	echo "<table border=1 class='table table-striped table-hover table-condensed'>\n";
	echo "<thead><tr>".$thead."</tr></thread>";
        echo "<tbody>\n";
}

function e2g_open_table_header(){
	global $dbc;
	//$h="<th style='text-align:center;'>Date</th>"; //print"<tr>";
        foreach ($dbc as $c){
        	$h .= "<th style='text-align:center;'>".ucfirst($c)."</th>";
	}
	e2g_open_table($h);
}

function e2g_close_table(){
	echo"</tr>\n</tbody>";
	echo"</table>";
}

function e2guardian_show_dstats($count = 5) {
	exec("/usr/bin/tail -{$count} /var/log/e2guardian/dstats.log",$dstats);
	for ($d = ($count -1); $d >= 0; $d--) {
		print "<tr>\n";
		$dstat = preg_replace("/\s+/"," ",$dstats[$d]);
		$fields = explode(" ",$dstat);
		//$fields[0] = date('r', $fields[0]);
		print "<th style='text-align:right;'><a>" . date('H:i',$fields[0]) . "</a></th>\n";
		for ($i = 2; $i < 11; $i++) {
			print "<th style='text-align:right;'><a>" . number_format($fields[$i],0,"",".") . "</a></th>\n";
		}
		print "</tr>\n";
	}
}


$pfb_table=array();

?><div id='e2guardian'><?php
global $config;


$size = $config['installedpackages']['e2guardian']['config'][0]['widget_count'];

$dbc = array('time','busy','httpwQ','logQ','conx','conx/s','reqs','reqs/s','maxfd','LCcnt');
$curr_time = time();
e2g_open_table_header();
e2guardian_show_dstats();
e2g_close_table();
echo"  </tr>";
echo"</table></div>";

?>
<script src="/vendor/jquery/jquery-3.5.1.min.js" type="text/javascript"></script>
<script type="text/javascript">
   function getstatus_e2guardian() {
	var url = "/widgets/widgets/e2guardian.widget.php";
	jQuery.ajax(url,
		{
		type: 'post',
		data: {
			getupdatestatus:  'yes'
		},
		success: function(ret){
			$('#e2guardian').html(ret);
		}
	});
    }

	$(document).ready(function() {
		setTimeout(getstatus_e2guardian,20000);
	});

</script>
