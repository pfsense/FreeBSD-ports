<?php
/*
 * tailscale_install.inc
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

global $config, $tailscale_is_installing;

require_once('tailscale/tailscale_common.inc');

/*
 * Returns a static array representing the pfSense ifgroup entry for tailscale
 */
function tailscale_ifgroup_entry()
{
	return[
		'ifname'	=> 'Tailscale', 
		'descr'		=> gettext('Tailscale Interface Group (DO NOT EDIT/DELETE!)'),
		'members'	=> null,
	];
}

/* 
 * Installs the tailscale ifgroup, safe to call multiple times
 * 
 * We currently unshift (i.e. place at the top) package-defined interface groups.
 * Packages need to be able to define interface groups that are not visible in the UI.
 * This is the best we can do for now.
 */
function tailscale_ifgroup_install()
{
	global $config;

	$has_ifgroup = false;

	$tailscale_entry = tailscale_ifgroup_entry();

	init_config_arr(['ifgroups', 'ifgroupentry']);

	foreach ($config['ifgroups']['ifgroupentry'] as $entry)
		if ($has_ifgroup = ($entry['ifname'] === $tailscale_entry['ifname']))
			break;

	if ($has_ifgroup)
		return;

	array_unshift($config['ifgroups']['ifgroupentry'], $tailscale_entry);

	write_config(sprintf(gettext('Installed Tailscale interface group (%s)'), $tailscale_entry['ifname']));
}

/*
 * Deinstall the tailscale ifgroup, safe to call multiple times
 */
function tailscale_ifgroup_deinstall()
{
	global $config;

	$had_ifgroup = false;

	$tailscale_entry = tailscale_ifgroup_entry();

	init_config_arr(['ifgroups', 'ifgroupentry']);

	foreach ($config['ifgroups']['ifgroupentry'] as $idx => $entry)
		if ($entry['ifname'] === $tailscale_entry['ifname']) {
			unset($config['ifgroups']['ifgroupentry'][$idx]);
			$had_ifgroup = true;
		}

	if (!$had_ifgroup)
		return;
	
	write_config(sprintf(gettext('Removed Tailscale interface group (%s)'), $tailscale_entry['ifname']));
}

/*
 * Test to see if we are keeping the config on de-install
 */
function tailscale_has_keep_config(): bool
{
	return (tailscale_get_config('tailscale', 'keepconfig', 'on') === 'on');
}

/*
 * Just the opposite as above
 */
function tailscale_not_has_keep_config(): bool
{
	return !(tailscale_has_keep_config());
}

/*
 * Utility function to remove all package settings and state
 */
function tailscale_clean(): void
{
	global $config;

	# 1. remove the state cache on the filesystem, we keep logs and transferred files
	unlink_if_exists(tailscale_state_directory() . '/' . TAILSCALED_STATE);

	# 2. clean the tailscale config tree
	unset($config['installedpackages']['tailscale']);

	# 3. clean the tailscale auth config tree
	unset($config['installedpackages']['tailscaleauth']);

	# 4. save the config
	write_config(gettext('Removed Tailscale package settings and local state cache.'));
}

/*
 * package install hook
 * 
 * Here is where do useful PHP things during package installation
 * 
 * We safe a little bit of global state here in order to later determine if we are
 * in the context of package installation. This state is reset when the resync config
 * hook is called.
 */
function tailscale_install_hook()
{
	global $tailscale_is_installing;

	$tailscale_is_installing = true;

	update_status(sprintf(" \n  %s...", gettext('Creating Tailscale interface group')));

	tailscale_ifgroup_install();

	update_status(sprintf(" %s.\n", gettext('done')));
}

/*
 * package deinstall hook
 * 
 * Here is where do useful PHP things during package deinstallation
 * Note: We can't call the pfsense_tailscaled script anymore, it has already been deleted
 */
function tailscale_deinstall_hook()
{
	update_status(sprintf(" \n  %s...", gettext('Stopping Tailscale service')));

	tailscale_run_rc_command(TAILSCALED_RC, 'stop');

	update_status(sprintf(" %s.\n  %s...", gettext('done'), gettext('Removing Tailscale interface group')));

	tailscale_ifgroup_deinstall();

	if (tailscale_not_has_keep_config()) {

		update_status(sprintf(" %s.\n  %s...", gettext('done'), gettext('Removing Tailscale package settings and state cache')));

		tailscale_clean();

	}

	update_status(sprintf(" %s.\n", gettext('done')));
}
