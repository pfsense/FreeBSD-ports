<?php

/*
 * apcupsd.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021-2024 Rubicon Communications, LLC (Netgate)
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

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("/usr/local/www/widgets/include/widget-apcupsd.inc");

if (!function_exists('compose_apc_contents')) {
	function compose_apc_contents($widgetkey) {
		global $user_settings;

		if (!isset($user_settings["widgets"][$widgetkey]["apc_temp_dis_type"])) {
			$user_settings["widgets"][$widgetkey]["apc_temp_dis_type"] = "both_deg";
		}
		if (!isset($user_settings["widgets"][$widgetkey]["apc_host_dis"])) {
			$user_settings["widgets"][$widgetkey]["apc_host_dis"] = "no";
		}
		if (!isset($user_settings["widgets"][$widgetkey]["apc_temp_dis_type_var"])) {
			$user_settings["widgets"][$widgetkey]["apc_temp_dis_type_var"] = "1";
		}
		if (!isset($user_settings["widgets"][$widgetkey]["apc_load_warning_threshold"])) {
			$user_settings["widgets"][$widgetkey]["apc_load_warning_threshold"] = "75";
		}
		if (!isset($user_settings["widgets"][$widgetkey]["apc_load_critical_threshold"])) {
			$user_settings["widgets"][$widgetkey]["apc_load_critical_threshold"] = "90";
		}
		if (!isset($user_settings["widgets"][$widgetkey]["apc_temp_warning_threshold"])) {
			$user_settings["widgets"][$widgetkey]["apc_temp_warning_threshold"] = "27";
		}
		if (!isset($user_settings["widgets"][$widgetkey]["apc_temp_critical_threshold"])) {
			$user_settings["widgets"][$widgetkey]["apc_temp_critical_threshold"] = "40";
		}
		if (!isset($user_settings["widgets"][$widgetkey]["apc_charge_warning_threshold"])) {
			$user_settings["widgets"][$widgetkey]["apc_charge_warning_threshold"] = "50";
		}
		if (!isset($user_settings["widgets"][$widgetkey]["apc_charge_critical_threshold"])) {
			$user_settings["widgets"][$widgetkey]["apc_charge_critical_threshold"] = "15";
		}
		if (!isset($user_settings["widgets"][$widgetkey]["apc_bage_warning_threshold"])) {
			$user_settings["widgets"][$widgetkey]["apc_bage_warning_threshold"] = "365";
		}
		if (!isset($user_settings["widgets"][$widgetkey]["apc_bage_critical_threshold"])) {
			$user_settings["widgets"][$widgetkey]["apc_bage_critical_threshold"] = "720";
		}

		if (!(include_once "/usr/local/pkg/apcupsd.inc")) {
			$rtnstr = "";
			$rtnstr .= "<div class=\"panel panel-warning responsive\"><div class=\"panel-heading\"><h2 class=\"panel-title\">apcupsd not installed...</h2></div>\n";
			$rtnstr .= "<pre>\n";
			$rtnstr .= "Please install and configure apcupsd before using this widget.<br />\n";
			$rtnstr .= "</pre>\n";
			$rtnstr .= "</div>\n";
			print($rtnstr);
			exit(1);
		}

		if (check_nis_running_apcupsd()) {
			$nisip = ((check_nis_ip_apcupsd() != '') ? check_nis_ip_apcupsd() : "localhost");
			$nisport = ((check_nis_port_apcupsd() != '') ? check_nis_port_apcupsd() : "3551");

			$ph = popen("/usr/local/sbin/apcaccess -h " . escapeshellarg($nisip) . ":" . escapeshellarg($nisport) . " 2>&1", "r" );
			while ($v = fgets($ph)) {
				$results[trim(explode(': ', $v)[0])]=trim(explode(': ', $v)[1]);
			}
			pclose($ph);

			$rtnstr = "<tr><td>Status</td><td colspan=\"3\">\n";

			if ($results != null) {
				$bchrg = (($results['BCHARGE'] != "") ? str_replace(" Percent", "", $results['BCHARGE']) : null);

				if ($results['STATUS'] != "") {
					$mainstatarray = array("ONLINE", "ON-BATTERY", "OVERLOADED", "BATTERY-LOW", "LOWBATT", "REPLACE-BATTERY", "REPLACEBATT", "COMM-LOST", "COMMLOST", "NOBATT"); //Taken from apcupsd source
					$substatarray = array("CALIBRATION", "CAL", "TRIM", "BOOST", "SHUTDOWN", "SHUTTING-DOWN", "SLAVE", "SLAVEDOWN");  //Taken from apcupsd source
					$statusarray = explode(" ", str_replace(array("ON BATTERY", "BATTERY LOW", "REPLACE BATTERY", "SHUTTING DOWN", "COMM LOST"), array("ON-BATTERY", "BATTERY-LOW", "REPLACE-BATTERY", "SHUTTING-DOWN", "COMM-LOST"), $results['STATUS']));
					$statstr = "";
					$statsubstr = "";

					for ($i=0; ($i <= count($statusarray)); $i++) {
						if (in_array($statusarray[$i], $mainstatarray)) {
							switch ($statusarray[$i]) {
								case "ONLINE":
									$bclr = "green";
									$statstr .= $statusarray[$i] . " ";
									break;
								case "REPLACE-BATTERY":
								case "REPLACEBATT":
								case "COMM-LOST":
								case "COMMLOST":
								case "OVERLOADED":
									$bclr = "orange";
									$statstr .= str_replace("-" , " ", $statusarray[$i]) . " ";
									break;
								case "ON-BATTERY":
								case "BATTERY-LOW":
								case "LOWBATT":
								case "NOBATT":
									$bclr = "red";
									$statstr .= str_replace("-" , " ", $statusarray[$i]) . " ";
									break;
							}
						} elseif (in_array($statusarray[$i], $substatarray)) {
							$statsubstr .= $statusarray[$i] . " ";
						} elseif (($statusarray[$i] != "") && ($statusarray[$i] != null)) {
							$statsubstr .= "Unknown(" . $statusarray[$i] . ") ";
						}
					}

					//Apcupsd doesn't have a charging output, this is a concept to get around that... not sure if the logic pans out
					if (($bchrg != null) &&
					    (in_array("ONLINE", $statusarray)) &&
					    ($bchrg < 100) &&
					    !in_array($statstr, $mainstatarray)) {
						$statstr = str_replace("ONLINE", "CHARGING", $statstr);
						$brot = "45deg";
						$bicn = "fa-solid fa-plug";
						$bclr = "orange";
					} elseif (($bchrg != null) && (in_array("ONLINE", $statusarray))) {
						$brot = "45deg";
						$bicn = "fa-solid fa-plug";
						$bclr = "green";
					} elseif ($bchrg != null) {
						$brot = "270deg";
						if ($bchrg <= 25) {
							$bicn = "fas fa-solid fa-battery-empty";
						} elseif ($bchrg <= 50) {
							$bicn = "fas fa-solid fa-battery-quarter";
						} elseif ($bchrg <= 75) {
							$bicn = "fas fa-solid fa-battery-half";
						} elseif ($bchrg <= 99) {
							$bicn = "fas fa-solid fa-battery-three-quarters";
						} else {
							$bicn = "fas fa-solid fa-battery-empty";
						}
					} else {
						$bicn = "fas fa-solid fa-times-circle";
						$bclr = "orange";
						$statstr = "Unknown " . $statstr;
						$statsubstr = "Unknown " . $statsubstr;
					}

					$rtnstr .= "<span class=\"" . $bicn . "\" style=\"color:" . $bclr . ";font-size:2em;transform: rotate(" . $brot . ");\"></span><span style=\"color:" . $bclr . ";font-style:bold;font-size:2em\">\n";
					$rtnstr .= "&nbsp;" . $statstr . "</span>\n";
					$rtnstr .= (($statsubstr != "") ? "<br />&nbsp;" . $statsubstr . "\n" : "");
					$rtnstr .= (($results['LASTXFER'] != "") ? "<br />Last Transfer: &nbsp;" . $results['LASTXFER'] . "\n" : "");
				}
				$rtnstr .= "</td></tr>\n";

				if ($user_settings["widgets"][$widgetkey]["apc_host_dis"] == "yes") {
					$rtnstr .= "<tr><td>Apcupsd Host</td>\n";
					$rtnstr .= "<td><span class=\"fas fa-solid fa-server\"></span>&nbsp;(" . $nisip . ":" . $nisport . ")</td></tr>\n";
				}

				$rtnstr .= "</td></tr><tr><td>Line Voltage</td>\n";
				$rtnstr .= "<td><span class=\"fa-solid fa-bolt\"></span>&nbsp;" . (($results['LINEV']) ? $results['LINEV'] : "N/A") . (($results['LINEFREQ']!="") ? " (" . $results['LINEFREQ'] . ")" : "") . "</td>\n";
				$rtnstr .= "<td>Out Voltage</td>\n";
				$rtnstr .= "<td><span class=\"fa-solid fa-bolt\"></span>&nbsp;" . (($results['OUTPUTV'] != "") ? $results['OUTPUTV'] : "N/A") . "</td>\n";
				$rtnstr .= "</tr>\n";

				if ($results['LOADPCT']) {
					$rtnstr .= "<tr><td>Load</td><td colspan=\"3\"><div class=\"progress\">";
					$loadpct = str_replace(" Percent", "", $results['LOADPCT']);
					if ($loadpct >= $user_settings["widgets"][$widgetkey]["apc_load_critical_threshold"]) {
						$rtnstr .= "<div id=\"apcupsd_load_meter\" class=\"progress-bar progress-bar-striped progress-bar-success\" role=\"progressbar\" aria-valuenow=\"0\" aria-valuemin=\"0\" aria-valuemax=\"100\" style=\"width: " . $user_settings["widgets"][$widgetkey]["apc_load_warning_threshold"] . "%\"></div>\n";
						$rtnstr .= "<div id=\"apcupsd_load_meter\" class=\"progress-bar progress-bar-striped progress-bar-warning\" role=\"progressbar\" aria-valuenow=\"0\" aria-valuemin=\"0\" aria-valuemax=\"100\" style=\"width: " . ($user_settings["widgets"][$widgetkey]["apc_load_critical_threshold"] - $user_settings["widgets"][$widgetkey]["apc_load_warning_threshold"]) . "%\"></div>\n";
						$rtnstr .= "<div id=\"apcupsd_load_meter\" class=\"progress-bar progress-bar-striped progress-bar-danger\" role=\"progressbar\" aria-valuenow=\"0\" aria-valuemin=\"0\" aria-valuemax=\"100\" style=\"width: " . ($loadpct - $user_settings["widgets"][$widgetkey]["apc_load_critical_threshold"]) . "%\"></div>\n";
					} elseif ($loadpct >= $user_settings["widgets"][$widgetkey]["apc_load_warning_threshold"]) {
						$rtnstr .= "<div id=\"apcupsd_load_meter\" class=\"progress-bar progress-bar-striped progress-bar-success\" role=\"progressbar\" aria-valuenow=\"0\" aria-valuemin=\"0\" aria-valuemax=\"100\" style=\"width: " . $user_settings["widgets"][$widgetkey]["apc_load_warning_threshold"] . "%\"></div>\n";
						$rtnstr .= "<div id=\"apcupsd_load_meter\" class=\"progress-bar progress-bar-striped progress-bar-warning\" role=\"progressbar\" aria-valuenow=\"0\" aria-valuemin=\"0\" aria-valuemax=\"100\" style=\"width: " . ($loadpct - $user_settings["widgets"][$widgetkey]["apc_load_warning_threshold"]) . "%\"></div>\n";
					} else {
						$rtnstr .= "<div id=\"apcupsd_load_meter\" class=\"progress-bar progress-bar-striped progress-bar-success\" role=\"progressbar\" aria-valuenow=\"0\" aria-valuemin=\"0\" aria-valuemax=\"100\" style=\"width: " . $loadpct . "%\"></div>\n";
					}
					$rtnstr .= "</div><span class=\"fas fa-solid fa-info-circle\"></span>&nbsp;" . $loadpct . "%&nbsp;</td></tr>";
				}

				if ($results['ITEMP'] != "") {
					$rtnstr .= "<tr><td>Temp</td><td colspan=\"3\">\n";
					$degf = ((substr(($results['ITEMP']), -1, 1) === "C") ? (((substr(($results['ITEMP']), 0, (strlen($results['ITEMP'])-2)))*(9/5))+(32)) : (substr(($results['ITEMP']), 0, (strlen($results['ITEMP'])-2)))) . " F";
					$degc = ((substr(($results['ITEMP']), -1, 1) === "C") ? (substr(($results['ITEMP']), 0, (strlen($results['ITEMP'])-2))) : (((substr(($results['ITEMP']), 0, (strlen($results['ITEMP'])-2)))-32)*(5/9))) . " C";
					$rtnstr .= "<div class=\"progress\">\n";
					$tempmax = 60;
					if (substr(($degc), 0, (strlen($degc)-2)) >= $user_settings["widgets"][$widgetkey]["apc_temp_critical_threshold"]) {
						$rtnstr .= "<div id=\"apcupsd_temp_meter\" class=\"progress-bar progress-bar-striped progress-bar-success\" role=\"progressbar\" aria-valuenow=\"0\" aria-valuemin=\"0\" aria-valuemax=\"100\" style=\"width: " . (($user_settings["widgets"][$widgetkey]["apc_temp_warning_threshold"]/$tempmax)*100) . "%\"></div>\n";
						$rtnstr .= "<div id=\"apcupsd_temp_meter\" class=\"progress-bar progress-bar-striped progress-bar-warning\" role=\"progressbar\" aria-valuenow=\"0\" aria-valuemin=\"0\" aria-valuemax=\"100\" style=\"width: " . ((($user_settings["widgets"][$widgetkey]["apc_temp_critical_threshold"] - $user_settings["widgets"][$widgetkey]["apc_temp_warning_threshold"])/$tempmax)*100) . "%\"></div>\n";
						$rtnstr .= "<div id=\"apcupsd_temp_meter\" class=\"progress-bar progress-bar-striped progress-bar-danger\" role=\"progressbar\" aria-valuenow=\"0\" aria-valuemin=\"0\" aria-valuemax=\"100\" style=\"width: " . (((ceil(substr(($degc), 0, (strlen($degc)-2))) - $user_settings["widgets"][$widgetkey]["apc_temp_critical_threshold"])/$tempmax)*100) . "%\"></div>\n";
					} elseif (substr(($degc), 0, (strlen($degc)-2)) >= $user_settings["widgets"][$widgetkey]["apc_temp_warning_threshold"]) {
						$rtnstr .= "<div id=\"apcupsd_temp_meter\" class=\"progress-bar progress-bar-striped progress-bar-success\" role=\"progressbar\" aria-valuenow=\"0\" aria-valuemin=\"0\" aria-valuemax=\"100\" style=\"width: 50%\"></div>\n";
						$rtnstr .= "<div id=\"apcupsd_temp_meter\" class=\"progress-bar progress-bar-striped progress-bar-warning\" role=\"progressbar\" aria-valuenow=\"0\" aria-valuemin=\"0\" aria-valuemax=\"100\" style=\"width: " . (((ceil(substr(($degc), 0, (strlen($degc)-2))) - $user_settings["widgets"][$widgetkey]["apc_temp_warning_threshold"])/$tempmax)*100) . "%\"></div>\n";
					} else {
						$rtnstr .= "<div id=\"apcupsd_temp_meter\" class=\"progress-bar progress-bar-striped progress-bar-success\" role=\"progressbar\" aria-valuenow=\"0\" aria-valuemin=\"0\" aria-valuemax=\"100\" style=\"width: " . ((ceil(substr(($degc), 0, (strlen($degc)-2)))/$tempmax)*100) . "%\"></div>\n";
					}

					$rtnstr .= "</div>\n<span class=\"fas fa-solid fa-thermometer-full\"></span>&nbsp;&nbsp;";
					switch($user_settings['widgets'][$widgetkey]['apc_temp_dis_type']) {
						case 'degf':
							$rtnstr .= $degf;
							break;
						case 'both_deg':
							if ($user_settings["widgets"][$widgetkey]["apc_temp_dis_type_var"]=="1") {
								$rtnstr .= $degc . "&nbsp;(" . $degf . ")";
							} else {
								$rtnstr .= $degf . "&nbsp;(" . $degc . ")";
							}
							break;
						default:
						case 'degc':
							$rtnstr .= $degc;
							break;
					}
					$rtnstr .= "&nbsp;</td></tr>\n";
				}
				$rtnstr .= "<tr><td>Battery Charge</td>\n";

				if (($bchrg != null) && ($bchrg <= $user_settings["widgets"][$widgetkey]["apc_charge_critical_threshold"])) {
					$bchrgpbstyle = "progress-bar-danger";
				} elseif (($bchrg != null) && ($bchrg <= $user_settings["widgets"][$widgetkey]["apc_charge_warning_threshold"])) {
					$bchrgpbstyle = "progress-bar-warning";
				} else {
					$bchrgpbstyle = "progress-bar-success";
				}
				$rtnstr .= ($bchrg != null) ? "<td colspan=\"3\"><div class=\"progress\"><div id=\"apcupsd_bcharge_meter\" class=\"progress-bar progress-bar-striped " . $bchrgpbstyle . "\" role=\"progressbar\" aria-valuenow=\"0\" aria-valuemin=\"0\" aria-valuemax=\"100\" style=\"width: " . $bchrg . "%\"></div></div>\n" : "";
				$rtnstr .= ($bchrg != null) ? "<span class=\"fas fa-solid fa-battery-full\" ></span>&nbsp;" . $bchrg . "%\n" : "";

				$rtnstr .= (($results['BATTV'] != "") ? "<span class=\"fa-solid fa-bolt\" style=\"padding-left:1em\" ></span>&nbsp;" . $results['BATTV'] . "</td></tr>\n" : "</tr>\n");

				if ($results['TIMELEFT'] != "") {
					$rtnstr .= "<tr><td>Time Remaining</td>";
					$rtnstr .= "<td colspan=\"3\"><span class=\"fa-regular fa-clock\"></span>&nbsp;" . $results['TIMELEFT'] . "</td></tr>\n";
				}

				$rtnstr .= "<tr><td>Battery Age</td>";
				$rtnstr .= "<td colspan=\"3\">\n";

				if ($results['BATTDATE'] != "") {
					$batt_org = (new DateTime($results['BATTDATE']));
					$dtnow = (new DateTime());
					//$batt_age_str = ($batt_org->diff($dtnow))->format("Year:%y;Month:%m;Day:%d;Hour:%h;Minute:%i;Second:%s;TotalDays:%a");
					$batt_age_str = ($batt_org->diff($dtnow))->format("Year:%y;Month:%m;Day:%d;Hour:%h;TotalDays:%a");
					$batt_age_fstr = "";
					$batt_age = array();

					foreach(explode(";", $batt_age_str) as $name=>$v) {
						$batt_age[trim(explode(":",$v)[0])]=trim(explode(":",$v)[1]);

						if ((trim(explode(":",$v)[1]) != 0) && (trim(explode(":",$v)[0]) != "TotalDays")) {
							$batt_age_fstr .= (trim(explode(":",$v)[1])) . "&nbsp;" . (trim(explode(":",$v)[0])) . ((trim(explode(":",$v)[1]) != 1) ? "s" : "") . "&nbsp;";
						}
					}

					if ($batt_age['TotalDays'] >= $user_settings["widgets"][$widgetkey]["apc_bage_critical_threshold"]) {
						$bageclr = "red";
						$bageicn = "fa-regular fa-calendar-xmark";
					} elseif ($batt_age['TotalDays'] >= $user_settings["widgets"][$widgetkey]["apc_bage_warning_threshold"]) {
						$bageclr = "orange";
						$bageicn = "fa-regular fa-calendar-minus";
					} else {
						$bageclr = "green";
						$bageicn = "fa-regular fa-calendar-check";
					}
				} else {
					$bageclr = "orange";
					$bageicn = "fa-regular fa-calendar-minus";
					$batt_age_fstr = "Unknown Battery Date";
					$batt_org = (DateTime::createFromFormat('m/d/Y H:i:s', '01/01/1900 00:00:00'));
				}
				$rtnstr .= "<span style=\"color:" . $bageclr . ";font-style:bold;font-size:1em;\"><span class=\"" . $bageicn . "\"></span>";
				$rtnstr .= "&nbsp;" . $batt_age_fstr . "&nbsp;(" . $batt_org->format("m/d/Y") . ")</span>\n";

				/*From apcupsd documentation
					OK: self test indicates good battery
					BT: self test failed due to insufficient battery capacity
					NG: self test failed due to overload
					NO: No results (i.e. no self test performed in the last 5 minutes)*/
				if ($results['SELFTEST'] != "") {
					switch ($results['SELFTEST']) {
						case "OK":
							$stesticn = "fa-solid fa-check-square";
							$stestclr = "green";
							$steststr = "Pass";
						break;
						case "BT":
							$stesticn = "fa-solid fa-exclamation-triangle";
							$stestclr = "red";
							$steststr = "Failed (Capacity)";
						break;
						case "NG":
							$stesticn = "fa-solid fa-exclamation-triangle";
							$stestclr = "red";
							$steststr = "Failed (Overload)";
						break;
						case "NO":
							$stesticn = "fas fa-solid fa-question-square";
							$stestclr = "orange";
							$steststr = "Unknown (No Recent Test)";
						break;
					}
					$rtnstr .="<br /><span class=\"" . $stesticn . "\" style=\"color:" . $stestclr . ";font-style:bold;font-size:0.9em;padding-left:1em\">\n";
					$rtnstr .= "&nbsp;Last Test:&nbsp;" . $steststr . "</span>\n";
				}

				$rtnstr .= "</td></tr>\n";
			} else {
				$rtnstr .= "<div class=\"panel panel-warning responsive\"><div class=\"panel-heading\"><h2 class=\"panel-title\">Status information from apcupsd</h2></div>\n";
				$rtnstr .= "<pre>\n";
				$rtnstr .= "Error retrieving data... <br />\n";
				$rtnstr .= "</pre>\n";
				$rtnstr .= "</div>\n";
			}
		} else {
			$rtnstr .= "<div class=\"panel panel-warning responsive\"><div class=\"panel-heading\"><h2 class=\"panel-title\">Status information from apcupsd</h2></div>\n";
			$rtnstr .= "<pre>\n";
			$rtnstr .= "Network Information Server (NIS) not running, in order to run apcaccess on localhost, you need to enable it on APCupsd General settings. <br />\n";
			$rtnstr .= "</pre>\n";
			$rtnstr .= "</div>\n";
		}
		return($rtnstr);
	}
}

if ($_REQUEST && $_REQUEST['ajax']) {
	print(compose_apc_contents($_REQUEST['widgetkey']));
	exit;
}

if ($_POST['widgetkey']) {
	set_customwidgettitle($user_settings);

	if (!is_array($user_settings["widgets"][$_POST['widgetkey']])) {
		$user_settings["widgets"][$_POST['widgetkey']] = array();
	}
	if (isset($_POST["apc_temp_dis_type"])) {
		$user_settings["widgets"][$_POST['widgetkey']]["apc_temp_dis_type"] = $_POST["apc_temp_dis_type"];
	}
	if (isset($_POST["apc_host_dis"])){
		$user_settings["widgets"][$_POST['widgetkey']]["apc_host_dis"] = $_POST["apc_host_dis"];
	}
	if (isset($_POST["apc_temp_dis_type_var"])) {
		$user_settings["widgets"][$_POST['widgetkey']]["apc_temp_dis_type_var"] = $_POST["apc_temp_dis_type_var"];
	}
	if (isset($_POST["apc_load_warning_threshold"])) {
		$user_settings["widgets"][$_POST['widgetkey']]["apc_load_warning_threshold"] = $_POST["apc_load_warning_threshold"];
	}
	if (isset($_POST["apc_load_critical_threshold"])) {
		$user_settings["widgets"][$_POST['widgetkey']]["apc_load_critical_threshold"] = $_POST["apc_load_critical_threshold"];
	}
	if (isset($_POST["apc_temp_warning_threshold"])) {
		$user_settings["widgets"][$_POST['widgetkey']]["apc_temp_warning_threshold"] = $_POST["apc_temp_warning_threshold"];
	}
	if (isset($_POST["apc_temp_critical_threshold"])) {
		$user_settings["widgets"][$_POST['widgetkey']]["apc_temp_critical_threshold"] = $_POST["apc_temp_critical_threshold"];
	}
	if (isset($_POST["apc_charge_warning_threshold"])) {
		$user_settings["widgets"][$_POST['widgetkey']]["apc_charge_warning_threshold"] = $_POST["apc_charge_warning_threshold"];
	}
	if (isset($_POST["apc_charge_critical_threshold"])) {
		$user_settings["widgets"][$_POST['widgetkey']]["apc_charge_critical_threshold"] = $_POST["apc_charge_critical_threshold"];
	}
	if (isset($_POST["apc_bage_warning_threshold"])) {
		$user_settings["widgets"][$_POST['widgetkey']]["apc_bage_warning_threshold"] = $_POST["apc_bage_warning_threshold"];
	}
	if (isset($_POST["apc_bage_critical_threshold"])) {
		$user_settings["widgets"][$_POST['widgetkey']]["apc_bage_critical_threshold"] = $_POST["apc_bage_critical_threshold"];
	}

	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Updated apcupsd widget settings via dashboard."));

	header("Location: /");
	exit(0);
}
$widgetperiod = isset($config['widgets']['period']) ? $config['widgets']['period'] * 1000 : 10000;

?>
<table class="table table-hover table-striped table-condensed">
	<tbody id="<?=htmlspecialchars($widgetkey)?>-apcupsdimpbody">
		<tr><td><?=gettext("Retrieving data...")?></td></tr>
	</tbody>
</table>
<!-- <a id="apcupsd_apcaccess_refresh" href="#" class="fa-solid fa-arrows-rotate" style="display: none;"></a> -->
</div><div id="<?=$widget_panel_footer_id?>" class="panel-footer collapse">
<form action="/widgets/widgets/apcupsd.widget.php" method="post" class="form-horizontal">
	<?=gen_customwidgettitle_div($widgetconfig['title']); ?>
	<div class="form-group">
		<label class="col-sm-4 control-label"><?=gettext("Display NIS IP:Port")?></label>
		<div class="col-sm-6">
			<div class="radio">
				<label><input name="apc_host_dis" type="radio" id="apc_host_dis" style="padding-right:0.5em" value="yes" <?=(($user_settings["widgets"][$widgetkey]["apc_host_dis"] == "yes") ? "checked" : ""); ?> /> <?=gettext("Yes")?></label>
				<label><input name="apc_host_dis" type="radio" id="apc_host_dis" style="padding-left:1em" value="no" <?=(($user_settings["widgets"][$widgetkey]["apc_host_dis"] == "no") ? "checked" : ""); ?> /> <?=gettext("No")?></label>
			</div>
		</div>
	</div>
	<div class="form-group">
		<label class="col-sm-4 control-label"><?=gettext('Temperature')?></label>
		<div class="col-sm-6">
			<div class="radio">
				<label><input name="apc_temp_dis_type" type="radio" id="apc_temp_dis_type_degf" value="degc" <?=(($user_settings["widgets"][$widgetkey]["apc_temp_dis_type"] == "degc") ? "checked" : ""); ?> /> <?=gettext("Use °C")?></label>
			</div>
			<div class="radio">
				<label><input name="apc_temp_dis_type" type="radio" id="apc_temp_dis_type_degc" value="degf" <?=(($user_settings["widgets"][$widgetkey]["apc_temp_dis_type"] == "degf") ? "checked" : ""); ?> /><?=gettext("Use °F")?></label>
			</div>
			<div class="radio">
				<label>
					<input name="apc_temp_dis_type" type="radio" id="apc_temp_dis_type_both_deg" value="both_deg" <?=(($user_settings["widgets"][$widgetkey]["apc_temp_dis_type"] == "both_deg") ? "checked" : ""); ?> /><?=gettext("Both: ")?>
					<select name="apc_temp_dis_type_var" id="apc_temp_dis_type_both_deg_var">
						<option value="1" <?=(($user_settings["widgets"][$widgetkey]["apc_temp_dis_type_var"] == "1") ? "selected" : ""); ?> >°C (°F)</option>
						<option value="2" <?=(($user_settings["widgets"][$widgetkey]["apc_temp_dis_type_var"] == "2") ? "selected" : ""); ?> >°F (°C)</option>
					</select>
				</label>
			</div>
		</div>
	</div>
	<div class="form-group">
		<label class="col-sm-4 control-label"><?=gettext('Load Levels')?></label>
		<div class="col-sm-6">
			<div class="col-sm-4">
				<label><?=gettext('Warning')?><input type="text"name="apc_load_warning_threshold" id="apc_load_warning_threshold" value="<?= gettext($user_settings["widgets"][$widgetkey]["apc_load_warning_threshold"]); ?>" class="form-control" /></label>
			</div>
			<div class="col-sm-4">
				<label><?=gettext('Critical')?><input type="text" name="apc_load_critical_threshold" id="apc_load_critical_threshold" value="<?= gettext($user_settings["widgets"][$widgetkey]["apc_load_critical_threshold"]); ?>" class="form-control" /></label>
			</div>
		</div>
	</div>
	<div class="form-group">
		<label class="col-sm-4 control-label"><?=gettext('Temp Levels')?></label>
		<div class="col-sm-7">
			<div class="col-sm-5">
				<label><?=gettext('Warning (°C)')?><input type="text" maxlength="2" size="2"  name="apc_temp_warning_threshold" id="apc_temp_warning_threshold" value="<?= gettext($user_settings["widgets"][$widgetkey]["apc_temp_warning_threshold"]); ?>" class="form-control" /></label>
			</div>
			<div class="col-sm-5">
				<label><?=gettext('Critical (°C)')?><input type="text" maxlength="2" size="2" name="apc_temp_critical_threshold" id="apc_temp_critical_threshold" value="<?= gettext($user_settings["widgets"][$widgetkey]["apc_temp_critical_threshold"]); ?>" class="form-control" /></label>
			</div>
		</div>
	</div>
	<div class="form-group">
		<label class="col-sm-4 control-label"><?=gettext('Charge Levels')?></label>
		<div class="col-sm-6">
			<div class="col-sm-4">
				<label><?=gettext('Warning')?><input type="text" name="apc_charge_warning_threshold" id="apc_charge_warning_threshold" value="<?= gettext($user_settings["widgets"][$widgetkey]["apc_charge_warning_threshold"]); ?>" class="form-control" /></label>
			</div>
			<div class="col-sm-4">
				<label><?=gettext('Critical')?><input type="text" name="apc_charge_critical_threshold" id="apc_charge_critical_threshold" value="<?= gettext($user_settings["widgets"][$widgetkey]["apc_charge_critical_threshold"]); ?>" class="form-control" /></label>
			</div>
		</div>
	</div>
	<div class="form-group">
		<label class="col-sm-4 control-label"><?=gettext('Battery Age Levels')?></label>
		<div class="col-sm-6">
			<div class="col-sm-4">
				<label><?=gettext('Warning')?><input type="text" name="apc_bage_warning_threshold" id="apc_bage_warning_threshold" value="<?= gettext($user_settings["widgets"][$widgetkey]["apc_bage_warning_threshold"]); ?>" class="form-control" /></label>
			</div>
			<div class="col-sm-4">
				<label><?=gettext('Critical')?><input type="text" name="apc_bage_critical_threshold" id="apc_bage_critical_threshold" value="<?= gettext($user_settings["widgets"][$widgetkey]["apc_bage_critical_threshold"]); ?>" class="form-control" /></label>
			</div>
		</div>
	</div>

	<div class="form-group">
		<div align="center">
			<input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey); ?>">
			<button type="submit" class="btn btn-primary"><i class="fa-solid fa-save icon-embed-btn"></i><?=gettext('Save')?></button>
		</div>
	</div>
</form>

<script type="text/javascript">
events.push(function()
{
	function apcupsd_refresh_callback(s){
		$(<?= json_encode('#' . $widgetkey . '-apcupsdimpbody')?>).html(s);
	}

	var postdata = {
		ajax: "ajax",
		widgetkey : <?=json_encode($widgetkey)?>
	};

	var refreshObject = new Object();
	refreshObject.name = "RefreshAPCUPSD";
	refreshObject.url = "/widgets/widgets/apcupsd.widget.php";
	refreshObject.callback = apcupsd_refresh_callback;
	refreshObject.parms = postdata;
	refreshObject.freq = 1;

	register_ajax(refreshObject);
});
</script>
