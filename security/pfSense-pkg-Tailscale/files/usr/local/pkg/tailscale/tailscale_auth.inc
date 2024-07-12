<?php
/*
 * tailscale_auth.inc
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

require_once('tailscale/tailscale_common.inc');

/* 
 * Utility function for cleanly restarting tailscale (e.g. on authkey changes)
 * 
 * See rc.d/pfsense_tailscaled for platform semantics
 */
function tailscale_clean_restart()
{
	# need to save this state before cleaning
	$needs_start = (tailscale_is_enabled() && tailscale_is_running());

	# this stops tailscale and purges the local state cache
	tailscale_run_rc_command(PFSENSE_TAILSCALED_RC, 'clean', false);

	# only start if tailscale was running before cleaning
	if ($needs_start)
		tailscale_run_rc_command(PFSENSE_TAILSCALED_RC, 'start', false);
}

/*
 * page input validation hook
 * 
 * This is the validation hook for the tailscale_auth.xml page. Here we just validate
 * the login server as a proper URL.
 */
function tailscale_auth_validation_hook(&$post, &$input_errors)
{
	$loginserver = $post['loginserver'];
	if (!empty($loginserver) && !is_URL($loginserver))
		$input_errors[] = sprintf(gettext('Login Server (%s) is not a valid URL.'), $loginserver);
}

/* 
 * page resync config hook
 * 
 * This is the resync hook for the tailscale_auth.xml page. The goal here is to
 * ensure service state is consistent with the configuration and to update the
 * pfsense_tailscaled rcconf file. If changes were made to the rcconf files,
 * the service needs to be restarted,
 */
function tailscale_auth_resync_config_hook()
{
	if (!empty($_POST['service_clean'])) {
		tailscale_run_rc_command(PFSENSE_TAILSCALED_RC, 'clean', false);
		return;
	}

	$pfsense_tailscaled_map = tailscale_pfsense_tailscaled_rcconf_map();
	if (tailscale_map_and_write_rcconf($pfsense_tailscaled_map, PFSENSE_TAILSCALED_RCCONF))
		tailscale_clean_restart();
}
