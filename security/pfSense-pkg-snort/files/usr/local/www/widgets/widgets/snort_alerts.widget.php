<?php
/*
 * snort_alerts.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2009-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2018 Bill Meeks
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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("/usr/local/www/widgets/include/widget-snort.inc");

global $g;

/* retrieve snort variables */
$a_instance = config_get_path('installedpackages/snortglobal/rule', []);

// Set some CSS class variables
$alertRowEvenClass = "listMReven";
$alertRowOddClass = "listMRodd";
$alertColClass = "listMRr";

/* check if Snort widget alert display lines value is set */
$snort_nentries = intval(config_get_path('widgets/widget_snort_display_lines', '5'));
if ($snort_nentries <= 0)
	$snort_nentries = 5;

/* array sorting of the alerts */
function sksort(&$array, $subkey="id", $sort_ascending=false) {
        /* an empty array causes sksort to fail - this test alleviates the error */
	if(empty($array))
	        return false;
	if (count($array)) {
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

// Called by Ajax to update the "snort-alert-entries" <tbody> table element's contents
if (isset($_GET['getNewAlerts'])) {
	$response = "";
	$s_alerts = snort_widget_get_alerts();
	$counter = 0;
	foreach ($s_alerts as $a) {
		$response .= $a['instanceid'] . "||" . $a['dateonly'] . " " . $a['timeonly'] . "||" . $a['src'] . "||";
		$response .= $a['dst'] . "||" . $a['msg'] . "\n";
		$counter++;
		if($counter >= $snort_nentries)
			break;
	}
	echo $response;
	return;
}

// See if saving new display line count value
if(isset($_POST['widget_snort_display_lines'])) {
	if($_POST['widget_snort_display_lines'] == "") {
		config_set_path('widgets/widget_snort_display_lines', '5');
	} else {
		config_set_path('widgets/widget_snort_display_lines', max(intval($_POST['widget_snort_display_lines']), 1));
	}
	write_config("Saved Snort Alerts Widget Displayed Lines Parameter via Dashboard");
	header("Location: ../../index.php");
}

// Read "$snort_nentries" worth of alerts from the top of the alert.log file
// of each configured interface, and then return the most recent '$snort_entries'
// alerts in a sorted array (most recent alert first).
function snort_widget_get_alerts() {

	global $a_instance, $snort_nentries;
	$snort_alerts = array();
	/* read log file(s) */
	$counter=0;
	foreach ($a_instance as $instanceid => $instance) {
		$snort_uuid = $a_instance[$instanceid]['uuid'];
		$if_real = get_real_interface($a_instance[$instanceid]['interface']);

		/* make sure alert file exists, then "tail" the last '$snort_nentries' from it */
		if (file_exists("/var/log/snort/snort_{$if_real}{$snort_uuid}/alert")) {
			exec("tail -{$snort_nentries} -r /var/log/snort/snort_{$if_real}{$snort_uuid}/alert > /tmp/alert_snort{$snort_uuid}");

			if (file_exists("/tmp/alert_snort{$snort_uuid}")) {

				/*              0         1            2      3       4   5     6   7       8   9       10 11             12       13     14          */
				/* File format: timestamp,generator_id,sig_id,sig_rev,msg,proto,src,srcport,dst,dstport,id,classification,priority,action,disposition */
				if (!$fd = fopen("/tmp/alert_snort{$snort_uuid}", "r")) {
					logger(LOG_ERR, localize_text("Widget failed to open file %s", "/tmp/alert_snort{$snort_uuid}"), LOG_PREFIX_PKG_SNORT);
					continue;
				}
				while (($fields = fgetcsv($fd, 1000, ',', '"')) !== FALSE) {
					if(count($fields) < 14 || count($fields) > 15)
						continue;

					// Get the Snort interface this alert was received from
					$snort_alerts[$counter]['instanceid'] = convert_friendly_interface_to_friendly_descr($a_instance[$instanceid]['interface']);

					// "fields[0]" is the complete timestamp in ASCII form. Convert
					// to a UNIX timestamp so we can use it for various date and
					// time formatting.  Also extract the MM/DD/YY component and
					// reverse its order to YY/MM/DD for proper sorting.
					$fields[0] = trim($fields[0]); // remove trailing space before comma delimiter
					$tstamp = strtotime(str_replace("-", " ", $fields[0])); // remove "-" between date and time components
					$tmp = substr($fields[0],6,2) . '/' . substr($fields[0],0,2) . '/' . substr($fields[0],3,2);
					$snort_alerts[$counter]['timestamp'] = str_replace(substr($fields[0],0,8),$tmp,$fields[0]);

					$snort_alerts[$counter]['timeonly'] = date("H:i:s", $tstamp);
					$snort_alerts[$counter]['dateonly'] = date("M d", $tstamp);
					// Add square brackets around any any IPv6 address
					if (strpos($fields[6], ":") === FALSE)
						$snort_alerts[$counter]['src'] = trim($fields[6]);
					else
						$snort_alerts[$counter]['src'] = "[" . trim($fields[6]) . "]";
					// Add the SRC PORT if not null
					if (!empty($fields[7]))
						$snort_alerts[$counter]['src'] .= ":" . trim($fields[7]);
					// Add square brackets around any any IPv6 address
					if (strpos($fields[8], ":") === FALSE)
						$snort_alerts[$counter]['dst'] = trim($fields[8]);
					else
						$snort_alerts[$counter]['dst'] = "[" . trim($fields[8]) . "]";
					// Add the DST PORT if not null
					if (!empty($fields[9]))
						$snort_alerts[$counter]['dst'] .= ":" . trim($fields[9]);
					$snort_alerts[$counter]['msg'] = trim($fields[4]);
					$counter++;
				};
				fclose($fd);
				@unlink("/tmp/alert_snort{$snort_uuid}");
			};
		};
	};

	/* Sort the alerts array in descending order (newewst first) */
	sksort($snort_alerts, 'timestamp', false);

	return $snort_alerts;
}
?>

<table  class="table table-hover table-striped table-condensed" style="table-layout: fixed;">
	<colgroup>
		<col style="width: 24%;" />
		<col style="width: 38%;" />
		<col style="width: 38%;" />
	</colgroup>
	<thead>
		<tr>
			<th><?=gettext("Interface/Time");?></th>
			<th><?=gettext("Src/Dst Address");?></th>
			<th><?=gettext("Description");?></th>
		</tr>
	</thead>
	<tbody id="snort-alert-entries">
	<?php
		$snort_alerts = snort_widget_get_alerts();
		$counter=0;
		if (is_array($snort_alerts)) {
			foreach ($snort_alerts as $alert) {
	?>			
				<tr>
					<td style="overflow: hidden; text-overflow: ellipsis;" nowrap><?=$alert['instanceid']; ?><br/>
						<?=$alert['dateonly']; ?> <?=$alert['timeonly']; ?>
					</td>
					<td style="overflow: hidden; text-overflow: ellipsis;" nowrap>
						<div style="display:inline;" title="<?=$alert['src']; ?>"><?=$alert['src']; ?></div><br/>
						<div style="display:inline;" title="<?=$alert['dst']; ?>"><?=$alert['dst']; ?></div>
					</td>
					<td><div style="display: fixed; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; 
						line-height: 1.2em; max-height: 2.4em; overflow: hidden; text-overflow: ellipsis;" 
						title="<?=$alert['msg']; ?>"><?=$alert['msg']; ?></div>
					</td>
				</tr>
	<?php			$counter++;
				if($counter >= $snort_nentries)
					break;
			}
		}
	?>
	</tbody>
</table>

<!-- close the body we're wrapped in and add a configuration-panel -->
</div>

<div id="widget-<?=$widgetname?>_panel-footer" class="panel-footer collapse">
	<input type="hidden" id="snort_alerts-config" name="snort_alerts-config" value="" />
		<form action="/widgets/widgets/snort_alerts.widget.php" method="post" name="iformd" class="form-horizontal">
			<div class="form-group">
				<label for="widget_snort_display_lines" class="col-sm-4 control-label"><?=gettext('Alerts to Display:')?></label>
				<div class="col-sm-3">
					<input type="number" name="widget_snort_display_lines" class="form-control" id="widget_snort_display_lines" 
					value="<?= config_get_path('widgets/widget_snort_display_lines') ?>" placeholder="5" min="1" max="20" />
				</div>
				<div class="col-sm-3">
					<button id="submitd" name="submitd" type="submit" class="btn btn-sm btn-primary"><?=gettext('Save')?></button>
				</div>
			</div>
		</form>

<script type="text/javascript">
//<![CDATA[
<!-- needed in the snort_alerts.js file code -->
	var snortupdateDelay = 15000; // update every 15 seconds
	var snort_nentries = <?=$snort_nentries;?>; // number of alerts to display (5 is default)
//]]>
</script>

