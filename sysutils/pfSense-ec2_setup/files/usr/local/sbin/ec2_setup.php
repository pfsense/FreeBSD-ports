#!/usr/local/bin/php -f
<?php
/*
 * ec2_setup.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016 Rubicon Communications, LLC (Netgate)
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

require_once("globals.inc");
require_once("config.inc");
require_once("auth.inc");
require_once("interfaces.inc");
require_once("certs.inc");
require_once("openvpn.inc");

function retrieveMetaData($url) {
	if (!$url)
		return;

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_FAILONERROR, true);
	curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);
	curl_setopt($curl, CURLOPT_TIMEOUT, 30);
	$metadata = curl_exec($curl);
	curl_close($curl);

	return($metadata);
}

function retrieveSSHKey() {
	global $g;

	if ($g['default-config-flavor'] == "openstack-csm") {
		$url = "http://169.254.169.254/latest/meta-data/public-ipv4";
	} else {
		$url = "http://169.254.169.254/latest/meta-data/public-keys/0/openssh-key";
	}
	return(retrieveMetaData($url));
}

function retrieveUserData() {
	$url = "http://169.254.169.254/latest/user-data/";
	$user_data = retrieveMetaData($url);

	if (!$user_data)
		return;

	/* userdata is formatted like this:
	   foo1=bar1:foo2=bar2:...:fooN=barN
	   what, were you raised in a barN? */

	$kv_pairs = explode(':', $user_data);
	foreach ($kv_pairs as $pair) {
		list($key, $value) = explode("=", $pair, 2);
		$ud[$key] = $value;
	}

	return($ud);
}

function retrievePublicIP() {
	$wanintf = get_real_wan_interface();
	$macaddr = get_interface_mac($wanintf);
	if (!$macaddr)
		return;

	$url = "http://169.254.169.254/latest/meta-data/network/interfaces/macs/$macaddr/public-ipv4s";
	$public_ipv4 = retrieveMetaData($url);

	if (is_ipaddrv4($public_ipv4)) {
		$natipfile = "/var/db/natted_{$wanintf}_ip";
		file_put_contents($natipfile, $public_ipv4);
		return($public_ipv4);
	}

	return;
}

function generateRandomPassword($length = 15) {
	/* get some random bytes. use them as offsets into the space of
           printable ascii characters. 32-126 is the printable characters.
	   Omit 32 itself since it might be confusing if there is a space
	   in the password.
	*/

	$range_size = 126 - 33 + 1;
	$random_bytes = str_split(openssl_random_pseudo_bytes($length));

	for ($i = 0; $i < $length; $i++) {

		$offset = ord($random_bytes[$i]) % $range_size;
		$password .= chr(33 + $offset);

	}

	return $password;
}

function addCA() {
	global $config;
	if (!is_array($config['ca']))
		$config['ca'] = array();

	$a_ca = &$config['ca'];

	$ca_cfg['keylen']     = 2048;
	$ca_cfg['digest_alg'] = 'sha256';
	$ca_cfg['lifetime']   = 3650;

	$dn['countryName']         = 'US';
	$dn['stateOrProvinceName'] = 'TX';
	$dn['localityName']        = 'Austin';
	$dn['organizationName']    = 'Netgate VPN';
	$dn['emailAddress']        = 'email';
	$dn['commonName']          = 'Netgate VPN CA';

	$ca = array();
	$ca['refid'] = uniqid();
	$ca['descr'] = 'Netgate Auto VPN CA';

	if (!ca_create($ca, $ca_cfg['keylen'], $ca_cfg['lifetime'], $dn, $ca_cfg['digest_alg'])) {
		$ssl_errs = 0;
		while ($ssl_err = openssl_error_string()) {
			$ssl_errs++;
			$last_ssl_err = $ssl_err;
		}
		if ($ssl_errs) {
			echo "Errors creating CA cert: $last_ssl_err\n";
			return;
		}
	}

	$a_ca[] = $ca;
	return($ca['refid']);
}

function addServerCert($caref) {
	global $config;

	if (!is_array($config['cert']))
	$config['cert'] = array();

	$a_cert = &$config['cert'];

	$cert_cfg['keylen'] = 2048;
	$cert_cfg['csr_keylen'] = 2048;
	$cert_cfg['digest_alg'] = 'sha256';
	$cert_cfg['type'] = 'server';
	$cert_cfg['lifetime'] = 3650;

	$dn['countryName']         = 'US';
	$dn['stateOrProvinceName'] = 'TX';
	$dn['localityName']        = 'Austin';
	$dn['organizationName']    = 'Netgate VPN';
	$dn['emailAddress']        = 'email';
	$dn['commonName']          = 'Netgate VPN Server';

	$cert = array();
	$cert['refid'] = uniqid();
	$cert['descr'] = 'Netgate Auto VPN Server Cert';

	if (!cert_create($cert, $caref, $cert_cfg['keylen'],
	    $cert_cfg['lifetime'], $dn, $cert_cfg['type'], $cert_cfg['digest_alg'])) {
		$ssl_errs = 0;
		while ($ssl_err = openssl_error_string()) {
			$ssl_errs++;
			$last_ssl_err = $ssl_err;
		}
		if ($ssl_errs) {
			echo "Errors creating cert: $last_ssl_err\n";
			return;
		}
	}

	$a_cert[] = $cert;
	return($cert['refid']);
}

function addOpenVPNServer() {
	global $config;

	if (!is_array($config['openvpn']['openvpn-server']))
		$config['openvpn']['openvpn-server'] = array();

	$a_server = &$config['openvpn']['openvpn-server'];

	/* don't do anything if it's previously been done */
	if (isset($a_server[0]['description']) &&
	    ($a_server[0]['description'] == 'Netgate Auto Remote Access VPN'))
		return;

	$server['vpnid'] = 0;
	$server['disable'] = '';
	$server['mode'] = 'server_user';
	$server['authmode'] = 'Local Database';
	$server['protocol'] = 'UDP';
	$server['dev_mode'] = 'tun';
	$server['interface'] = 'wan';
	$server['local_port'] = 1194;
	$server['description'] = 'Netgate Auto Remote Access VPN';
	$server['tlsauth_enable'] = 'no';
	$server['autotls_enable'] = 'no';
	$server['caref'] = addCA();
	if (!isset($server['caref']))
		return;
	$server['certref'] = addServerCert($server['caref']);
	if (!isset($server['certref']))
		return;
	$server['dh_length'] = 1024;
	$server['crypto'] = 'AES-128-CBC';
	$server['engine'] = 'none';
	$server['cert_depth'] = 1;
	$server['tunnel_network'] = '172.24.42.0/24';
	$server['gwredir'] = 'yes';
	$server['compression']  = 'yes';
	$server['duplicate_cn'] = true;
	$server['topology_subnet'] = 'yes';
	$server['custom_options'] = 'push "route-ipv6 0::0/1 vpn_gateway";push "route-ipv6 8000::0/1 vpn_gateway";';
	$server['tunnel_networkv6'] = 'fd6f:826b:ed1e::0/64';
	$server['dns_server_enable'] = true;
	$server['dns_server1'] = '172.24.42.1';

	$a_server[] = $server;

	openvpn_resync('server', $server);
	return;
}

function configureMgmtNetRules($mgmtnet) {
	global $config;

	/*
	   Since the EC2 VM must be managed over the internet, access to SSH
	   & web is open to the outside. By default it is open to anywhere
	   because it is unknown at image creation time where the user will be
	   coming from. User can pass in a management network to allow in
	   the user data field that will be used to replace 'any' in the
	   default filter rules.

	   find rules with '_replace_src_with_mgmtnet_' in the description and
	   replace the source network with $mgmtnet

	   could also add a tag to look for that indicates the destination
	   address (or other attributes) should be substituted
	*/

	$src_addr_tag = '_replace_src_with_mgmtnet_';

	if (! (is_ipaddrv4($mgmtnet) || is_subnetv4($mgmtnet)) ) {
		echo "Invalid management subnet/address: $mgmtnet\n";
		return;
	}

	if (!is_array($config['filter']['rule']))
		$config['filter']['rule'] = array();
	$a_filter = &$config['filter']['rule'];

	foreach ($a_filter as &$rule) {
		$pos = strpos($rule['descr'], $src_addr_tag);
		if ($pos !== false) {
			unset($rule['source']['any']);
			$rule['source']['address'] = $mgmtnet;
			$rule['descr'] = str_replace($src_addr_tag, "", $rule['descr']);
		}
	}

	return(true);
}

function writeOpenVPNConfig($publicIP) {
	global $config, $g;

	/* check if the first openvpn server is the automatically generated
	   remote access VPN server before writing the config */
	if (!is_array($config['openvpn']['openvpn-server']) ||
	    !isset($config['openvpn']['openvpn-server'][0]['description']) ||
	    ($config['openvpn']['openvpn-server'][0]['description'] !=
	     'Netgate Auto Remote Access VPN'))
		return;

	$cfgDir             = "/usr/local/libdata/vpn-profile";
	$ovpnCfgFile        = "remote-access-openvpn.ovpn";
	$cfgTemplateDir     = "/usr/local/share/{$g['product_name']}-openvpn_connect_profile";

	if (!file_exists($cfgDir))
		mkdir($cfgDir, 0755, true);

	/* read the template file and replace the placeholders */
	$newOvpnCfg = file_get_contents("$cfgTemplateDir/$ovpnCfgFile");
	if (!isset($newOvpnCfg))
		return;

	$newOvpnCfg = str_replace('__PUBLIC_IP__', $publicIP, $newOvpnCfg);
	$ca = $config['ca'][0]['crt'];
	if ($ca) {
		$newOvpnCfg = str_replace('__CA_CRT__', base64_decode($ca), $newOvpnCfg);
	}

	/* do not write a file if one of the fields was missing */
	if (!($publicIP && $ca))
		return;

	if (!file_exists("$cfgDir/$ovpnCfgFile") ||
	    (file_get_contents("$cfgDir/$ovpnCfgFile") !== $newOvpnCfg))
		file_put_contents("$cfgDir/$ovpnCfgFile", $newOvpnCfg);

	return;
}


function initialSystemConfig() {
	global $config, $g;

	/* admin user should exist already, exit if it doesnt */
	if (!(is_array($config['system']['user']) && isset($config['system']['user'][0]))) {
		echo "Didn't find user data in config. Exiting EC2 setup.\n";
		exit;
	}

	$a_users = &$config['system']['user'];

	$ec2_user = 'ec2-user';
	$ec2_id = -1;
	foreach ($a_users as $id => $user) {
		if ($user['name'] == $ec2_user) {
			$ec2_id = $id;
			break;
		}
	}

	# Create EC2 user when it doesn't exist
	if ($ec2_id == -1) {
		$new_user = array(
			'scope' => 'user',
			'descr' => 'EC2 User',
			'name' => $ec2_user,
			'uid' => $config['system']['nextuid']++
		);
		$ec2_id = count($a_users);
		$a_users[$ec2_id] = $new_user;
		/*
		 * Add user to groups so PHP can see the memberships properly
		 * or else the user's shell account does not get proper
		 * permissions (if applicable) See #5152.
		 */
		local_user_set_groups($new_user, array('admins'));
		local_user_set($new_user);
		/*
		 * Add user to groups again to ensure they are set everywhere,
		 * otherwise the user may not appear to be a member of the
		 * group. See commit:5372d26d9d25d751d16865ed9d46869d3b0ec5e1.
		 */
		local_user_set_groups($new_user, array('admins'));
		write_config("EC2 User created by ec2_setup");
	}

	$a_ec2_user = &$config['system']['user'][$ec2_id];

	/* get the administative SSH Key and add it to the config */
	if (!isset($a_ec2_user['authorizedkeys'])) {
		$ssh_key = retrieveSSHKey();
		if ($ssh_key) {
			echo "SSH Key retrieved: $ssh_key\n";
			$a_ec2_user['authorizedkeys'] = base64_encode($ssh_key);
		} else
			echo "Failed to retrieve an SSH key for administrative access\n";
	}

	/* get user metadata, set admin password if one was specified */
	$user_data = retrieveUserData();
	if ($user_data && isset($user_data['password']))
		$admin_password = $user_data['password'];
	else
		/* none specified, generate a random one */
		$admin_password = generateRandomPassword();

	if ($admin_password) {
		$pw_string = "***\n***\n";
		$pw_string .= "*** ec2-user password changed to: $admin_password\n";
		$pw_string .= "***\n***\n";
		local_user_set_password($a_ec2_user, $admin_password);
		file_put_contents("/etc/motd-passwd", $pw_string);
	} else {
		@unlink('/etc/motd-passwd');
		echo "No password generated for admin, keeping default password\n";
	}
	local_user_set($a_ec2_user);

	if ($g['default-config-flavor'] == "ec2" ||
	    $g['default-config-flavor'] == "ec2-ic") {
		/* add a disabled remote access OpenVPN server */
		addOpenVPNServer();
	}

	if (isset($user_data['mgmtnet']))
		configureMgmtNetRules($user_data['mgmtnet']);

	if (file_exists("/usr/local/pkg/sudo.inc") &&
	    is_array($config['installedpackages']['sudo']['config'][0]['row'])) {
		$a_row = &$config['installedpackages']['sudo']['config'][0]['row'];

		$row_id = -1;
		foreach ($a_row as $id => $row) {
			if ($row['username'] == "user:{$ec2_user}") {
				$row_id = $id;
				break;
			}
		}

		if ($row_id == -1) {
			$new_row = array(
				'username' => "user:{$ec2_user}",
				'runas' => 'user:root',
				'nopasswd' => 'ON',
				'cmdlist' => 'ALL'
			);
			$a_row[] = $new_row;
			write_config("Sudo configured for EC2 User");

			require_once("/usr/local/pkg/sudo.inc");
			sudo_write_config();
		}
	}

	unset($config['system']['doinitialsetup']);
	write_config("EC2 setup completed");
}

switch ($g['default-config-flavor']) {
case "ec2":
case "ec2-csm":
case "ec2-ic":
case "openstack-csm":
	if (isset($config['system']['doinitialsetup']))
		initialSystemConfig();
	break;
}

switch ($g['default-config-flavor']) {
case "ec2":
case "ec2-csm":
case "ec2-ic":
	$publicIP = retrievePublicIP();
	writeOpenVPNConfig($publicIP);
	break;
}

?>
