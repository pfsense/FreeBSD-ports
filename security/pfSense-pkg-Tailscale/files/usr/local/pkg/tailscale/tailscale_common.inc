<?php
/*
 * tailscale_common.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2022-2023 Rubicon Communications, LLC (Netgate)
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

global $config;

require_once('service-utils.inc');
require_once('vendor/autoload.php');

use Nette\Utils\Html;

define('TAILSCALED_RC', 'tailscaled');
define('PFSENSE_TAILSCALED_RC', 'pfsense_tailscaled');

define('PKG_BIN', '/usr/sbin/pkg');
define('SERVICE_BIN', '/usr/sbin/service');
define('SYSRC_BIN', '/usr/sbin/sysrc');
define('TAILSCALE_BIN', '/usr/local/bin/tailscale');

define('PFSENSE_TAILSCALED_RCCONF', '/usr/local/etc/rc.conf.d/pfsense_tailscaled');
define('TAILSCALED_RCCONF', '/usr/local/etc/rc.conf.d/tailscaled');

define('TAILSCALED_STATE', 'tailscaled.state');
define('TAILSCALED_STATE_DIR', '/usr/local/pkg/tailscale/state');

define('TAILSCALE_SERVER', 'https://controlplane.tailscale.com');

define('SYSRC_NO', 'NO');
define('SYSRC_YES', 'YES');

/*
 * Utility function to format an array of status commands as an HTML
 * table for eventually printing in the browser.
 * 
 * $commands = [
 * 		'command_key' => [
 * 			'title'	=> 'This is some commmand',
 * 			'exec'	=> '/usr/local/bin/tailscale status'
 * 		],
 * ];
 */ 
function tailscale_status_gen_cmd_html($commands, $key)
{
	$table = Html::el('table');

	$table->class[] = 'table';
	$table->class[] = 'table-hover';
	$table->class[] = 'table-condensed';

	$command = $commands[$key];

	# this formats the first row of the table
	$table->addHtml(
		Html::el('tr')->addHtml(
			Html::el('td')->addHtml(
				Html::el('div')
					->name($key)
					->addHtml(
						Html::el('h5')->addHtml(
							Html::el('a')
								->name($key)
								->addText(sprintf('%s (%s)', $command['title'], $command['exec']))
						)
					)
			)
		)
	);

	# execute the $command['exec'] to get the status output
	exec($command['exec'], $out, $rc);

	# something went wrong, so unshift a friendly string to the $out array
	if ($rc !== 0)
		array_unshift($out, sprintf(gettext('Error executing command (%s)'), $command['exec']));

	# this formats the second row of the table that holds the command output
	$table->addHtml(
		Html::el('tr')->addHtml(
			Html::el('td')->addHtml(
				Html::el('pre')
					->id($key)
					->addText(implode("\n", $out))
			)
		)
	);

	# all done
	return $table->toHtml();
}

/*
 * Function to be called by the GUI to print one $command
 */
function tailscale_status_print_cmd($commands, $key)
{
	print(tailscale_status_gen_cmd_html($commands, $key));
}

/*
 * Function to be called by the GUI to print all $commands
 */
function tailscale_status_print_cmds($commands)
{
	foreach ($commands as $command_key => $command)
		tailscale_status_print_cmd($commands, $command_key);
}

/*
 * Utility function to format an array of status commands as an
 * unorderd HTML list.
 * 
 * $commands = [
 * 		'command_key' => [
 * 			'title'	=> 'This is some commmand',
 * 			'exec'	=> '/usr/local/bin/tailscale status'
 * 		],
 * ];
 */ 
function tailscale_status_gen_cmd_list_html($commands)
{
	$ul = Html::el('ul');
	foreach ($commands as $command_key => $command)
		$ul->addHtml(
			Html::el('li')->addHtml(
				Html::el('strong')->addHtml(
					Html::el('a')->href('#'.$command_key)->addText($command['title'])
				)
			)
		);

	$p = Html::el('p')->addHtml($ul);

	return $p->toHtml();
}

/*
 * Function to be called by GUI to print HTML list of status commands
 */
function tailscale_status_print_cmd_list($commands)
{
	print(tailscale_status_gen_cmd_list_html($commands));
}

/*
 * Utility function to define a status command block given a $title and a $command
 * 
 * $title is a friendly name for the command
 * $command is a shell command to be executed
 */
function tailscale_status_define_cmd(&$commands, $key, $title, $exec)
{
	if (isset($commands[$key]))
		return false;

	$commands[$key] = [
		'title'	=> htmlspecialchars($title),
		'exec'	=> $exec,
	];

	return true;
}

/*
 * Utility function for querying pkg(8)
 * 
 * $field_map is a PHP array that maps PHP array keys to pkg(8) query patterns
 * 		Example: ['name' => '%n', 'version' => '%v', 'comment ='%c'];
 * 
 * $packages is a PHP array of packages to query
 */ 
function tailscale_get_pkg_info($field_map = NULL, $packages = NULL)
{
	$pkg_delm = "\t";

	$cmd = implode(' ', [PKG_BIN, 'query', escapeshellarg(implode($pkg_delm, $field_map)), implode(' ', $packages)]);

	exec($cmd, $out, $rc);

	if ($rc !== 0)
		return false;

	$return_keys = array_values(array_flip($field_map));
	foreach ($out as $row_idx => $row_string)
		foreach (explode($pkg_delm, $row_string) as $field_idx => $field_string)
			$ret[$row_idx][$return_keys[$field_idx]] = $field_string;

	return $ret;
}

/*
 * Utility function for querying configuration by $package/$key, falling back to a $default value
 */
function tailscale_get_config($package, $key, $default = NULL)
{
	global $config;
	$path = $config['installedpackages'][trim($package)]['config'][0];
	return (is_array($path) && array_key_exists($key, $path)) ? $path[$key] : $default;
}

/*
 * Utility function for obtaining the state directory
 */
function tailscale_state_directory(): string
{
	return (tailscale_get_config('tailscale', 'statedir', TAILSCALED_STATE_DIR));
}

/*
 * Just a test if tailscale is enabled
 */
function tailscale_is_enabled()
{
	return (tailscale_get_config('tailscale', 'enable', 'off') === 'on');
}

/*
 * Just a test if tailscale is not enabled
 */
function tailscale_is_not_enabled()
{
    return !tailscale_is_enabled();
}

/* 
 * Just a test if tailscale is running
 */
function tailscale_is_running()
{
	return is_service_running('tailscale');
}

/*
 * Just a test if tailscale is not running
 */
function tailscale_is_not_running()
{
	return !tailscale_is_running();
}

/*
 * Just a test if auto refresh pages is enabled
 */
function tailscale_has_auto_refresh()
{
	return (tailscale_get_config('tailscale', 'autorefresh', 'on') === 'on');
}

/*
 * Query tailscale status and return as a PHP array, false on failure
 */
function tailscale_get_status()
{
	$cmd = implode(' ', [TAILSCALE_BIN, 'status', '--json']);

	exec($cmd, $out, $rc);

	if (($rc === 0) && ($status = json_decode(implode($out), true)))
		return $status;

	return false;
}

function tailscale_check_backend_state(...$states)
{
	if ($status = tailscale_get_status())
		return in_array($status['BackendState'], $states);
	
	return false;
}

function tailscale_is_online()
{
	if ($status = tailscale_get_status())
		return $status['Self']['Online'];

	return false;
}

function tailscale_is_not_online()
{
	return (!tailscale_is_online());
}

/*
 * Function for generating health status as a pretty HTML ul list
 * 
 * $topline is for adding text to the top line
 * $bottomline is for adding text to the bottom line
 */
function tailscale_gen_health_info_html($topline = NULL, $bottomline = NULL)
{
	$fragment = Html::el(null);

	if ($topline)
		$fragment->addHtml(Html::el('p')->addText($topline));

	if (!(($status = tailscale_get_status())
	    && is_array($health = $status['Health'])))
		goto bailout;

	$fragment->addHtml(Html::el('p')->addText(gettext('Status')));

	$ul = Html::el('ul');

	foreach ($status['Health'] as $health_row)
		$ul->addHtml(
			Html::el('li')->addText($health_row)
		);

	$fragment->addHtml($ul);

bailout:
	if ($bottomline)
		$fragment->addHtml(Html::el('p')->addText($bottomline));

	return $fragment->toHtml();
}

/*
 * common page after head hook
 * 
 * This is where any code that needs to be evaluated immediately above the UI tabs.
 * Currently this is the same code all tailscale pages.
 */
function tailscale_common_after_head_hook()
{
	# alert user if tailscale is not enabled
	if (tailscale_is_not_enabled()) {
		$info = gettext('Tailscale is not enabled.');
		$type = 'warning';
		goto print_notice;
	}

	# alert user if tailscale is not running (and enabled)
	if (tailscale_is_not_running()) {
		$info = gettext('Tailscale is not running.');
		$type = 'danger';
		goto print_notice;
	}

	# alert user if tailscale is not online (and enabled and running)
	if (tailscale_is_not_online()) {
		$info = gettext('Tailscale is not online. Refresh or check the Tailscale status page.');
		$type = 'warning';
		goto print_notice;
	}

	# everything looks good
	if (tailscale_is_online()) {
		$info = gettext('Tailscale is online.');
		$type = 'success';
		$ignoreable = true;
		goto print_notice;
	}

	# nothing to print
	return;

print_notice:
	print_info_box($info, $type, $ignoreable ? 'close' : false);
}

/* 
 * Utility function for executing pfsense_tailscaled rc commands
 * 
 * See rc.d/pfsense_tailscaled for platform semantics
 */
function tailscale_run_rc_command($rc, $command, $background = true)
{
	$cmd = implode(' ', [SERVICE_BIN, escapeshellarg($rc), escapeshellarg($command)]);

	$retval = mwexec($cmd, false, false, $background);

	# we want pid on background
	return $background ? $retval : ($retval === 0);
}

/*
 * Utility function for walking an HTML form repeatable element
 * the callback signature here is callable(&$value, $key, &$userdata)
 */
function tailscale_input_iter(&$post, $input_name, $callable, &$userdata = NULL)
{
	$x = 0;
	if (is_callable($callable))
		while (!is_null($post[$input_name.$x]))
			call_user_func_array($callable, [&$post[$input_name.$x], $input_name.($x++), &$userdata]);

	# similar semantics to array_walk()
	return true;
}

/*
 * Parses a $post array containing an HTML form repeatable $field and returns
 * a proper array of values.
 */
function tailscale_input_parse_repeatable($post, $field)
{
	$userdata = [];
	if (is_array($post))
		tailscale_input_iter($post, $field, function(&$value, $key, &$userdata) {
			$userdata[] = $value;
		}, $userdata);

	return $userdata;
}

/*
 * Aligns a route in CIDR format to a subnet start boundary
 */
function tailscale_route_align($cidr_route)
{
	if (!is_subnet($cidr_route))
		return $cidr_route;

	[$address, $mask] = explode('/', $cidr_route);

	return implode('/', [gen_subnet($address, $mask), $mask]);
}

/*
 * Takes a $post array by reference, interates over advertisedroutevalueX keys,
 * trims the values, and then aligns the values to subnet start boundaries.
 */
function tailscale_sanitize_advertisedroutes(&$post)
{
	tailscale_input_iter($post, 'advertisedroutevalue', function (&$value) {
		$value = trim($value);
		$value = tailscale_route_align($value);
	});
}

/*
 * Takes an array of callbacks containing some transformation semantics and returns
 * a new array with the results of these callbacks as values. Indices/keys are preserved.
 * 
 * $var_ map = [
 * 		'key1' => function($x, $y, $z) { 
 * 			return $x.$y.$z; 
 *		},
 * 		'key2' => function($x, $y, $z) {
 * 			return 'value2'; 
 *		},
		'key3' => 'value3',
 * ];
 * 
 * $var_result = tailscale_invoke_var_map($var_map, 'x', 'y', 'z');
 * 
 * $var_result = [
 * 		'key1' => 'xyz',
 * 		'key2' => 'value2',
 * 		'key3' => 'value3',
 * ];
 * 
 * Callback signature is callback($key, ...$extra args) 
 */
function tailscale_invoke_var_map($var_map, ...$extra_args)
{
	if (is_array($var_map)) 
		array_Walk($var_map, function(&$value, $key, $extra_args) {
			$value = is_callable($value) ? call_user_func($value, $key, ...$extra_args) : $value;
		}, $extra_args);

	return $var_map;
}

/*
 * Takes a variable map (see above), invokes the map, and writes the resulting
 * transformation out as a rcconf file using sysrc(8). Only returns true if something
 * meaningful changed on disk. Returns false on errors or no changes.
 */
function tailscale_map_and_write_rcconf($var_map, $output_file)
{
	$formatted_vars = tailscale_invoke_var_map($var_map);

	$var_string = implode(' ', array_values($formatted_vars));

	# first perform sanity check
	$cmd = implode(' ', [SYSRC_BIN, '-c', '-f', $output_file, $var_string]);
	if (mwexec($cmd) === 0)
		return false;

	# looks good, changes needed so write them out
	$cmd = implode(' ', [SYSRC_BIN, '-f', $output_file, $var_string]);
	return (mwexec($cmd) === 0);
}

/*
 * Utility function that takes a $key and $value and returns
 * an sh-style variable assignment like $key="$value"
 */
function tailscale_format_sysrc_variable($key, $value)
{
	return sprintf('%s="%s"', $key, $value);
}

/*
 * The variable map used to rc.conf.d/pfsense_tailscaled
 */
function tailscale_pfsense_tailscaled_rcconf_map()
{
	# pfsense_tailscaled_acceptdns_enable (bool)
	$rcconf_map['pfsense_tailscaled_acceptdns_enable'] = function($key) {
		$value= SYSRC_NO;
		if (tailscale_get_config('tailscale', 'acceptdns', 'on') === 'on')
			$value = SYSRC_YES;
		return tailscale_format_sysrc_variable($key, $value);
	};

	# pfsense_tailscaled_acceptroutes_enable (bool)
	$rcconf_map['pfsense_tailscaled_acceptroutes_enable'] = function($key) {
		$value = SYSRC_NO;
		if (tailscale_get_config('tailscale', 'acceptroutes', 'off') === 'on')
			$value = SYSRC_YES;
		return tailscale_format_sysrc_variable($key, $value);
	};

	# pfsense_tailscaled_advertiseroutes (str)
	$rcconf_map['pfsense_tailscaled_advertiseroutes'] = function($key) {
		$values = tailscale_get_config('tailscale', 'row', []);
		array_walk($values, function(&$value) {
			$value = $value['advertisedroutevalue'];
		});
		$value = implode(',', $values);
		return tailscale_format_sysrc_variable($key, $value);
	};

	# pfsense_tailscaled_authkey (str)
	$rcconf_map['pfsense_tailscaled_authkey'] = function($key) {
		$value = tailscale_get_config('tailscaleauth', 'preauthkey', NULL);
		return tailscale_format_sysrc_variable($key, $value);
	};

	# pfsense_tailscaled_loginserver (str)
	$rcconf_map['pfsense_tailscaled_loginserver'] = function($key) {
		$value = tailscale_get_config('tailscaleauth', 'loginserver', TAILSCALE_SERVER);
		return tailscale_format_sysrc_variable($key, $value);
	};

	# pfsense_tailscaled_exitnode_enable (bool)
	$rcconf_map['pfsense_tailscaled_exitnode_enable'] = function($key) {
		$value = SYSRC_NO;
		if (tailscale_get_config('tailscale', 'exitnode', 'off') === 'on')
			$value = SYSRC_YES;
		return tailscale_format_sysrc_variable($key, $value);
	};

	# This is currently only available on Linux (see https://github.com/tailscale/tailscale/issues/5573)
	# pfsense_tailscaled_snat_subnet_routes (bool)
	/** $rcconf_map['pfsense_tailscaled_snat_subnet_routes'] = function($key) {
		$value = SYSRC_NO;
		if (tailscale_get_config('tailscale', 'snatsubnetroutes', 'off') === 'on')
			$value = SYSRC_YES;
		return tailscale_format_sysrc_variable($key, $value);
	}; */

	return $rcconf_map;
}

/*
 * The variable map used to rc.conf.d/tailscaled
 */
function tailscale_tailscaled_rcconf_map()
{
	# tailscaled_enable (bool)
	$rcconf_map['tailscaled_enable'] = function($key) {
		$value = SYSRC_NO;
		if (tailscale_get_config('tailscale', 'enable', 'off') === 'on')
			$value = SYSRC_YES;
		return tailscale_format_sysrc_variable($key, $value);
	};

	# tailscaled_state_dir (str)
	$rcconf_map['tailscaled_state_dir'] = function($key) {
		return tailscale_format_sysrc_variable($key, tailscale_state_directory());
	};

	# tailscaled_exitnode_enable (bool), handled above
	$rcconf_map['tailscaled_exitnode_enable'] = function($key) {
		return tailscale_format_sysrc_variable($key, SYSRC_NO);
	};

	# tailscaled_port (int)
	$rcconf_map['tailscaled_port'] = function($key) {
		$value = tailscale_get_config('tailscale', 'listenport', 41641);
		return tailscale_format_sysrc_variable($key, $value);
	};

	# tailscaled_syslog_output_enable (bool)
	$rcconf_map['tailscaled_syslog_output_enable'] = function($key) {
		$value = SYSRC_NO;
		if (tailscale_get_config('tailscale', 'syslogenable', 'off') === 'on')
			$value = SYSRC_YES;
		return tailscale_format_sysrc_variable($key, $value);
	};

	# tailscaled_syslog_output_facility (str)
	$rcconf_map['tailscaled_syslog_output_facility'] = function($key) {
		$value = tailscale_get_config('tailscale', 'syslogfacility', 'daemon');
		return tailscale_format_sysrc_variable($key, $value);
	};

	# tailscaled_syslog_output_priority (str)
	$rcconf_map['tailscaled_syslog_output_priority'] = function($key) {
		$value = tailscale_get_config('tailscale', 'syslogpriority', 'notice');
		return tailscale_format_sysrc_variable($key, $value);
	};

	return $rcconf_map;
}
