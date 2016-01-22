<?php
/*
    haproxy_upgrade_config.inc
	Copyright (C) 2015 PiBa-NL
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
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
	
	$writeconfigupdate = false;
	if ($configversion < "00.13") {
		/* Do XML upgrade from haproxy 0.31 to haproxy-dev */
		if (is_array($config['installedpackages']['haproxy']['ha_servers'])) {
			$static_output .= "HAProxy, Do XML upgrade from haproxy 0.31 to haproxy-dev\n";
			update_output_window($static_output);

		/* We have an old config */
			$config['installedpackages']['haproxy']['ha_pools']['item'] = array();
			$a_global = &$config['installedpackages']['haproxy'];
			$a_backends = &$config['installedpackages']['haproxy']['ha_backends']['item'];
			$a_oldservers = &$config['installedpackages']['haproxy']['ha_servers']['item'];
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
	
	$writeconfigupdate = $config['installedpackages']['haproxy']['configversion'] <> $configversion;
	if ($writeconfigupdate) {
		$config['installedpackages']['haproxy']['configversion'] = $configversion;
		$static_output .= "HAProxy, write updated config to version: $configversion\n";
		update_output_window($static_output);
		write_config("HAProxy, update xml config version");
	}
}
