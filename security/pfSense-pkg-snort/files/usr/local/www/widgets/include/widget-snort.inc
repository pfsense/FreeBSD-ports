<?php
require_once("config.inc");

//set variables for custom title and link
$snort_alerts_title = "Snort Alerts";
$snort_alerts_title_link = "snort/snort_alerts.php";

function widget_snort_uninstall() {

	global $config;

	/* Remove the Snort widget from the Dashboard display list */
	$widgets = $config['widgets']['sequence'];
	if (!empty($widgets)) {
		$widgetlist = explode(",", $widgets);
		foreach ($widgetlist as $key => $widget) {
			if (strstr($widget, "snort_alerts-container"))
				unset($widgetlist[$key]);
		}
		$config['widgets']['sequence'] = implode(",", $widgetlist);
		write_config();
	}
}
?>
