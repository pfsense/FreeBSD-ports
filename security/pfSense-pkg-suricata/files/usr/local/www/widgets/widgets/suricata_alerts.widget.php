<?php
/*
    suricata_alerts.widget.php
    Copyright (C) 2009 Jim Pingle
    mod 24-07-2012

    Copyright (C) 2014 Bill Meeks
    mod 03-Mar-2014 adapted for use with Suricata by Bill Meeks

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INClUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

$nocsrf = true;

require_once("guiconfig.inc");
require_once("/usr/local/www/widgets/include/widget-suricata.inc");

global $config, $g;

/* Retrieve Suricata configuration */
if (!is_array($config['installedpackages']['suricata']['rule']))
	$config['installedpackages']['suricata']['rule'] = array();
$a_instance = &$config['installedpackages']['suricata']['rule'];

/* array sorting */
function suricata_sksort(&$array, $subkey="id", $sort_ascending=false) {
        /* an empty array causes suricata_sksort to fail - this test alleviates the error */
	if(empty($array))
	        return false;
	if (count($array)){
		$temp_array[key($array)] = array_shift($array);
	};
	foreach ($array as $key => $val){
		$offset = 0;
		$found = false;
		foreach ($temp_array as $tmp_key => $tmp_val) {
			if (!$found and strtolower($val[$subkey]) > strtolower($tmp_val[$subkey])) {
				$temp_array = array_merge((array)array_slice($temp_array,0,$offset), array($key => $val), array_slice($temp_array,$offset));
				$found = true;
			};
			$offset++;
		};
		if (!$found) $temp_array = array_merge($temp_array, array($key => $val));
	};

	if ($sort_ascending) {
		$array = array_reverse($temp_array);
	} else $array = $temp_array;
        /* below is the complement for empty array test */
        return true; 
};

/* check if suricata widget variable is set */
$suri_nentries = $config['widgets']['widget_suricata_display_lines'];
if (!isset($suri_nentries) || $suri_nentries < 0)
	$suri_nentries = 5;

// Called by Ajax to update alerts table contents
if (isset($_GET['getNewAlerts'])) {
	$response = "";
	$suri_alerts = suricata_widget_get_alerts();
	$counter = 0;
	foreach ($suri_alerts as $a) {
		$response .= $a['instanceid'] . " " . $a['dateonly'] . "||" . $a['timeonly'] . "||" . $a['src'] . "||";
		$response .= $a['dst'] . "||" . $a['msg'] . "\n";
		$counter++;
		if($counter >= $suri_nentries)
			break;
	}
	echo $response;
	return;
}

if(isset($_POST['widget_suricata_display_lines'])) {
	$config['widgets']['widget_suricata_display_lines'] = $_POST['widget_suricata_display_lines'];
	write_config("Saved Suricata Alerts Widget Displayed Lines Parameter via Dashboard");
	header("Location: ../../index.php");
}

// Read "$suri_nentries" worth of alerts from the top of the alerts.log file
function suricata_widget_get_alerts() {

	global $config, $a_instance, $suri_nentries;
	$suricata_alerts = array();

	/* read log file(s) */
	$counter=0;
	foreach ($a_instance as $instanceid => $instance) {
		$suricata_uuid = $a_instance[$instanceid]['uuid'];
		$if_real = get_real_interface($a_instance[$instanceid]['interface']);

		// make sure alert file exists, then grab the most recent {$suri_nentries} from it
		// and write them to a temp file.
		if (file_exists("/var/log/suricata/suricata_{$if_real}{$suricata_uuid}/alerts.log")) {
			exec("tail -{$suri_nentries} -r /var/log/suricata/suricata_{$if_real}{$suricata_uuid}/alerts.log > /tmp/surialerts_{$suricata_uuid}");
			if (file_exists("/tmp/surialerts_{$suricata_uuid}")) {

				/*************** FORMAT without CSV patch -- ALERT -- ***********************************************************************************/
				/* Line format: timestamp  action[**] [gid:sid:rev] msg [**] [Classification: class] [Priority: pri] {proto} src:srcport -> dst:dstport */
				/*              0          1           2   3   4    5                         6                 7     8      9   10         11  12      */
				/****************************************************************************************************************************************/

				/**************** FORMAT without CSV patch -- DECODER EVENT -- **************************************************************************/
				/* Line format: timestamp  action[**] [gid:sid:rev] msg [**] [Classification: class] [Priority: pri] [**] [Raw pkt: ...]                */
				/*              0          1           2   3   4    5                         6                 7                                       */
				/************** *************************************************************************************************************************/

				if (!$fd = fopen("/tmp/surialerts_{$suricata_uuid}", "r")) {
					log_error(gettext("[Suricata Widget] Failed to open file /tmp/surialerts_{$suricata_uuid}"));
					continue;
				}
				$buf = "";
				while (($buf = fgets($fd)) !== FALSE) {
					$fields = array();
					$tmp = array();

					// Parse alert log entry to find the parts we want to display
					$fields[0] = substr($buf, 0, strpos($buf, '  '));

					// The regular expression match below returns an array as follows:
					// [2] => GID, [3] => SID, [4] => REV, [5] => MSG, [6] => CLASSIFICATION, [7] = PRIORITY
					preg_match('/\[\*{2}\]\s\[((\d+):(\d+):(\d+))\]\s(.*)\[\*{2}\]\s\[Classification:\s(.*)\]\s\[Priority:\s(\d+)\]\s/', $buf, $tmp);
					$fields['gid'] = trim($tmp[2]);
					$fields['sid'] = trim($tmp[3]);
					$fields['rev'] = trim($tmp[4]);
					$fields['msg'] = trim($tmp[5]);
					$fields['class'] = trim($tmp[6]);
					$fields['priority'] = trim($tmp[7]);

					// The regular expression match below looks for the PROTO, SRC and DST fields
					// and returns an array as follows:
					// [1] = PROTO, [2] => SRC:SPORT [3] => DST:DPORT
					if (preg_match('/\{(.*)\}\s(.*)\s->\s(.*)/', $buf, $tmp)) {
						// Get SRC
						$fields['src'] = trim(substr($tmp[2], 0, strrpos($tmp[2], ':')));
						if (is_ipaddrv6($fields['src']))
							$fields['src'] = inet_ntop(inet_pton($fields['src']));

						// Get SPORT
						$fields['sport'] = trim(substr($tmp[2], strrpos($tmp[2], ':') + 1));

						// Get DST
						$fields['dst'] = trim(substr($tmp[3], 0, strrpos($tmp[3], ':')));
						if (is_ipaddrv6($fields['dst']))
							$fields['dst'] = inet_ntop(inet_pton($fields['dst']));

						// Get DPORT
						$fields['dport'] = trim(substr($tmp[3], strrpos($tmp[3], ':') + 1));
					}
					else {
						// If no PROTO and IP ADDR, then this is a DECODER EVENT
						$fields['src'] = gettext("Decoder Event");
						$fields['sport'] = "";
						$fields['dst'] = "";
						$fields['dport'] = "";
					}

					// Create a DateTime object from the event timestamp that
					// we can use to easily manipulate output formats.
					$event_tm = date_create_from_format("m/d/Y-H:i:s.u", $fields[0]);

					// Check the 'CATEGORY' field for the text "(null)" and
					// substitute "No classtype defined".
					if ($fields['class'] == "(null)")
						$fields['class'] = "No classtype assigned";

					$suricata_alerts[$counter]['instanceid'] = strtoupper(convert_friendly_interface_to_friendly_descr($a_instance[$instanceid]['interface']));
					$suricata_alerts[$counter]['timestamp'] = strval(date_timestamp_get($event_tm));
					$suricata_alerts[$counter]['timeonly'] = date_format($event_tm, "H:i:s");
					$suricata_alerts[$counter]['dateonly'] = date_format($event_tm, "M d");
					$suricata_alerts[$counter]['msg'] = $fields['msg'];
					// Add square brackets around any IPv6 address
					if (is_ipaddrv6($fields['src']))
						$suricata_alerts[$counter]['src'] = "[" . $fields['src'] . "]";
					else
						$suricata_alerts[$counter]['src'] = $fields['src'];
					// Add the SRC PORT if not null
					if (!empty($fields['sport']) || $fields['sport'] == '0')					
						$suricata_alerts[$counter]['src'] .= ":" . $fields['sport'];
					// Add square brackets around any IPv6 address
					if (is_ipaddrv6($fields['dst']))
						$suricata_alerts[$counter]['dst'] = "[" . $fields['dst'] . "]";
					else
						$suricata_alerts[$counter]['dst'] = $fields['dst'];
					// Add the DST PORT if not null
					if (!empty($fields['dport']) || $fields['dport'] == '0')
						$suricata_alerts[$counter]['dst'] .= ":" . $fields['dport'];
					$counter++;
				};
				fclose($fd);
				@unlink("/tmp/surialerts_{$suricata_uuid}");
			};
		};
	};

	// Sort the alerts array
	if (isset($config['syslog']['reverse'])) {
		suricata_sksort($suricata_alerts, 'timestamp', false);
	} else {
		suricata_sksort($suricata_alerts, 'timestamp', true);
	}

	return $suricata_alerts;
}

/* display the result */
?>

<input type="hidden" id="suricata_alerts-config" name="suricata_alerts-config" value=""/>
<div id="suricata_alerts-settings" class="widgetconfigdiv" style="display:none;">
	<form action="/widgets/widgets/suricata_alerts.widget.php" method="post" name="iformd">
		Enter number of recent alerts to display (default is 5)<br/>
		<input type="text" size="5" name="widget_suricata_display_lines" class="formfld unknown" id="widget_suricata_display_lines" value="<?= $config['widgets']['widget_suricata_display_lines'] ?>" />
		&nbsp;&nbsp;<input id="submitd" name="submitd" type="submit" class="formbtn" value="Save" />
    </form>
</div>

<table width="100%" border="0" cellspacing="0" cellpadding="0" style="table-layout: fixed;">
	<colgroup>
		<col style="width: 24%;" />
		<col style="width: 38%;" />
		<col style="width: 38%;" />
	</colgroup>
	<thead>
		<tr>
			<th class="listhdrr"><?=gettext("IF/Date");?></th>
			<th class="listhdrr"><?=gettext("Src/Dst Address");?></th>
			<th class="listhdrr"><?=gettext("Description");?></th>
		</tr>
	</thead>
	<tbody id="suricata-alert-entries">
	<?php
		$suricata_alerts = suricata_widget_get_alerts($suri_nentries);
		$counter=0;
		if (is_array($suricata_alerts)) {
			foreach ($suricata_alerts as $alert) {
				$evenRowClass = $counter % 2 ? " listMReven" : " listMRodd";
				echo("	<tr class='" . $evenRowClass . "'>
				<td class='listMRr'>" . $alert['instanceid'] . " " . $alert['dateonly'] . "<br/>" . $alert['timeonly'] . "</td>		
				<td class='listMRr ellipsis' nowrap><div style='display:inline;' title='" . $alert['src'] . "'>" . $alert['src'] . "</div><br/><div style='display:inline;' title='" . $alert['dst'] . "'>" . $alert['dst'] . "</div></td>
				<td class='listMRr'><div style='display: fixed; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; line-height: 1.2em; max-height: 2.4em; overflow: hidden; text-overflow: ellipsis;' title='{$alert['msg']}'>" . $alert['msg'] . "</div></td></tr>");
				$counter++;
				if($counter >= $suri_nentries)
					break;
			}
		}
	?>
	</tbody>
</table>

<script type="text/javascript">
//<![CDATA[
	var suricataupdateDelay = 10000; // update every 10 seconds
	var suri_nentries = <?php echo $suri_nentries; ?>; // default is 5

<!-- needed to display the widget settings menu -->
	selectIntLink = "suricata_alerts-configure";
	textlink = document.getElementById(selectIntLink);
	textlink.style.display = "inline";
//]]>
</script>

