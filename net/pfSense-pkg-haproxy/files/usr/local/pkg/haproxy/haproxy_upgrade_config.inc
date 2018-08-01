<?php
/*
 * haproxy_upgrade_config.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013 PiBa-NL
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

require_once("haproxy_utils.inc");
require_once("pkg-utils.inc");
require_once("haproxy.inc");

function haproxy_upgrade_config() {
	global $config, $static_output;
	// for future config upgrades.
	// make sure the version stays 'comparable'
	if (is_arrayset($config,'installedpackages','haproxy') && isset($config['installedpackages']['haproxy']['configversion']))
		$configversion = $config['installedpackages']['haproxy']['configversion'];
	else
		$configversion = "00.12";

	$static_output .= "HAProxy, from version $configversion\n";
	update_output_window($static_output);
	
	if (!is_array($config['installedpackages'])) {
		$config['installedpackages'] = array();
	}
	if (!is_array($config['installedpackages']['haproxy'])) {
		$config['installedpackages']['haproxy'] = array();
	}

	$writeconfigupdate = false;
	if ($configversion < "00.13") {
		/* Do XML upgrade from haproxy 0.31 to haproxy-dev */
		if (is_array($config['installedpackages']['haproxy']['ha_servers'])) {
			$static_output .= "HAProxy, Do XML upgrade from haproxy 0.31 to haproxy-dev\n";
			update_output_window($static_output);

		/* We have an old config */
			$config['installedpackages']['haproxy']['ha_pools']['item'] = array();
			$a_global = &$config['installedpackages']['haproxy'];
			
			if (!is_array($config['installedpackages']['haproxy']['ha_backends'])) {
				$config['installedpackages']['haproxy']['ha_backends'] = array();
			}
			if (!is_array($config['installedpackages']['haproxy']['ha_backends']['item'])) {
				$config['installedpackages']['haproxy']['ha_backends']['item'] = array();
			}
			$a_backends = &$config['installedpackages']['haproxy']['ha_backends']['item'];

			if (!is_array($config['installedpackages']['haproxy']['ha_servers'])) {
				$config['installedpackages']['haproxy']['ha_servers'] = array();
			}
			if (!is_array($config['installedpackages']['haproxy']['ha_servers']['item'])) {
				$config['installedpackages']['haproxy']['ha_servers']['item'] = array();
			}
			$a_oldservers = &$config['installedpackages']['haproxy']['ha_servers']['item'];

			if (!is_array($config['installedpackages']['haproxy']['ha_pools'])) {
				$config['installedpackages']['haproxy']['ha_pools'] = array();
			}
			if (!is_array($config['installedpackages']['haproxy']['ha_pools']['item'])) {
				$config['installedpackages']['haproxy']['ha_pools']['item'] = array();
			}
			$a_pools = &$config['installedpackages']['haproxy']['ha_pools']['item'];

			foreach ($a_backends as $id => $be) {
				$a_backends[$id]['status'] = 'active';
			}
			$id = 0;
			foreach ($a_oldservers as $oldserver) {
				$pool=$oldserver;
				/* make server sub array */
				$server=array();
				$server['name'] = $oldserver['name'];
				$server['address'] = $oldserver['address'];
				$server['port'] = $oldserver['port'];
				$server['weight'] = $oldserver['weight'];
				$a_servers=array();
				$a_servers[]=$server;
				/* set new pool */
				$pool['name'] = "pool$id";
				$id++;
				$pool['ha_servers']['item']=$a_servers;
				/* link to frontend */
				foreach ($a_backends as $id => $be) {
					if ($a_backends[$id]['name'] == $oldserver['backend']) {
						$a_backends[$id]['backend_serverpool'] = $pool['name'];
						$pool['monitor_uri'] = $be['monitor_uri'];
						unset($a_backends[$id]['monitor_uri']);
						break;
					}
				}
				unset($pool['backend']);
				unset($pool['address']);
				unset($pool['port']);
				unset($pool['weight']);
				$a_pools[] = $pool;
			}
			unset($config['installedpackages']['haproxy']['ha_servers']);
			$writeconfigupdate = true;
		}

		/* XML update to: pkg v1.3 and 'pool' changed to 'backend_serverpool' because 'pool' was added to listtags() in xmlparse.inc */
		if (is_arrayset($config,'installedpackages','haproxy','ha_backends','item',0,'pool')) {
			$static_output .= "HAProxy, Do XML upgrade, change to backend_serverpool from pool array\n";
			update_output_window($static_output);

			foreach($config['installedpackages']['haproxy']['ha_backends']['item'] as &$frontend)
			{
				$backend_serverpool = $frontend['pool'][0];
				$frontend['backend_serverpool'] = $backend_serverpool;
				unset($frontend['pool']);
			}
			$writeconfigupdate = true;
		}
		//also move setting for existing 2.0 installations as only the new variable is used
		if (is_arrayset($config,'installedpackages','haproxy','ha_backends','item',0) &&
			isset($config['installedpackages']['haproxy']['ha_backends']['item'][0]['pool'])) {
			$static_output .= "HAProxy, Do XML upgrade, change to backend_serverpool from pool\n";
			update_output_window($static_output);
			foreach($config['installedpackages']['haproxy']['ha_backends']['item'] as &$frontend)
			{
				$backend_serverpool = $frontend['pool'];
				$frontend['backend_serverpool'] = $backend_serverpool;
				unset($frontend['pool']);
			}
			$writeconfigupdate = true;
		}
		// update config to "haproxy-devel 1.5-dev19 pkg v0.5"
		if(is_arrayset($config,'installedpackages','haproxy','ha_backends','item')) {
			$static_output .= "HAProxy, Do XML upgrade, update frontend options\n";
			update_output_window($static_output);
			foreach ($config['installedpackages']['haproxy']['ha_backends']['item'] as &$bind) {
				if($bind['httpclose'] && $bind['httpclose'] == "yes" ) {
					$bind['httpclose'] = "httpclose";
					$writeconfigupdate = true;
				}
				if (!$bind['extaddr']){
					$bind['extaddr'] = "wan_ipv4";
					$writeconfigupdate = true;
				}
				if ($bind['extaddr'] == "localhost"){
					$bind['extaddr'] = "localhost_ipv4";
					$writeconfigupdate = true;
				}
				if ($bind['extaddr'] == "any"){
					$bind['extaddr'] = "any_ipv4";
					$writeconfigupdate = true;
				}
			}
		}
	}
	if ($configversion < "00.13") {
		// update config to "haproxy-devel 1.5-dev19 pkg v0.13"
		if (is_array($config['installedpackages']['haproxy']['ha_backends']['item'])) {
			foreach ($config['installedpackages']['haproxy']['ha_backends']['item'] as &$bind) {
				if (isset($bind['extaddr'])) {
					$new['extaddr'] = $bind['extaddr'];
					$new['extaddr_port'] = $bind['port'];
					$new['extaddr_ssl'] = $bind['ssloffload'];
					$bind['a_extaddr']['item'][] = $new;
				}
				unset($bind['extaddr']);
				unset($bind['port']);
				//unset($bind['ssloffload']);
			}
		}
		$configversion = "00.13";
	}
	if ($configversion < "00.16") {
		$static_output .= "HAProxy, 00.16\n";
		$static_output .= "HAProxy, NOTICE: Changes to certificate acl's might need adjusting current config accordingly.\n";
		$static_output .= "HAProxy, NOTICE: Certificate acls are now combined with user acls.\n";
		update_output_window($static_output);
		$configversion = "00.16";
	}
	if ($configversion < "00.17") {
		$static_output .= "HAProxy, 00.17\n";
		update_output_window($static_output);
		// remove 'none' ca-cert, and set checkbox to allow for no certificate instead.
		if (is_array($config['installedpackages']['haproxy']['ha_backends']['item'])) {
			foreach ($config['installedpackages']['haproxy']['ha_backends']['item'] as &$bind) {
				$list = array();
				foreach ($bind['clientcert_ca']['item'] as $ca){
					if (empty($ca['cert_ca']))
						$bind['sslclientcert-none'] = 'yes';
					else
						$list[] = $ca;
				}
				$bind['clientcert_ca']['item'] = $list;
			}
		}
		$configversion = "00.17";
	}
	if ($configversion < "00.19") {
		update_output_window($static_output);
		$carpint = $config['installedpackages']['haproxy']['carpdev'];
		if (is_arrayset($config, 'virtualip', 'vip') && is_arrayset($config, 'installedpackages', 'haproxy')) {
			foreach($config['virtualip']['vip'] as $carp) {
				$carp_int = "{$carp['interface']}_vip{$carp['vhid']}";
				if ($carp_int == $carpint) {
					$config['installedpackages']['haproxy']['carpdev'] = $carp['subnet'];
					break;
				}
			}
		}
		$configversion = "00.19";
	}
	if ($configversion < "00.32") {
		$frontends = array();
		if (is_array($config['installedpackages']['haproxy']['ha_backends']['item'])) {
			foreach ($config['installedpackages']['haproxy']['ha_backends']['item'] as &$frontend) {
				$primaryfrontend = get_primaryfrontend($frontend);
				$fe_name = $primaryfrontend['name'];
				$frontends[$fe_name][] = &$frontend;
			}

			foreach ($frontends as $primary) {
				$acl_count = array();
				foreach ($primary as &$frontend){
					$acl_use = array();
					if (!is_array($frontend['a_actionitems'])) {
						$frontend['a_actionitems'] = array();
					}
					if (!is_array($frontend['a_actionitems']['item'])) {
						$frontend['a_actionitems']['item'] = array();
					}
					$a_actions = &$frontend['a_actionitems']['item'];
					if (!is_array($a_actions)) {
						$a_actions = array();
					}

					$primaryfrontend = get_primaryfrontend($frontend);
					$frontendtype = $primaryfrontend['type'];
					$is_default = true;
					if (is_array($frontend['ha_acls']['item'])) {
						$a_acl = &$frontend['ha_acls']['item'];

						foreach ($a_acl as &$aclitem) {
							$aclname = $aclitem['name'];
							$acltype = haproxy_find_acl($aclitem['expression']);
							if ($aclitem['expression'] == "backendservercount") {
								$aclitem['backendservercountbackend'] = $frontend['backend_serverpool'];
							}
							if (!isset($acl_count[$aclname])) {
								$acl_count[$aclname] = 1;
							} else {
								$acl_count[$aclname] += 1;
								$aclitem['name'] .= "_{$acl_count[$aclname]}";
							}
							if (!isset($acltype))
								continue;
							if ($acltype['mode'] != '' && $acltype['mode'] != strtolower($frontendtype)) {
								continue;
							}
							$acl_use[$aclname][] = $aclitem['name'];
						}
						foreach ($acl_use as $key => $acl_x) {
							$aclx = $acl_count[$key];
							$aclnames = "";
							foreach($acl_x as $aclname) {
								$aclnames .= " $aclname";
							}
							$aclnames = trim($aclnames);
							$action['action'] = 'use_backend';
							$action['use_backendbackend'] = $frontend['backend_serverpool'];
							$action['acl'] = $aclnames;
							$a_actions[] = $action;
							$is_default = false;
						}
					}
					if (!$is_default) {
						$frontend['backend_serverpool'] = "";
					}
				}
			}
		}
		$configversion = "00.32";
	}
	if ($configversion < "00.57") {
		if (!is_array($config['installedpackages']['haproxy']['ha_backends'])) {
			$config['installedpackages']['haproxy']['ha_backends'] = array();
		}
		if (!is_array($config['installedpackages']['haproxy']['ha_backends']['item'])) {
			$config['installedpackages']['haproxy']['ha_backends']['item'] = array();
		}
		if (!is_array($config['installedpackages']['haproxy']['ha_pools'])) {
			$config['installedpackages']['haproxy']['ha_pools'] = array();
		}
		if (!is_array($config['installedpackages']['haproxy']['ha_pools']['item'])) {
			$config['installedpackages']['haproxy']['ha_pools']['item'] = array();
		}

		$haproxycfg = &$config['installedpackages']['haproxy'];
		$a_frontend = &$haproxycfg['ha_backends']['item'];
		// - assign unique ID's to all backends and servers for saving/loading server-state file
		//
		// - the backend nolonger automaticaly 'guesses' the desired backend ssl/check-ssl flags..
		// so to keep existing behaviour the backends that are used multiple times 
		// are automatically duplicated with different ssl/checkssl options
		// with settings being explicitly this allows for the user to control when ssl is or isnt used
		// also for tcp frontend/backend combinations where for example ldaps could pass along
		
		$id = 100;
		$newbackends = array();
		foreach($haproxycfg['ha_pools']['item'] as &$backend) {
			$id++;
			$backend['id'] = $id;
			
			$fes = find_frontends_using_backend($backend['name']);
			$httpfrontend = false;
			$tcpfrontend = false;
			foreach($fes as $fename) {
				$frontend = $a_frontend[get_frontend_id($fename)];
				if ($frontend['type'] == "http") {
					$httpfrontend = true;
				} else {
					$tcpfrontend = true;
				}
			}
			if ($httpfrontend && $tcpfrontend) {
				// we have both types.. so duplicate it once for the ssl one..
				$newbackend = $backend;
				$oldvalue = $newbackend['name'];
				$newbackend['name'] = $newbackend['name'] . "_ssl";
				$newvalue = $newbackend['name'];
				$id++;
				$newbackend['id'] = $id;
				if (is_array($newbackend['ha_servers']['item'])) {
					foreach($newbackend['ha_servers']['item'] as &$server) {
						$id++;
						$server['id'] = $id;
						if ($tcpfrontend && $server['ssl'] == 'yes') {
							// https frontend used to automatically switch to using the check-ssl setting..
							unset($server['ssl']);
							$server['checkssl'] = 'yes';
						}
					}
				}
				$newbackends[] = $newbackend;
				
				// rename backend references where required.
				$a_frontends = &$config['installedpackages']['haproxy']['ha_backends']['item'];
				foreach ($a_frontends as &$frontend) {
					if ($frontend['type'] == 'http') {
						continue;
					}
					if ($frontend['backend_serverpool'] == $oldvalue) {
						$frontend['backend_serverpool'] = $newvalue;
					}
					if (is_array($frontend['a_actionitems']['item'])) {
						foreach($frontend['a_actionitems']['item'] as &$item) {
							if ($item['action'] == "use_backend") {
								if ($item['use_backendbackend'] == $oldvalue) {
									$item['use_backendbackend'] = $newvalue;
								}
							}
						}
					}
				}
				unset($frontend);
				
			} else {
				// only https/tcp frontends to this backend..
				if (is_array($backend['ha_servers']['item'])) {
					foreach($backend['ha_servers']['item'] as &$server) {
						$id++;
						$server['id'] = $id;
						if (!$httpfrontend && $server['ssl'] == 'yes') {
							// https frontend used to automatically switch to using the check-ssl setting..
							unset($server['ssl']);
							$server['checkssl'] = true;
						}
					}
				}
			}
		}
		unset($backend);
		// add duplicated backends to the config.
		foreach($newbackends as $newbackend) {
			$haproxycfg['ha_pools']['item'][] = $newbackend;
		}
		$configversion = "00.57";
	}

	$writeconfigupdate = $config['installedpackages']['haproxy']['configversion'] <> $configversion;
	if ($writeconfigupdate) {
		$config['installedpackages']['haproxy']['configversion'] = $configversion;
		$static_output .= "HAProxy, write updated config to version: $configversion\n";
		update_output_window($static_output);
		write_config("HAProxy, update xml config version to version: ". $configversion);
	}
}
