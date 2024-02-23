<?php
/*
 * tailscale.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2022-2024 Rubicon Communications, LLC (Netgate)
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

global $tailscale_is_installing;

require_once('tailscale/tailscale_common.inc');
require_once('tailscale/tailscale_install.inc');

/*
 * Called to ensure the running state is consistent with the configuration state
 */
function tailscale_service_checkpoint()
{
	# start the service if enabled and not running
	if (tailscale_is_enabled() && tailscale_is_not_running()) {
		tailscale_run_rc_command(PFSENSE_TAILSCALED_RC, 'start');
		return;
	}

	# stop the service if running and not enabled
	if (tailscale_is_not_enabled() && tailscale_is_running()) {
		tailscale_run_rc_command(PFSENSE_TAILSCALED_RC, 'stop');
		return;
	}
}

/*
 * page input validation hook
 * 
 * This is the validation hook for the primary tailscale.xml page. Here we first
 * perform some sanitizing of the advertised routes input (i.e. trim and align
 * subnets to proper start boundaris, so 192.168.10.10/24 becomes 192.168.10.0/24).
 * We then perform some validation of listen port and advertised routes.
 */
function tailscale_validation_hook(&$post, &$input_errors)
{
	tailscale_sanitize_advertisedroutes($post);

	$lport = $post['listenport'];
	if (!empty($lport) && (!ctype_digit($lport) || !is_port($lport)))
		$input_errors[] = sprintf(gettext('Invalid service listen port (%s)'), $lport);

	$interval = $post['autorefreshinterval'];
	if (!empty($interval) && !ctype_digit($interval))
		$input_errors[] = sprintf(gettext('Invalid auto page refresh interval (%s)'), $interval);

	tailscale_input_iter($post, 'advertisedroutevalue', function(&$value, $key, &$input_errors) {
		if (!empty($value) && !is_subnet($value))
			$input_errors[] = sprintf(gettext('%s is not a valid subnet'), $value);
	}, $input_errors);
}

/*
 * page configuration resync hook
 * 
 * This is the resync hook for the primary tailscale.xml page. The goal here is to
 * ensure service state is consistent with the configuration and to update the
 * tailscaled and pfsense_tailscaled rcconf files are current. If changes were made
 * to the rcconf files, the service needs to be restarted,
 * 
 * The resync hook is called during package installation. However, we only want to write
 * configuration to disk and not start or restart services. We check some global state to
 * see if we are installing. If we are installing, we only write configuration.
 */
function tailscale_resync_config_hook()
{
	global $tailscale_is_installing;

	if (!$tailscale_is_installing)
		tailscale_service_checkpoint();

	$need_restart = false;

	if (tailscale_map_and_write_rcconf(tailscale_tailscaled_rcconf_map(), TAILSCALED_RCCONF))
		$need_restart = true;

	if (tailscale_map_and_write_rcconf(tailscale_pfsense_tailscaled_rcconf_map(), PFSENSE_TAILSCALED_RCCONF))
		$need_restart = true;

	# we perform this in the background, it can otherwise block for a while
	if ($need_restart && !$tailscale_is_installing)
		tailscale_run_rc_command(PFSENSE_TAILSCALED_RC, 'restart');

	# definitely not installing at this point
	$tailscale_is_installing = false;
}
