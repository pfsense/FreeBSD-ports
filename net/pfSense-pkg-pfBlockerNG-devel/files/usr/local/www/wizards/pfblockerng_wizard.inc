<?php
/*
 * pfblockerng_wizard.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2022 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2022 BBcan177@gmail.com
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

require_once('config.inc');
require_once('util.inc');
require_once('services.inc');
require_once('/usr/local/pkg/pfblockerng/pfblockerng.inc');

function step2_stepbeforeformdisplay() {
	global $config, $pkg, $stepid;

	if (isset($config['pfblockerng_wizard'])) {
		unset($config['pfblockerng_wizard']);
	}

	$fields		= &$pkg['step'][2]['fields']['field'];
	$interface_list = pfb_build_if_list(TRUE, FALSE);
	$int_size	= count($interface_list) ?: '1';

	for ($key = 0; $key < 2; $key++) {
		$fields[$key]['options']['option'] = array();
		foreach ($interface_list as $value => $name) {
			$fields[$key]['options']['option'][]	= array('name' => $name, 'value' => $value);
			$fields[$key]['size']			= $int_size;
		}
	}
}

function step2_submitphpaction() {
	global $config, $stepid, $input_errors;

	if ($_POST) {
		if ($_POST['back'] == 'Back') {
			$stepid = 0;
			return;
		}

		if (empty($_POST['inbound_interface']) || empty($_POST['outbound_interface'])) {
			$input_errors[] = 'pfBlockerNG IP Category configuration | Atleast one Inbound and one Outbound interfaces must be selected.';
			step2_stepbeforeformdisplay(); // Reload interface options
			$stepid = 1;
			return;
		}
	}

	$config['pfblockerng_wizard']['step2']['inbound_interface']	= implode(',', (array)$_POST['inbound_interface']);
	$config['pfblockerng_wizard']['step2']['outbound_interface']	= implode(',', (array)$_POST['outbound_interface']);
}

function step3_submitphpaction() {
	global $config, $stepid, $input_errors;

	if ($_POST) {
		if ($_POST['back'] == 'Back') {
			$stepid = 0;
		}
	}

	// Validate DNSBL VIP address
	if (!is_ipaddrv4($_POST['pfb_dnsvip'])) {
		$input_errors[] = 'DNSBL Virtual IP: A valid IPv4 address must be specified.';
	}
	else {
		$ip_validate = where_is_ipaddr_configured($_POST['pfb_dnsvip'], '' , true, true, '');
		if (count($ip_validate)) {
			$input_errors[] = 'DNSBL Virtual IP: Address must be in an isolated Range that is not used in your Network.';
		}
	}

	// Validate DNSBL Port selections
	if (!is_port($_POST['pfb_dnsport']) || !is_port($_POST['pfb_dnsport_ssl'])) {
		$input_errors[] = 'DNSBL Ports: A valid port must be defined.';
	}

	if ($input_errors) {
		$stepid = 2;
	}
}

function step4_submitphpaction() {
	global $config, $pfb, $input_errors;

	// Load all pfBlockerNG Feeds
	$feed_info_raw = json_decode(@file_get_contents("{$pfb['feeds']}"), TRUE);
	if (json_last_error() !== JSON_ERROR_NONE || !is_array($feed_info_raw)) {
		$input_errors[] = 'pfBlockerNG: Feeds database missing. Cannot continue - Reinstall package.';
		return;
	}

	// Remove any pfBlockerNG settings and file/folder contents
	pfb_remove_config_settings();
	pfb_clear_contents();

	// Add default pfBlockerNG configuration
	init_config_arr(array('installedpackages', 'pfblockerng', 'config', 0));
	$new_config = &$config['installedpackages'];
	$new_config['pfblockerng']['config'][0]['enable_cb']				= 'on';
	$new_config['pfblockerng']['config'][0]['pfb_keep']				= 'on';

	init_config_arr(array('installedpackages', 'pfblockerngipsettings', 'config', 0));
	$new_config['pfblockerngipsettings']['config'][0]['enable_dup']			= 'on';
	$new_config['pfblockerngipsettings']['config'][0]['suppression']		= 'on';
	$new_config['pfblockerngipsettings']['config'][0]['inbound_interface']		= $config['pfblockerng_wizard']['step2']['inbound_interface'];
	$new_config['pfblockerngipsettings']['config'][0]['inbound_deny_action']	= 'block';
	$new_config['pfblockerngipsettings']['config'][0]['outbound_interface']		= $config['pfblockerng_wizard']['step2']['outbound_interface']; 
	$new_config['pfblockerngipsettings']['config'][0]['outbound_deny_action']	= 'reject';	
	$new_config['pfblockerngipsettings']['config'][0]['pass_order']			= 'order_0';

	init_config_arr(array('installedpackages', 'pfblockerngdnsblsettings', 'config', 0));
	$new_config['pfblockerngdnsblsettings']['config'][0]['pfb_dnsvip']		= $config['pfblockerng_wizard']['step3']['pfb_dnsvip'];
	$new_config['pfblockerngdnsblsettings']['config'][0]['pfb_dnsport']		= $config['pfblockerng_wizard']['step3']['pfb_dnsport'];
	$new_config['pfblockerngdnsblsettings']['config'][0]['pfb_dnsport_ssl']		= $config['pfblockerng_wizard']['step3']['pfb_dnsport_ssl'];
	$new_config['pfblockerngdnsblsettings']['config'][0]['pfb_dnsblv6']		= $config['pfblockerng_wizard']['step3']['pfb_dnsblv6'] ? 'on' : '';
	$new_config['pfblockerngdnsblsettings']['config'][0]['pfb_dnsbl'] 		= 'on';

	// Default DNSBL Whitelist
	if ($config['pfblockerng_wizard']['step3']['suppression'] == 'on') {

		$new_config['pfblockerngdnsblsettings']['config'][0]['suppression']		= 

"czMuYW1hem9uYXdzLmNvbQ0KczMtMS5hbWF6b25hd3MuY29tICMgQ05BTUUgZm9yIChzMy5hbWF6
b25hd3MuY29tKQ0KLmdpdGh1Yi5jb20NCi5naXRodWJ1c2VyY29udGVudC5jb20gDQpnaXRodWIu
bWFwLmZhc3RseS5uZXQgIyBDTkFNRSBmb3IgKHJhdy5naXRodWJ1c2VyY29udGVudC5jb20pDQou
Z2l0bGFiLmNvbQ0KLnNvdXJjZWZvcmdlLm5ldA0KLmZscy1uYS5hbWF6b24uY29tICMgYWxleGEN
Ci5jb250cm9sLmtvY2hhdmEuY29tICMgYWxleGEgMg0KLmRldmljZS1tZXRyaWNzLXVzLTIuYW1h
em9uLmNvbSAjIGFsZXhhIDMNCi5hbWF6b24tYWRzeXN0ZW0uY29tICMgYW1hem9uIGFwcCBhZHMN
Ci5weC5tb2F0YWRzLmNvbSAjIGFtYXpvbiBhcHAgMg0KLndpbGRjYXJkLm1vYXRhZHMuY29tLmVk
Z2VrZXkubmV0ICMgQ05BTUUgZm9yIChweC5tb2F0YWRzLmNvbSkNCi5lMTMxMzYuZy5ha2FtYWll
ZGdlLm5ldCAjIENOQU1FIGZvciAocHgubW9hdGFkcy5jb20pDQouc2VjdXJlLWdsLmltcndvcmxk
d2lkZS5jb20gIyBhbWF6b24gYXBwIDMNCi5waXhlbC5hZHNhZmVwcm90ZWN0ZWQuY29tICMgYW1h
em9uIGFwcCA0DQouYW55Y2FzdC5waXhlbC5hZHNhZmVwcm90ZWN0ZWQuY29tICMgQ05BTUUgZm9y
IChwaXhlbC5hZHNhZmVwcm90ZWN0ZWQuY29tKQ0KLmJzLnNlcnZpbmctc3lzLmNvbSAjIGFtYXpv
biBhcHAgNQ0KLmJzLmV5ZWJsYXN0ZXIuYWthZG5zLm5ldCAjIENOQU1FIGZvciAoYnMuc2Vydmlu
Zy1zeXMuY29tKQ0KLmJzbGEuZXllYmxhc3Rlci5ha2FkbnMubmV0ICMgQ05BTUUgZm9yIChicy5z
ZXJ2aW5nLXN5cy5jb20pDQouYWRzYWZlcHJvdGVjdGVkLmNvbSAjIGFtYXpvbiBhcHAgNg0KLmFu
eWNhc3Quc3RhdGljLmFkc2FmZXByb3RlY3RlZC5jb20gIyBDTkFNRSBmb3IgKHN0YXRpYy5hZHNh
ZmVwcm90ZWN0ZWQuY29tKQ0KZ29vZ2xlLmNvbQ0Kd3d3Lmdvb2dsZS5jb20NCnlvdXR1YmUuY29t
DQp3d3cueW91dHViZS5jb20NCnlvdXR1YmUtdWkubC5nb29nbGUuY29tICMgQ05BTUUgZm9yICh5
b3V0dWJlLmNvbSkNCnN0YWNrb3ZlcmZsb3cuY29tDQp3d3cuc3RhY2tvdmVyZmxvdy5jb20NCmRy
b3Bib3guY29tDQp3d3cuZHJvcGJveC5jb20NCnd3dy5kcm9wYm94LWRucy5jb20gIyBDTkFNRSBm
b3IgKGRyb3Bib3guY29tKQ0KLmFkc2FmZXByb3RlY3RlZC5jb20NCmNvbnRyb2wua29jaGF2YS5j
b20NCnNlY3VyZS1nbC5pbXJ3b3JsZHdpZGUuY29tDQpwYnMudHdpbWcuY29tICMgdHdpdHRlciBp
bWFnZXMNCnd3dy5wYnMudHdpbWcuY29tICMgdHdpdHRlciBpbWFnZXMNCmNzMTk2LndhYy5lZGdl
Y2FzdGNkbi5uZXQgIyBDTkFNRSBmb3IgKHBicy50d2ltZy5jb20pDQpjczItd2FjLmFwci04MzE1
LmVkZ2VjYXN0ZG5zLm5ldCAjIENOQU1FIGZvciAocGJzLnR3aW1nLmNvbSkNCmNzMi13YWMtdXMu
ODMxNS5lY2Rucy5uZXQgIyBDTkFNRSBmb3IgKHBicy50d2ltZy5jb20pDQpjczQ1LndhYy5lZGdl
Y2FzdGNkbi5uZXQgIyBDTkFNRSBmb3IgKHBicy50d2ltZy5jb20pDQpjczItd2FjLmFwci04MzE1
LmVkZ2VjYXN0ZG5zLm5ldCAjIENOQU1FIGZvciAocGJzLnR3aW1nLmNvbSkNCmNzMi13YWMtdXMu
ODMxNS5lY2Rucy5uZXQgIyBDTkFNRSBmb3IgKHBicy50d2ltZy5jb20pDQpjczQ1LndhYy5lZGdl
Y2FzdGNkbi5uZXQgIyBDTkFNRSBmb3IgKHBicy50d2ltZy5jb20pDQoucGZzZW5zZS5vcmcNCi5u
ZXRnYXRlLmNvbQ0K
";
	}

	// Selected Alias/Groups to add to default installation
	$new = array();
	$new['pfblockernglistsv4']		= $new['pfblockerngdnsbl'] = $new['pfblockerngdnsbl'] = array();
	$new['pfblockernglistsv4']['PRI1']	= $feed_info_raw['ipv4']['PRI1'];
	$new['pfblockerngdnsbl']['ADs_Basic']	= $feed_info_raw['dnsbl']['ADs_Basic'];

	foreach ($new as $key => $data) {
		foreach ($data as $alias => $info) {

			$add			= array();
			$add['aliasname']	= $alias;
			$add['description']	= $info['description'];

			foreach($info['feeds'] as $row) {

				// Remove Feeds that require Registration and Discontinued Feeds
				if (isset($row['register']) || (isset($row['status']) && $row['status'] == 'discontinued')) {
					continue;
				}

				if (!is_array($add['row'])) {
					$add['row'] = array();
				}
				$add['row'][] = array(	'format'	=> 'auto',
							'state'		=> 'Enabled',
							'url'		=> $row['url'],
							'header'	=> $row['header']);
			}

			if (strpos($key, 'dnsbl') !== FALSE) {
				$add['action']		= 'unbound';
				$add['cron']		= 'EveryDay';
				$add['logging']		= 'enabled';
				$add['order']		= 'default';
			} else {
				$add['action']		= 'Deny_Outbound';
				$add['cron']		= '01hour';
				$add['aliaslog']	= 'enabled';
			}
			$add['dow']			= '1';
			$new_config[$key]['config'][] = $add;
		}
	}

	if (isset($config['pfblockerng_wizard'])) {
		unset($config['pfblockerng_wizard']);
	}

	write_config('pfBlockerNG: Default Wizard configuration saved');
	pfb_global();

	// Restart Unbound - Code from services_unbound.php 'apply'
	pfb_unbound_dnsbl('disabled');
	$retval = services_unbound_configure();
	if ($retval == 0) {
		clear_subsystem_dirty('unbound');
	}
	system_resolvconf_generate();   // Update resolv.conf
	system_dhcpleases_configure();  // Start or restart dhcpleases

	// Load Update page to download IP/DNSBL Feeds and finish the installation
	header("Location: /pfblockerng/pfblockerng_update.php?wizard=reload");
	exit;
}
?>
