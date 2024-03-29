<?php
/*
 * wireguard.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2021 R. Christian McDonald (https://github.com/rcmcdonald91)
 * Copyright (c) 2021 Vajonam
 * Copyright (c) 2020 Ascrod
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

// pfSense includes
require_once('guiconfig.inc');
require_once('util.inc');

// WireGuard includes
require_once('wireguard/includes/wg.inc');
require_once('wireguard/includes/wg_guiconfig.inc');

// Widget includes
require_once('/usr/local/www/widgets/include/wireguard.inc');

global $wgg;

wg_globals();

$widgetkey			= (isset($_POST['widgetkey'])) ? $_POST['widgetkey'] : $widgetkey;

$widget_config			= $user_settings['widgets'][$widgetkey];

// Define default widget behavior
$wireguard_refresh_interval	= (isset($widget_config['refresh_interval']) && is_numericint($widget_config['refresh_interval'])) ? $widget_config['refresh_interval'] : $wgg['default_widget_refresh_interval'];

$wireguard_activity_threshold	= (isset($widget_config['activity_threshold']) && is_numericint($widget_config['activity_threshold'])) ? $widget_config['activity_threshold'] : $wgg['default_widget_activity_threshold'];

// Are we handling an ajax refresh?
if (isset($_POST['ajax'])) {

	print(wg_compose_widget_body($widgetkey, $wireguard_activity_threshold));

	// We are done here...
	exit();

}

// Are we saving the configurable settings?
if (isset($_POST['save'])) {

	// Process settings post
	wg_do_widget_settings_post($_POST, $user_settings);
	
	// Redirect back to home...
	header('Location: /');
	
	// We are done here...
	exit();

}

?>
	<div class="table-responsive">
		<table class="table table-hover table-striped table-condensed" style="overflow-x: visible;">
			<thead>
				<th><?=gettext('Tunnel')?></th>
				<th><?=gettext('Description')?></th>
				<th><?=(($wireguard_activity_threshold == 0) ? gettext('Peers') : gettext('Active Peers'))?></th>
				<th><?=gettext('Listen Port')?></th>
				<th><?=gettext('RX')?></th>
				<th><?=gettext('TX')?></th>
			</thead>
			<tbody id="<?=htmlspecialchars($widgetkey)?>">
				<?=wg_compose_widget_body($widgetkey, $wireguard_activity_threshold)?>
			</tbody>
		</table>
	</div>
</div>

<div id="widget-<?=htmlspecialchars($widgetkey)?>_panel-footer" class="panel-footer collapse">

	<form action="/widgets/widgets/<?=$widgetconfig['basename']?>.widget.php" method="post" class="form-horizontal">
		<input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey)?>" />
		<input type="hidden" name="save" value="save" />

		<div class="form-group">
			<label for="<?=htmlspecialchars($widgetkey)?>_refresh_interval" class="col-sm-4 control-label"><?=gettext('Refresh Interval')?></label>
			<div class="col-sm-8">
				<input type="number" id="<?=htmlspecialchars($widgetkey)?>_refresh_interval" name="<?=htmlspecialchars($widgetkey)?>_refresh_interval" value="<?=htmlspecialchars($wireguard_refresh_interval)?>" placeholder="<?=$wgg['default_widget_refresh_interval']?>" min="0" max="10" class="form-control" />
				<span class="help-block">
					<?=gettext('Widget refresh interval (in ticks).')?>
					<br />
					<span class="text-danger">Note:</span>
					<?=sprintf(gettext('The default is %s tick (0 to disable).'), $wgg['default_widget_refresh_interval'])?>
				</span>
			</div>
		</div>

		<div class="form-group">
			<label for="<?=htmlspecialchars($widgetkey)?>_activity_threshold" class="col-sm-4 control-label">
				<span><?=gettext('Activity Threshold')?></span>
			</label>
			<div class="col-sm-8">
				<input type="number" id="<?=htmlspecialchars($widgetkey)?>_activity_threshold" name="<?=htmlspecialchars($widgetkey)?>_activity_threshold" value="<?=htmlspecialchars($wireguard_activity_threshold)?>" placeholder="<?=$wgg['default_widget_activity_threshold']?>" min="0" class="form-control" />
				<span class="help-block">
					<?=gettext('Peer activity threshold (in seconds).')?>
					<br />
					<span class="text-danger">Note:</span>
					<?=sprintf(gettext('The default is %s seconds (0 to disable).'), $wgg['default_widget_activity_threshold'])?>
				</span>
			</div>
		</div>

		<nav class="action-buttons">
			<button type="submit" class="btn btn-primary">
				<i class="fa-solid fa-save icon-embed-btn"></i>
				<?=gettext('Save')?>
			</button>
		</nav>
	</form>

	<script type="text/javascript">
	//<![CDATA[
	events.push(function(){

		var wireguardRefreshInterval = <?=json_encode($wireguard_refresh_interval)?>;

		// Callback function called by refresh system when data is retrieved
		function wireguard_callback(s) {
			$(<?=json_encode("#{$widgetkey}")?>).html(s);
		}

		// POST data to send via AJAX
		var postdata = {
			ajax: "ajax",
			widgetkey: <?=json_encode($widgetkey)?>
		};

		if (wireguardRefreshInterval > 0) {

			// Create an object defining the widget refresh AJAX call
			var wireguardObject = new Object();
			wireguardObject.name = "wireguard";
			wireguardObject.url = "/widgets/widgets/wireguard.widget.php";
			wireguardObject.callback = wireguard_callback;
			wireguardObject.parms = postdata;
			wireguardObject.freq = wireguardRefreshInterval;

			// Register the AJAX object
			register_ajax(wireguardObject);

		}

	});
	//]]>
	</script>
