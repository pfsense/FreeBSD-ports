<?php
/*
 * suricata_alerts.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper
 * Copyright (c) 2005 Bill Marquette
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2025 Bill Meeks
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
require_once("/usr/local/www/widgets/include/widget-suricata.inc");

global $g;

/* array sorting */
function suricata_sksort(&$array, $subkey="id", $sort_ascending=false) {
        /* an empty array causes suricata_sksort to fail - this test alleviates the error */
	if(empty($array)) {
		return false;
	}

	if (count($array)){
		$temp_array[key($array)] = array_shift($array);
	}

	foreach ($array as $key => $val) {
		$offset = 0;
		$found = false;

		foreach ($temp_array as $tmp_key => $tmp_val) {
			if (!$found and strtolower($val[$subkey]) > strtolower($tmp_val[$subkey])) {
				$temp_array = array_merge((array)array_slice($temp_array,0,$offset), array($key => $val), array_slice($temp_array,$offset));
				$found = true;
			}

			$offset++;
		}

		if (!$found) {
			$temp_array = array_merge($temp_array, array($key => $val));
		}
	}

	if ($sort_ascending) {
		$array = array_reverse($temp_array);
	} else $array = $temp_array;
        /* below is the complement for empty array test */
        return true;
}

/* check if suricata widget variable is set */
$suri_nentries = config_get_path('widgets/widget_suricata_display_lines', '5');
if (empty($suri_nentries) || $suri_nentries < 0) {
	$suri_nentries = 5;
}

// Called by Ajax to update alerts table contents
if (isset($_GET['getNewAlerts'])) {
	$response = "";
	$suri_alerts = suricata_widget_get_alerts();
	$counter = 0;
	foreach ($suri_alerts as $a) {
		$response .= $a['instanceid'] . "||" . $a['dateonly'] . " " . $a['timeonly'] . "||" . $a['src'] . "||";
		$response .= $a['dst'] . "||" . $a['msg'] . "\n";
		$counter++;
		if($counter >= $suri_nentries) {
			break;
		}
	}

	echo $response;
	return;
}

if(isset($_POST['widget_suricata_display_lines'])) {
	config_set_path('widgets/widget_suricata_display_lines', $_POST['widget_suricata_display_lines']);
	write_config("Saved Suricata Alerts Widget Displayed Lines Parameter via Dashboard");
	header("Location: ../../index.php");
}

// Read "$suri_nentries" worth of alerts from the top of the alerts.log file
function suricata_widget_get_alerts() {

	global $g, $suri_nentries;
	$suricata_alerts = array();

	/* read log file(s) */
	$counter=0;

	foreach (config_get_path('installedpackages/suricata/rule', []) as $instanceid => $instance) {
		$suricata_uuid = $instance['uuid'];
		$if_real = get_real_interface($instance['interface']);

		// make sure alert file exists, then grab the most recent {$suri_nentries} from it
		// and write them to a temp file.
		if (file_exists(SURICATALOGDIR . "suricata_{$if_real}{$suricata_uuid}/alerts.log")) {
			exec("/usr/bin/tail -{$suri_nentries} -r " . SURICATALOGDIR . "suricata_{$if_real}{$suricata_uuid}/alerts.log > {$g['tmp_path']}/surialerts_{$suricata_uuid}");

			if (file_exists("{$g['tmp_path']}/surialerts_{$suricata_uuid}")) {

				/*************** FORMAT without CSV patch -- ALERT -- ***********************************************************************************/
				/* Line format: timestamp  action[**] [gid:sid:rev] msg [**] [Classification: class] [Priority: pri] {proto} src:srcport -> dst:dstport */
				/*              0          1           2   3   4    5                         6                 7     8      9   10         11  12      */
				/****************************************************************************************************************************************/

				/**************** FORMAT without CSV patch -- DECODER EVENT -- **************************************************************************/
				/* Line format: timestamp  action[**] [gid:sid:rev] msg [**] [Classification: class] [Priority: pri] [**] [Raw pkt: ...]                */
				/*              0          1           2   3   4    5                         6                 7                                       */
				/************** *************************************************************************************************************************/

				if (!$fd = fopen("{$g['tmp_path']}/surialerts_{$suricata_uuid}", "r")) {
					logger(LOG_ERR, localize_text("Widget failed to open file %s", "{$g['tmp_path']}/surialerts_{$suricata_uuid}"), LOG_PREFIX_PKG_SURICATA);
					continue;
				}

				$buf = "";

				while (($buf = fgets($fd)) !== FALSE) {
					$fields = array();
					$tmp = array();

					// Drop any invalid line read from the log excerpt
					if (empty(trim($buf)))
						continue;

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
					try {
						$event_tm = date_create_from_format("m/d/Y-H:i:s.u", $fields[0]);
						$suricata_alerts[$counter]['timestamp'] = strval(date_timestamp_get($event_tm));
						$suricata_alerts[$counter]['timeonly'] = date_format($event_tm, "H:i:s");
						$suricata_alerts[$counter]['dateonly'] = date_format($event_tm, "M d");
					} catch (Exception $e) {
						syslog(LOG_WARNING, "[suricata] WARNING: Dashboard Widget found invalid timestamp entry in current alerts.log, the line will be ignored and skipped.");
						continue;
					}

					// Check the 'CATEGORY' field for the text "(null)" and
					// substitute "No classtype defined".
					if ($fields['class'] == "(null)") {
						$fields['class'] = "No classtype assigned";
					}

					$suricata_alerts[$counter]['instanceid'] = strtoupper(convert_friendly_interface_to_friendly_descr($instance['interface']));
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
				@unlink("{$g['tmp_path']}/surialerts_{$suricata_uuid}");
			};
		};
	};

	// Sort the alerts array by timestamp descending (newest first)
	suricata_sksort($suricata_alerts, 'timestamp', false);

	return $suricata_alerts;
}

/* display the result */
?>

<table class="table table-hover table-striped table-condensed">
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
	<tbody id="suricata-alert-entries">
	<?php
		$suricata_alerts = suricata_widget_get_alerts();
		$counter=0;
		if (is_array($suricata_alerts)) {
			foreach ($suricata_alerts as $alert) {
	?>
				<tr>
					<td style="overflow: hidden; text-overflow: ellipsis;" nowrap><?=$alert['instanceid']; ?><br/>
						<?=$alert['dateonly']; ?> <?=$alert['timeonly']; ?>
					</td>
					<td style="overflow: hidden; text-overflow: ellipsis;" nowrap>
						<div style="display:inline;" title="<?=$alert['src']; ?>"><?=$alert['src']; ?></div><br/>
						<div style="display:inline;" title="<?=$alert['dst']; ?>"><?=$alert['dst']; ?></div>
					</td>
					<td>
						<div style="display: fixed; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; 
							line-height: 1.2em; max-height: 2.4em; overflow: hidden; text-overflow: ellipsis;" 
							title="<?=$alert['msg']; ?>"><?=$alert['msg']; ?></div>
					</td>
				</tr>
	<?php
				$counter++;

				if($counter >= $suri_nentries) {
					break;
				}
			}
		}
	?>
	</tbody>
</table>

<!-- close the body we're wrapped in and add a configuration-panel -->
</div>

<div id="widget-<?=$widgetname?>_panel-footer" class="panel-footer collapse">
	<input type="hidden" id="suricata_alerts-config" name="suricata_alerts-config" value="" />
		<form action="/widgets/widgets/suricata_alerts.widget.php" method="post" name="iformd" class="form-horizontal">
			<div class="form-group">
				<label for="widget_suricata_display_lines" class="col-sm-4 control-label"><?=gettext('Alerts to Display:')?></label>
				<div class="col-sm-3">
					<input type="number" name="widget_suricata_display_lines" class="form-control" id="widget_suricata_display_lines" 
					value="<?= config_get_path('widgets/widget_suricata_display_lines') ?>" placeholder="5" min="1" max="20" />
				</div>
				<div class="col-sm-3">
					<button id="submitd" name="submitd" type="submit" class="btn btn-sm btn-primary"><?=gettext('Save')?></button>
				</div>
			</div>
		</form>


<script type="text/javascript">
//<![CDATA[
	var suricataupdateDelay = 15000; // update every 15 seconds
	var suri_nentries = <?php echo $suri_nentries; ?>; // default is 5
//]]>
</script>

