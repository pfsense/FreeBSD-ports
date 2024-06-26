<?php
/*
 * nut_status.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2016-2024 Denny Page
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


require_once("/usr/local/www/widgets/include/nut_status.inc");
require_once("/usr/local/pkg/nut/nut.inc");


if ($_REQUEST && $_REQUEST['ajax']) {
	print_table();
	exit;
}


function print_row($desc, $value) {
	print '<tr>';
	print '  <th style="width:19%">' . $desc . '</th>';
	print '  <td>' . $value . '</td>';
	print '</tr>';
}

function print_row_progressbar($desc, $type, $progress, $txt) {
	print '<tr>';
	print '  <th style="width:19%">' . $desc . '</td>';
	print '  <td>';
	print '    <div class="progress" style="max-width:99%">';
	print '      <div class="progress-bar progress-bar-' . $type . ' progress-bar-striped" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . $progress . '" style="width: ' . $progress . '%"></div>';
	print '    </div>';
	print '    <div style="max-width:99%">';
	print        $txt;
	print '    </div>';
	print '  </td>';
	print '</tr>';
}

function print_table() {
	$status = nut_ups_status();

	if ($status['_alert']) {
		print '<tr>';
		print '<th class="danger">' . gettext("Alert") . '</td>';
		print '<td class="danger"><b>' . gettext("The UPS requires attention") . '</b></td>';
		print '</tr>';
	}

	if (isset($status['ups.status'])) {
		$statwords = $status['ups.status'];

		if (isset($status['battery.charge'])) {
			$battery = $status['battery.charge'];
		} else {
			$battery = 0;
		}

		if (str_contains($statwords, 'OL')) {
			$icon_name = 'plug';
			if ($battery < 50 || str_contains($statwords, 'RB') || str_contains($statwords, 'LB') || str_contains($statwords, 'OVER')) {
				$icon_color = 'red';
			} elseif ($battery < 75 || str_contains($statwords, 'BOOST')) {
				$icon_color = 'orange';
			} else {
				$icon_color = 'green';
			}
		} elseif (str_contains($statwords, 'OB')) {
			if ($battery < 25) {
				$icon_name = 'battery-empty';
				$icon_color = 'red';
			} elseif ($battery < 50) {
				$icon_name = 'battery-quarter';
				$icon_color = 'red';
			} elseif ($battery < 75) {
				$icon_name = 'battery-half';
				$icon_color = 'orange';
			} else {
				$icon_name = 'battery-three-quarters';
				$icon_color = 'orange';
			}
		} else {
			$icon_name = 'question-circle';
			$icon_color = 'red';
		}
	} else {
		$icon_name = 'times-circle';
		$icon_color = 'red';
	}

	$icon = '<span class="fa-solid fa-' . $icon_name . '" style="color:' . $icon_color . ';width:25px;font-size:1.3em;"></span>';
	print_row("Summary", $icon . '<span style="font-size:1.1em;">' . htmlspecialchars($status['_summary']) . '</span>');


	if (isset($status['ups.alarm'])) {
		print_row(gettext("Alarm"), htmlspecialchars($status['ups.alarm']));
	}
	if (isset($status['_hms'])) {
		print_row(gettext("Runtime"), htmlspecialchars($status['_hms']) . '&nbsp;&nbsp;(H:M:S)');
	}
	if (isset($status['ups.load'])) {
		$load = htmlspecialchars($status['ups.load']);
		if ($load < 95) {
			$type = 'success';
		} elseif ($load < 99) {
			$type = 'warning';
		} else {
			$type = 'danger';
		}
		$txt = '<span style="width:30%;float:left;">' . $load . '%</span>';

		if (isset($status['input.voltage'])) {
			$txt .= '<span style="width:30%;float:left;"> Vin ' . htmlspecialchars($status['input.voltage']) . 'V</span>';
			if (isset($status['output.voltage'])) {
				$txt .= 'Vout ' . htmlspecialchars($status['output.voltage']) . 'V';
			}
		}

		print_row_progressbar(gettext("Load"), $type, $load, $txt);
	}

	if (isset($status['battery.charge'])) {
		$battery = htmlspecialchars($status['battery.charge']);
		if ($battery < 50) {
			$type = 'danger';
		} elseif ($battery < 75) {
			$type = 'warning';
		} else {
			$type = 'success';
		}

		$txt = '<span style="width:30%;float:left;">' . $battery . '%</span>';
		if (isset($status['battery.voltage'])) {
			$txt .= '<span style="width:30%;float:left;">' . htmlspecialchars($status['battery.voltage']) . 'V</span>';
		}

		/*
		 * Currently we only support battery installation date for SNMP UPSs.
		 *
		 * According to NUT:
		 *   Variable "battery.date" is defined as "Battery installation or last change date."
		 *   Variable "battery.mfr.date" is defined as "Battery manufacturing date", and was
		 *   immutable prior to NUT 8.0.
		 *
		 * There does not appear to be universal agreement among UPS manufactures as to how
		 * battery dates fit into USB ids. Some NUT subdrivers have special handling for battery
		 * dates, others do not. As a result, too many of the USB subdrivers report bogus
		 * information in the battery.date field. Some report the manufacture date of the UPS,
		 * while others report a default software date. Some incorrectly report the battery
		 * installation date in the battery.mfr.date field. To add insult to injury, these dates
		 * are oftehn immutable for USB UPSs, making them rather useless. All of this results in
		 * confusion and frustration for the user, so we currently don't do it.
		 *
		 * This decision should be revisited as NUT/USB subdrivers evolve.
		 */
		if (config_get_path('installedpackages/nut/config/0/type') == 'remote_snmp' && isset($status['battery.date'])) {
			$txt .= 'Installed ' . htmlspecialchars($status['battery.date']);
		}
		print_row_progressbar(gettext("Battery"), $type, $battery, $txt);
	}

	if (isset($status['ups.test.date'])) {
		$txt = '<span style="width:30%;float:left;">' . htmlspecialchars($status['ups.test.date']) . '</span>';
		if (isset($status['ups.test.result'])) {
			$txt .= 'Result ' . htmlspecialchars($status['ups.test.result']);
		}
		print_row(gettext("Last test"), $txt);
	}
}
?>


<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">
		<tbody id="nuttable">
			<?php print_table(); ?>
		</tbody>
	</table>
</div>


<script type="text/javascript">
//<![CDATA[
    function update_nut() {
        var ajaxRequest;

        ajaxRequest = $.ajax({
                url: "/widgets/widgets/nut_status.widget.php",
                type: "post",
                data: { ajax: "ajax"}
            });

        // Deal with the results of the above ajax call
        ajaxRequest.done(function (response, textStatus, jqXHR) {
            $('#nuttable').html(response);
            // and do it again
            setTimeout(update_nut, 10000);
        });
    }

    events.push(function(){
        update_nut();
    });
//]]>
</script>
