<?php

/*
 * net-snmp.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2017 Rubicon Communications, LLC (Netgate)
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
require_once("services.inc");
require_once("service-utils.inc");
require_once("pfsense-utils.inc");
require_once("certs.inc");

global $snmpd_config_file;
$snmpd_config_file = "/var/etc/netsnmpd.conf";
global $snmpd_users_file;
$snmpd_users_file = "/var/etc/netsnmpd-users.conf";
global $snmptrapd_config_file;
$snmptrapd_config_file = "/var/etc/netsnmptrapd.conf";
global $snmpd_cert_dir;
$snmpd_cert_dir = "/usr/local/share/snmp/tls/certs";
global $snmpd_certkey_dir;
$snmpd_certkey_dir = "/usr/local/share/snmp/tls/private";

function netsnmp_install_command() {
	global $config;
	/* Make some default settings/entries/users, if necessary */
	if (!is_array($config['installedpackages'])) {
		$config['installedpackages'] = array();
	}
	if (!is_array($config['installedpackages']['netsnmpusers'])) {
		$config['installedpackages']['netsnmpusers'] = array();
	}
	if (!is_array($config['installedpackages']['netsnmpusers']['config'])) {
		$config['installedpackages']['netsnmpusers']['config'] = array();
		/* A temp pseudo-random password for this default account. */
		$temp_password = bin2hex(openssl_random_pseudo_bytes(16));
		$config['installedpackages']['netsnmpusers']['config'][] = array (
			'username' => 'manager',
			'snmpv3_type' => 'user',
			'description' => 'manager',
			'rorwuser' => 'rwuser',
			'baseoid' => '',
			'authtype' => 'SHA',
			'password' => $temp_password,
			'privacyprotocol' => 'AES',
			'passphrase' => $temp_password,
			'minseclevusm' => 'noauth'
		);
	}

	if (!is_array($config['installedpackages']['netsnmptrapgen'])) {
		$config['installedpackages']['netsnmptrapgen'] = array();
	}
	if (!is_array($config['installedpackages']['netsnmptrapgen']['config'])) {
		$config['installedpackages']['netsnmptrapgen']['config'] = array();
	}
	if (!is_array($config['installedpackages']['netsnmptrapgen']['config'][0])) {
		$config['installedpackages']['netsnmptrapgen']['config'][0] = array();
	}
	if (empty($config['installedpackages']['netsnmptrapgen']['config'][0]['iquerysecname'])) {
		$config['installedpackages']['netsnmptrapgen']['config'][0]['iquerysecname'] = 'manager';
	}
}

/***************************************************************************
 *
 * Selection Source Functions
 *
 ***************************************************************************/

/* Get a list of CA Certificates for use with trustCert */
function netsnmp_get_ca_certs() {
	global $config;
	$ca_arr = array();
	$ca_arr[] = array('refid' => 'none', 'descr' => 'None');
	$ca_arr[] = array('refid' => 'self', 'descr' => 'Self-Signed Certificate');

	if (is_array($config['ca'])) {
		foreach ($config['ca'] as $ca) {
			$ca_arr[] = array('refid' => $ca['refid'], 'descr' => $ca['descr']);
		}
	}
	return $ca_arr;
}

/* Get a list of certificates */
function netsnmp_get_certs() {
	global $config;
	$cert_arr = array();
	$cert_arr[] = array('refid' => 'none', 'descr' => 'None');

	if (is_array($config['cert'])) {
		foreach ($config['cert'] as $cert) {
			$cert_arr[] = array('refid' => $cert['refid'], 'descr' => $cert['descr']);
		}
	}
	return $cert_arr;
}

/* Get a list of SNMPv3 User names */
function netsnmp_get_usernames() {
	global $config;
	if (!is_array($config['installedpackages'])) {
		$config['installedpackages'] = array();
	}
	if (!is_array($config['installedpackages']['netsnmpusers'])) {
		$config['installedpackages']['netsnmpusers'] = array();
	}
	$users = &$config['installedpackages']['netsnmpusers']['config'];
	$usernames = array();

	if (is_array($users)) {
		foreach ($users as $user) {
			/* We are only interested in the names */
			$usernames[] = array('refid' => $user['username'], 'descr' => $user['username']);
		}
	}
	return $usernames;
}

/***************************************************************************
 *
 * Support functions
 *
 ***************************************************************************/

/* Return a certificate fingerprint formatted in the way that snmpd wants. */
function netsnmp_cert_get_fingerprint($cert) {
	return strtoupper(preg_replace('/(..)(?!$)/', '\1:', openssl_x509_fingerprint($cert, 'SHA1')));
}
function netsnmp_cert_get_keyid($cert) {
	$details = openssl_x509_parse($cert);
	if (is_array($details['extensions'])) {
		return $details['extensions']['subjectKeyIdentifier'];
	} else {
		return "";
	}
}

/***************************************************************************
 *
 * Daemon Configuration for snmpd
 *
 ***************************************************************************/

/* Write snmpd config file */
function netsnmp_resync() {
	global $config, $g, $snmpd_config_file, $snmpd_users_file, $snmpd_cert_dir, $snmpd_certkey_dir;

	/* Global Settings tab */
	if (!is_array($config['installedpackages'])) {
		$config['installedpackages'] = array();
	}
	if (!is_array($config['installedpackages']['netsnmp'])) {
		$config['installedpackages']['netsnmp'] = array();
	}
	if (!is_array($config['installedpackages']['netsnmp']['config'])) {
		$config['installedpackages']['netsnmp']['config'] = array();
	}
	if (!is_array($config['installedpackages']['netsnmp']['config'][0])) {
		$config['installedpackages']['netsnmp']['config'][0] = array();
	}
	$nsettings = &$config['installedpackages']['netsnmp']['config'][0];

	/* If the service isn't enabled, nothing to do. Stop the service and return. */
	if ($nsettings['enable_snmpd'] != "on") {
		stop_service('net-snmpd');
		return;
	}

	$snmpd_config = "";
	$tls_active = false;

	/* The binding / agent addresses control where and how snmpd listens for incoming connections. */
	$agentaddresses = array();
	if (!is_array($nsettings['row'])) {
		$nsettings['row'] = array();
	}
	foreach ($nsettings['row'] as $agent) {
		$aa = "";
		/* At a minimum, a port must be supplied. UDP is assumed in that case */
		if (!empty($agent['snmpport'])) {
			$aa = $agent['snmpport'];
		}
		/* An address or hostname is optional but needs to be before the port */
		if (!empty($agent['ipaddress'])) {
			$aa = "{$agent['ipaddress']}:{$aa}";
		}
		/* Transport is optional (UDP is assumed) */
		if (!empty($agent['transport'])) {
			$aa = "{$agent['transport']}:{$aa}";
		}
		/* If a TLS mode is active, note it for later. */
		if (strpos($agent['transport'], 'tls') !== false) {
			$tls_active = true;
		}
		/* Only keep this entry if it contains data */
		if (!empty($aa)) {
			$agentaddresses[] = $aa;
		}
	}

	/* Agent address list is comma-separated, no spaces: a:b:c,d:e:f,g:h:i */
	if (!empty($agentaddresses)) {
		$snmpd_config .= "agentaddress " . implode(',', $agentaddresses) . "\n";
	}

	/* If a manual string engine ID type is set, use the specified string, otherwise use the type directly. */
	if (($nsettings['engineidtype'] != 'string') && !empty($nsettings['engineidtype'])) {
		$snmpd_config .= "engineIDType {$nsettings['engineidtype']}\n";
	} elseif (($nsettings['engineidtype'] == 'string') && !empty($nsettings['engineid'])) {
		$snmpd_config .= "engineID {$nsettings['engineid']}\n";
	}

	if ($nsettings['usetransportprefix'] == "on") {
		$snmpd_config .= "[snmp] tsmUseTransportPrefix yes\n";
	} else {
		$snmpd_config .= "[snmp] tsmUseTransportPrefix no\n";
	}

	if (!empty($nsettings['maxgetbulkrepeats'])) {
		$snmpd_config .= "maxGetbulkRepeats {$nsettings['maxgetbulkrepeats']}\n";
	}
	if (!empty($nsettings['maxgetbulkresponses'])) {
		$snmpd_config .= "maxGetbulkResponses {$nsettings['maxgetbulkresponses']}\n";
	}

	/* Dump custom options into the configuration as-is */
	if (!empty($nsettings['custom_options'])) {
		$snmpd_config .= "\n" . str_replace("\r", "", base64_decode($nsettings['custom_options'])) . "\n";
	}

/*
	TODO: Implement certificate backend, see
	http://www.net-snmp.org/wiki/index.php/Using_DTLS
*/
	if ($tls_active) {
		if (!is_dir($snmpd_cert_dir)) {
			safe_mkdir($snmpd_cert_dir);
		} else {
			/* Cleanup old certificates. */
			array_map('unlink', glob("{$snmpd_cert_dir}/*.crt"));
		}
		if (!is_dir($snmpd_certkey_dir)) {
			safe_mkdir($snmpd_certkey_dir);
		} else {
			/* Cleanup old certificate keys. */
			array_map('unlink', glob("{$snmpd_cert_dir}/*.key"));
		}

		/* Write CA */
		$ca = lookup_ca($nsettings['ca_cert']);
		if ($ca) {
			file_put_contents("{$snmpd_cert_dir}/ca.crt", base64_decode($ca['crt']));
			chmod($snmpd_cert_dir . '/ca.crt', 0660);
			//$snmpd_config .= "[snmp] trustCert " . netsnmp_cert_get_keyid(base64_decode($ca['crt'])) . "\n";
			$snmpd_config .= "[snmp] trustCert ca\n";
		}
		/* Write server certificate */
		$cert = lookup_cert($nsettings['server_cert']);
		if ($cert) {
			file_put_contents("{$snmpd_cert_dir}/snmpd.crt", base64_decode($cert['crt']));
			chmod($snmpd_cert_dir . '/snmpd.crt', 0660);
			file_put_contents("{$snmpd_certkey_dir}/snmpd.key", base64_decode($cert['prv']));
			chmod($snmpd_certkey_dir . '/snmpd.key', 0660);
			//$snmpd_config .= "[snmp] localCert " . netsnmp_cert_get_keyid(base64_decode($cert['crt'])) . "\n";
			$snmpd_config .= "[snmp] localCert snmpd\n";
		}
	}

	/* Host Information tab */
	if (!is_array($config['installedpackages']['netsnmphostinfo'])) {
		$config['installedpackages']['netsnmphostinfo'] = array();
	}
	if (!is_array($config['installedpackages']['netsnmphostinfo']['config'])) {
		$config['installedpackages']['netsnmphostinfo']['config'] = array();
	}
	if (!is_array($config['installedpackages']['netsnmphostinfo']['config'][0])) {
		$config['installedpackages']['netsnmphostinfo']['config'][0] = array();
	}
	$nhisettings = &$config['installedpackages']['netsnmphostinfo']['config'][0];

	if (!empty($nhisettings['syslocation'])) {
		$snmpd_config .= "sysLocation {$nhisettings['syslocation']}\n";
	}
	if (!empty($nhisettings['syscontact'])) {
		$snmpd_config .= "sysContact {$nhisettings['syscontact']}\n";
	}
	if (!empty($nhisettings['sysname'])) {
		$snmpd_config .= "sysName {$nhisettings['sysname']}\n";
	}
	if (!empty($nhisettings['sysdescr'])) {
		$snmpd_config .= "sysDescr {$nhisettings['sysdescr']}\n";
	}

	if (!empty($nhisettings['interface_fadeout'])) {
		$snmpd_config .= "interface_fadeout {$nhisettings['interface_fadeout']}\n";
	}
	if ($nhisettings['interface_replace_old'] == "yes") {
		$snmpd_config .= "interface_replace_old yes\n";
	} else {
		$snmpd_config .= "interface_replace_old no\n";
	}

	if (empty($nhisettings['disk_usage_percent'])) {
		$nhisettings['disk_usage_percent'] = "20";
	}
	/* Ignore /dev and its copies as they are not filesystems that need monitored */
	$snmpd_config .= "ignoreDisk /dev\n";
	$snmpd_config .= "ignoreDisk /var/dhcpd/dev\n";
	/* Monitor all disks that snmpd can find */
	$snmpd_config .= "includeAllDisks {$nhisettings['disk_usage_percent']}%\n";

	/* Load average settings each require the previous interval to be set,
	   otherwise the meaning is ambigious.
	   For example: load_1 [ load_5 [ load_15 ] ]
	 */
	if ($nhisettings['load_1']) {
		$snmpd_config .= "load {$nhisettings['load_1']}";
		if ($nhisettings['load_5']) {
			$snmpd_config .= " {$nhisettings['load_5']}";
			if ($nhisettings['load_15']) {
				$snmpd_config .= " {$nhisettings['load_15']}";
			}
		}
		$snmpd_config .= "\n";
	}

	/* Extended commands (formerly exec) and extendfix */
	/* TODO: Check if the parameters need quoting */
	if (!is_array($nhisettings['row'])) {
		$nhisettings['row'] = array();
	}
	foreach ($nhisettings['row'] as $extend) {
		$extend_parameters = "{$extend['ext_miboid']} " .
					"{$extend['ext_name']} " .
					"{$extend['ext_prog']} " .
					"{$extend['ext_args']} ";
		if (!empty(trim($extend_parameters))) {
			$snmpd_config .= "{$extend['ext_type']} " .
					"{$extend_parameters}\n";
		}
	}

	/* SNMPv3 Users */
	if (!is_array($config['installedpackages']['netsnmpusers'])) {
		$config['installedpackages']['netsnmpusers'] = array();
	}
	if (!is_array($config['installedpackages']['netsnmpusers']['config'])) {
		$config['installedpackages']['netsnmpusers']['config'] = array();
	}
	$nusettings = &$config['installedpackages']['netsnmpusers']['config'];

	$snmpd_users_config = "";
	if (!is_array($nusettings)) {
		$nusettings = array();
	}
	foreach ($nusettings as $user) {
		/* To make it easier to define a user in one pass, the user settings have both user and cert settings */
		if (($user['snmpv3_type'] == "user") || ($user['snmpv3_type'] == "both")) {
			/* Create a user entry in the USM table. This only defines the user and its authentication method/transport */
			$createuser = "createUser \"{$user['username']}\" " .
					"{$user['authtype']} " .
					"\"" . str_replace('"', '\"', $user['password']) . "\"";
			/* Only add the privacy protocol if the user needs one */
			if ($user['privacyprotocol'] != "none") {
				if (empty($user['passphrase'])) {
					$user['passphrase'] = $user['password'];
				}
				$createuser .= " {$user['privacyprotocol']} " .
						"\"" . str_replace('"', '\"', $user['passphrase']) . "\"";
			}
			/* createUser directives go into a separate configuration file that gets changed by snmpd */
			$snmpd_users_config .= "{$createuser}\n";

			/* Each SNMPv3 user also needs an rouser or rwuser line to define its access */
			$snmpd_config .= "{$user['rorwuser']} " .
					 "-s usm " .
					 "\"{$user['username']}\"";
			if ($user['minseclevusm'] != "none") {
				$snmpd_config .= " {$user['minseclevusm']}";
				if (!empty($user['baseoid'])) {
					$snmpd_config .= " {$user['baseoid']}";
				}
			}
			$snmpd_config .= "\n";
		}
		if (($user['snmpv3_type'] == "cert") || ($user['snmpv3_type'] == "both")) {
			/* Defines a mapping of a certificate parameter to an SNMPv3 username (defined previously)
			   This requires the fingerprint of the certificate in order to identify it specifically,
			   and then the option defines which cert field to use, or a manual override to map to a
			   specific user directly.
			 */

			// netsnmp_cert_get_fingerprint(base64_decode($cert['crt']));
			/* Write user certificate */
			$cert = lookup_cert($user['user_cert']);
			if ($cert) {
				file_put_contents("{$snmpd_cert_dir}/{$user['username']}.crt", base64_decode($cert['crt']));
				chmod("{$snmpd_cert_dir}/{$user['username']}.crt", 0660);
				file_put_contents("{$snmpd_certkey_dir}/{$user['username']}.key", base64_decode($cert['prv']));
				chmod("{$snmpd_certkey_dir}/{$user['username']}.key", 0660);

				$snmpd_config .= "certSecName " .
						 "{$user['priority']} " .
						 "{$user['username']} " .
						 "--{$user['options']}";
				/* If SecName was chosen, define the username directly */
				if ($user['options'] == "sn") {
					$snmpd_config .= " {$user['secname']}";
				}
				$snmpd_config .= "\n";
			}

			/* Each SNMPv3 user also needs an rouser or rwuser line to define its access */
			$snmpd_config .= "{$user['rorwuser']} " .
					 "-s tsm " .
					 "\"{$user['username']}\"";
			if ($user['minseclevtsm'] != "none") {
				$snmpd_config .= " {$user['minseclevtsm']}";
				if (!empty($user['baseoid'])) {
					$snmpd_config .= " {$user['baseoid']}";
				}
			}
			$snmpd_config .= "\n";
		}
	}


	/* Don't bother to write the users config if there are no users */
	if (!empty($snmpd_users_config)) {
		/* Write the createUser configuration file */
		file_put_contents($snmpd_users_file, $snmpd_users_config);
	}

	/* SNMPv1 & SNMPv2c Communities */
	if (!is_array($config['installedpackages']['netsnmpcommunities'])) {
		$config['installedpackages']['netsnmpcommunities'] = array();
	}
	if (!is_array($config['installedpackages']['netsnmpcommunities']['config'])) {
		$config['installedpackages']['netsnmpcommunities']['config'] = array();
	}
	$ncsettings = &$config['installedpackages']['netsnmpcommunities']['config'];
	if (!is_array($ncsettings)) {
		$ncsettings = array();
	}
	foreach ($ncsettings as $community) {
		/* Build the parameters first, add the type later as it could result in repetition. */
		$community_line = $community['community'];

		/* An OID restriction can only be specified if a source is also defined, use source "default" to set w/o network source */
		if (!empty($community['source'])) {
			/* TODO: Maybe some sanity checking to ensure that the source type matches the address family */
			$community_line .= " {$community['source']}";
			if (!empty($community['baseoid'])) {
				$community_line .= " {$community['baseoid']}";
			}
		}

		/* Build the community type based on the address family, if both are needed, build both lines. */
		if (($community['commtype'] == "IPv4") || ($community['commtype'] == "both")) {
			$snmpd_config .= "{$community['rorwcommunity']} {$community_line}\n";
		}
		if (($community['commtype'] == "IPv6") || ($community['commtype'] == "both")) {
			$snmpd_config .= "{$community['rorwcommunity']}6 {$community_line}\n";
		}
	}

	/* Trap Generation */
	if (!is_array($config['installedpackages']['netsnmptrapgen'])) {
		$config['installedpackages']['netsnmptrapgen'] = array();
	}
	if (!is_array($config['installedpackages']['netsnmptrapgen']['config'])) {
		$config['installedpackages']['netsnmptrapgen']['config'] = array();
	}
	if (!is_array($config['installedpackages']['netsnmptrapgen']['config'][0])) {
		$config['installedpackages']['netsnmptrapgen']['config'][0] = array();
	}
	$ntgsettings = &$config['installedpackages']['netsnmptrapgen']['config'][0];

	if (!empty($ntgsettings['trapcommunity'])) {
		$snmpd_config .= "trapcommunity {$ntgsettings['trapcommunity']}\n";
	}

	if (!empty($ntgsettings['authtrapenable'])) {
		$snmpd_config .= "authtrapenable {$ntgsettings['authtrapenable']}\n";
	}

	if (!empty($ntgsettings['linkupdownnotifications'])) {
		$snmpd_config .= "linkUpDownNotifications {$ntgsettings['linkupdownnotifications']}\n";
	}

	if (!empty($ntgsettings['defaultmonitors'])) {
		$snmpd_config .= "defaultMonitors {$ntgsettings['defaultmonitors']}\n";
	}

	if (!empty($ntgsettings['iquerysecname'])) {
		$snmpd_config .= "iquerySecName \"{$ntgsettings['iquerysecname']}\"\n";
		$snmpd_config .= "agentSecName \"{$ntgsettings['iquerysecname']}\"\n";
	}

	if (!empty($ntgsettings['v1trapaddress'])) {
		$snmpd_config .= "v1trapaddress {$ntgsettings['v1trapaddress']}\n";
	}

	/* Support up to three SNMPv1 Trap Sinks */
	if (!empty($ntgsettings['trapsink1_host'])) {
		$snmpd_config .= "trapsink {$ntgsettings['trapsink1_host']}";
		if (!empty($ntgsettings['trapsink1_community'])) {
			$snmpd_config .= " {$ntgsettings['trapsink1_community']}";
			if (!empty($ntgsettings['trapsink1_port'])) {
				$snmpd_config .= " {$ntgsettings['trapsink1_port']}";
			}
		}
		$snmpd_config .= "\n";
	}
	if (!empty($ntgsettings['trapsink3_host'])) {
		$snmpd_config .= "trapsink {$ntgsettings['trapsink3_host']}";
		if (!empty($ntgsettings['trapsink2_community'])) {
			$snmpd_config .= " {$ntgsettings['trapsink2_community']}";
			if (!empty($ntgsettings['trapsink2_port'])) {
				$snmpd_config .= " {$ntgsettings['trapsink2_port']}";
			}
		}
		$snmpd_config .= "\n";
	}
	if (!empty($ntgsettings['trapsink3_host'])) {
		$snmpd_config .= "trapsink {$ntgsettings['trapsink3_host']}";
		if (!empty($ntgsettings['trapsink3_community'])) {
			$snmpd_config .= " {$ntgsettings['trapsink3_community']}";
			if (!empty($ntgsettings['trapsink1_port'])) {
				$snmpd_config .= " {$ntgsettings['trapsink3_port']}";
			}
		}
		$snmpd_config .= "\n";
	}

	/* Support up to three SNMPv2c/3 Trap Sinks */
	if (!empty($ntgsettings['trap2sink1_host'])) {
		$snmpd_config .= "trap2sink {$ntgsettings['trap2sink1_host']}";
		if (!empty($ntgsettings['trap2sink1_community'])) {
			$snmpd_config .= " {$ntgsettings['trap2sink1_community']}";
			if (!empty($ntgsettings['trap2sink1_port'])) {
				$snmpd_config .= " {$ntgsettings['trap2sink1_port']}";
			}
		}
		$snmpd_config .= "\n";
	}
	if (!empty($ntgsettings['trap2sink2_host'])) {
		$snmpd_config .= "trap2sink {$ntgsettings['trap2sink2_host']}";
		if (!empty($ntgsettings['trap2sink2_community'])) {
			$snmpd_config .= " {$ntgsettings['trap2sink2_community']}";
			if (!empty($ntgsettings['trap2sink2_port'])) {
				$snmpd_config .= " {$ntgsettings['trap2sink2_port']}";
			}
		}
		$snmpd_config .= "\n";
	}
	if (!empty($ntgsettings['trap2sink3_host'])) {
		$snmpd_config .= "trap2sink {$ntgsettings['trap2sink3_host']}";
		if (!empty($ntgsettings['trap2sink3_community'])) {
			$snmpd_config .= " {$ntgsettings['trap2sink3_community']}";
			if (!empty($ntgsettings['trap2sink3_port'])) {
				$snmpd_config .= " {$ntgsettings['trap2sink3_port']}";
			}
		}
		$snmpd_config .= "\n";
	}

	/* Support up to three SNMPv2c/3 Inform notification hosts */
	if (!empty($ntgsettings['informsink1_host'])) {
		$snmpd_config .= "informsink {$ntgsettings['informsink1_host']}";
		if (!empty($ntgsettings['informsink1_community'])) {
			$snmpd_config .= " {$ntgsettings['informsink1_community']}";
			if (!empty($ntgsettings['informsink1_port'])) {
				$snmpd_config .= " {$ntgsettings['informsink1_port']}";
			}
		}
		$snmpd_config .= "\n";
	}
	if (!empty($ntgsettings['informsink2_host'])) {
		$snmpd_config .= "informsink {$ntgsettings['informsink2_host']}";
		if (!empty($ntgsettings['informsink2_community'])) {
			$snmpd_config .= " {$ntgsettings['informsink2_community']}";
			if (!empty($ntgsettings['informsink2_port'])) {
				$snmpd_config .= " {$ntgsettings['informsink2_port']}";
			}
		}
		$snmpd_config .= "\n";
	}
	if (!empty($ntgsettings['informsink3_host'])) {
		$snmpd_config .= "informsink {$ntgsettings['informsink3_host']}";
		if (!empty($ntgsettings['informsink3_community'])) {
			$snmpd_config .= " {$ntgsettings['informsink3_community']}";
			if (!empty($ntgsettings['informsink3_port'])) {
				$snmpd_config .= " {$ntgsettings['informsink3_port']}";
			}
		}
		$snmpd_config .= "\n";
	}

	/* 'monitor' entries define conditions to trigger traps. The control is a rowhelper, so loop through its entries. */
	if (!is_array($ntgsettings['row'])) {
		$ntgsettings['row'] = array();
	}
	foreach ($ntgsettings['row'] as $monitor) {
		if (empty($monitor['monitor_name'])) {
			continue;
		}
		$snmpd_config .= "monitor {$monitor['monitor_options']} " .
				 "{$monitor['monitor_name']} " .
				 "{$monitor['monitor_expression']} " .
				 "{$monitor['monitor_notificationevent']} " .
				 "{$monitor['monitor_setevent']}\n";
	}

	/* Running snmptrapd requires snmpd to run as an agentx master */
	$snmpd_config .= "master agentx\n";

	/* Write the configuration file */
	file_put_contents($snmpd_config_file, $snmpd_config);

	/* When stopping the daemon, use the PID file to locate and kill the process */
	$rc_file_stop = <<<EOF
echo "Stopping net-snmpd..."
if [ -e /var/run/net_snmpd.pid ]; then
	/bin/kill -9 `/bin/cat /var/run/net_snmpd.pid`
	/bin/rm -f /var/run/net_snmpd.pid
fi
EOF;

	/* When starting the daemon, reap any leftover processes and then start */
	$rc_file_start = <<<EOF
echo "Starting net-snmpd..."
# Ensure no other copies of the daemons are running or it breaks.
/usr/bin/killall -9 snmpd 2>/dev/null
sleep 1

/usr/local/sbin/snmpd -LF 0-4 d \
			-p /var/run/net_snmpd.pid \
			-M /usr/share/snmp/mibs/:/usr/local/share/snmp/mibs \
			-C \
			-c {$snmpd_config_file},{$snmpd_users_file}
EOF;
	write_rcfile(array(
			"file" => "net-snmpd.sh",
			"start" => $rc_file_start,
			"stop" =>  $rc_file_stop
		)
	);

	/* Restart the service with the new configuration. */
	restart_service('net-snmpd');

	if (file_exists("{$g['tmp_path']}/.rc.start_packages.running")) {
		netsnmp_resync_snmptrapd();
	}
}

/***************************************************************************
 *
 * Daemon Configuration for snmptrapd
 *
 ***************************************************************************/

/* Write snmptrapd config file and (re)start the daemon */
function netsnmp_resync_snmptrapd() {
	global $config, $snmptrapd_config_file;
	if (!is_array($config['installedpackages'])) {
		$config['installedpackages'] = array();
	}
	if (!is_array($config['installedpackages']['netsnmptrapd'])) {
		$config['installedpackages']['netsnmptrapd'] = array();
	}
	if (!is_array($config['installedpackages']['netsnmptrapd']['config'])) {
		$config['installedpackages']['netsnmptrapd']['config'] = array();
	}
	if (!is_array($config['installedpackages']['netsnmptrapd']['config'][0])) {
		$config['installedpackages']['netsnmptrapd']['config'][0] = array();
	}
	$nsettings = &$config['installedpackages']['netsnmptrapd']['config'][0];

	/* If snmptrapd is not enabled, then stop the service and do not write the config */
	if ($nsettings['enable_snmptrapd'] != "on") {
		stop_service('net-snmptrapd');
		return;
	}

	$snmptrapd_config = "";
	$snmptrapd_daemon_options = "";

	/* The protocol and interface binding for snmptrapd works the same as snmpd */
	$agentaddresses = array();
	if (!is_array($nsettings['row'])) {
		$nsettings['row'] = array();
	}
	foreach ($nsettings['row'] as $agent) {
		$aa = "";
		/* At a minimum, a port must be supplied. UDP is assumed in that case */
		if (!empty($agent['snmpport'])) {
			$aa = $agent['snmpport'];
		}
		/* An address or hostname is optional but needs to be before the port */
		if (!empty($agent['ipaddress'])) {
			$aa = "{$agent['ipaddress']}:{$aa}";
		}
		/* Transport is optional (UDP is assumed) */
		if (!empty($agent['transport'])) {
			$aa = "{$agent['transport']}:{$aa}";
		}
		/* Only keep this entry if it contains data */
		if (!empty($aa)) {
			$agentaddresses[] = $aa;
		}
	}

	/* Agent address list is comma-separated, no spaces: a:b:c,d:e:f,g:h:i */
	if (!empty($agentaddresses)) {
		$snmptrapd_config .= "agentaddress " . implode(',', $agentaddresses) . "\n";
	}

	if (!empty($nsettings['donotretainnotificationlogs'])) {
		$snmptrapd_config .= "doNotRetainNotificationLogs {$nsettings['donotretainnotificationlogs']}\n";
	}

	if (!empty($nsettings['donotlogtraps'])) {
		$snmptrapd_config .= "doNotLogTraps {$nsettings['donotlogtraps']}\n";
	}

	if (!empty($nsettings['ignoreauthfailure'])) {
		$snmptrapd_config .= "ignoreAuthFailure {$nsettings['ignoreauthfailure']}\n";
	}

	if (!empty($nsettings['disableauthorization'])) {
		$snmptrapd_config .= "disableAuthorization {$nsettings['disableauthorization']}\n";
	}

	/* Community-based Authentication for receiving traps */
	if (!is_array($config['installedpackages']['netsnmptrapdcommunities'])) {
		$config['installedpackages']['netsnmptrapdcommunities'] = array();
	}
	if (!is_array($config['installedpackages']['netsnmptrapdcommunities']['config'])) {
		$config['installedpackages']['netsnmptrapdcommunities']['config'] = array();
	}
	$ncsettings = &$config['installedpackages']['netsnmptrapdcommunities']['config'];
	if (!is_array($ncsettings)) {
		$ncsettings = array();
	}
	foreach ($ncsettings as $community) {
		/* If no type is specified, assume all types. */
		if (empty($community['type'])) {
			$community['type'] = "log,net,execute";
		}

		$snmptrapd_config .= "authCommunity {$community['type']} " .
					"\"{$community['authcommunity']}\"";

		/* Source restrictions are optional */
		if (!empty($community['source'])) {
			$snmptrapd_config .= " {$community['source']}";

			/* A base OID restriction is optional and can only be set when a source is also defined. */
			if (!empty($community['baseoid'])) {
				$snmptrapd_config .= " {$community['baseoid']}";
			}
		}
		$snmptrapd_config .= "\n";
	}

	/* User-based Authentication for receiving traps */
	if (!is_array($config['installedpackages']['netsnmptrapdusers'])) {
		$config['installedpackages']['netsnmptrapdusers'] = array();
	}
	if (!is_array($config['installedpackages']['netsnmptrapdusers']['config'])) {
		$config['installedpackages']['netsnmptrapdusers']['config'] = array();
	}
	$nusettings = &$config['installedpackages']['netsnmptrapdusers']['config'];
	if (!is_array($nusettings)) {
		$nusettings = array();
	}
	foreach ($nusettings as $user) {
		/* If no type is specified, assume all types. */
		if (empty($user['type'])) {
			$user['type'] = "log,net,execute";
		}

		$snmptrapd_config .= "authUser {$user['type']} " .
				 "-s {$user['secmodel']} " .
				 "\"{$user['username']}\"";

		/* Security level restrictions are optional */
		if ($user['level'] != "none") {
			$snmptrapd_config .= " {$user['level']}";

			/* A base OID restriction is optional and can only be set when a security level is also defined. */
			if (!empty($user['baseoid'])) {
				$snmptrapd_config .= " {$user['baseoid']}";
			}
		}
		$snmptrapd_config .= "\n";
	}

	/* Trap Format tab */
	if (!is_array($config['installedpackages']['netsnmptrapdformats'])) {
		$config['installedpackages']['netsnmptrapdformats'] = array();
	}
	if (!is_array($config['installedpackages']['netsnmptrapdformats']['config'])) {
		$config['installedpackages']['netsnmptrapdformats']['config'] = array();
	}
	if (!is_array($config['installedpackages']['netsnmptrapdformats']['config'][0])) {
		$config['installedpackages']['netsnmptrapdformats']['config'][0] = array();
	}
	$nfsettings = &$config['installedpackages']['netsnmptrapdformats']['config'][0];

	if (!empty($nsettings['format1'])) {
		$snmptrapd_config .= "format1 {$nsettings['format1']}\n";
	}

	if (!empty($nsettings['format2'])) {
		$snmptrapd_config .= "format2 {$nsettings['format2']}\n";
	}

	/* These formats are in a rowhelper to make the page less cluttered,
	   but there should only be one of each "destination" defined. */
	if (!is_array($nsettings['row'])) {
		$nsettings['row'] = array();
	}
	foreach ($nsettings['row'] as $format) {
		if (empty($format['destination'])) {
			continue;
		}
		$snmptrapd_config .= "{$format['destination']} " .
					"{$format['format_custom']}\n";
	}

	/* Trap Handles tab */
	if (!is_array($config['installedpackages']['netsnmptrapdtraphandles'])) {
		$config['installedpackages']['netsnmptrapdtraphandles'] = array();
	}
	if (!is_array($config['installedpackages']['netsnmptrapdtraphandles']['config'])) {
		$config['installedpackages']['netsnmptrapdtraphandles']['config'] = array();
	}
	$ntsettings = &$config['installedpackages']['netsnmptrapdtraphandles']['config'];

	/* Nothing fancy. Executes programs when a trap arrives, not much validation we can do. */
	if (!is_array($ntsettings)) {
		$ntsettings = array();
	}
	foreach ($ntsettings as $traphandle) {
		$snmptrapd_config .= "traphandle {$traphandle['destination']} " .
					"{$traphandle['program']} " .
					"{$traphandle['args']}\n";
	}

	/* Forwarding of traps to other hosts */
	if (!is_array($config['installedpackages']['netsnmptrapdforwards'])) {
		$config['installedpackages']['netsnmptrapdforwards'] = array();
	}
	if (!is_array($config['installedpackages']['netsnmptrapdforwards']['config'])) {
		$config['installedpackages']['netsnmptrapdforwards']['config'] = array();
	}
	$nwsettings = &$config['installedpackages']['netsnmptrapdforwards']['config'];
	if (!is_array($nwsettings)) {
		$nwsettings = array();
	}
	foreach ($nwsettings as $forward) {
		/* The forwarding addresses control where and how snmptrapd will deliver traps to another daemon. */
		$agentaddresses = array();
		if (is_array($forward['row'])) {
			$forward['row'] = array();
		}
		foreach ($forward['row'] as $agent) {
			$aa = "";
			/* At a minimum, a port must be supplied. UDP is assumed in that case */
			if (!empty($agent['snmpport'])) {
				$aa = $agent['snmpport'];
			}
			/* An address or hostname is optional but needs to be before the port */
			if (!empty($agent['ipaddress'])) {
				$aa = "{$agent['ipaddress']}:{$aa}";
			}
			/* Transport is optional (UDP is assumed) */
			if (!empty($agent['transport'])) {
				$aa = "{$agent['transport']}:{$aa}";
			}
			/* Only keep this entry if it contains data */
			if (!empty($aa)) {
				$agentaddresses[] = $aa;
			}
		}

		/* Only write the forward entry if there is a valid destination. */
		if (!empty($agentaddresses)) {
			/* Agent address list is comma-separated, no spaces: a:b:c,d:e:f,g:h:i */
			$snmptrapd_config .= "forward {$forward['destination']} " . implode(',', $agentaddresses) . "\n";
		}
	}

	if ($nsettings['snmptrapd_subagent']) {
		$snmptrapd_daemon_options .= "-X ";
	}

	/* Dump custom options into the configuration as-is */
	if (!empty($nsettings['custom_options'])) {
		$snmptrapd_config .= "\n" . str_replace("\r", "", base64_decode($nsettings['custom_options'])) . "\n";
	}

	file_put_contents($snmptrapd_config_file, $snmptrapd_config);

	/* When stopping the daemon, use the PID file to locate and kill the process */
	$rc_file_stop = <<<EOF
echo "Stopping net-snmptrapd..."
if [ -e /var/run/snmptrapd.pid ]; then
	/bin/kill -9 `/bin/cat /var/run/snmptrapd.pid`
	/bin/rm -f /var/run/snmptrapd.pid
fi
EOF;
	/* When starting the daemon, reap any leftover processes and then start */
	$rc_file_start = <<<EOF
echo "Starting net-snmptrapd..."
# Ensure no other copies of the daemons are running or it breaks.
/usr/bin/killall -9 snmptrapd 2>/dev/null
sleep 1

/usr/local/sbin/snmptrapd -p /var/run/snmptrapd.pid \
			-M /usr/share/snmp/mibs/:/usr/local/share/snmp/mibs \
			-C {$snmptrapd_daemon_options} \
			-c {$snmptrapd_config_file}
EOF;
	write_rcfile(array(
			"file" => "net-snmptrapd.sh",
			"start" => $rc_file_start,
			"stop" =>  $rc_file_stop
		)
	);

	/* Restart the service with the new configuration. */
	restart_service('net-snmptrapd');
}

/***************************************************************************
 *
 * Input validation section
 *
 ***************************************************************************/

/* snmpd - General tab */
function netsnmp_validate_general($post, &$input_errors) {
	global $config;
	if ($post['enable_snmpd'] != 'on') {
		return;
	}

	/* Interface binding:
		D/TLS blank or hostname.
		UDP/TCP blank, hostname, IPv4 addr
		UDP6/TCP6 blank, hostname, IPv6 addr
		 */
	$i = 0;
	$used_ports = array();
	while (isset($post['transport' . $i])) {
		$short_proto = "";
		switch ($post['transport' . $i]) {
			case "udp":
			case "tcp":
				if (!empty($post['ipaddress' . $i]) &&
				    (!is_ipaddrv4($post['ipaddress' . $i]) && !is_hostname($post['ipaddress' . $i]))) {
					$input_errors[] = sprintf(gettext("An IPv4 Address/Hostname must be entered, or the field must be blank. (%s)"), htmlspecialchars($post['ipaddress' . $i]));
				}
				$short_proto = $post['transport' . $i];
				break;
			case "udp6":
			case "tcp6":
				if (!empty($post['ipaddress' . $i]) &&
				    (!is_ipaddrv6($post['ipaddress' . $i]) && !is_hostname($post['ipaddress' . $i]))) {
					$input_errors[] = sprintf(gettext("An IPv6 Address/Hostname must be entered, or the field must be blank. (%s)"), htmlspecialchars($post['ipaddress' . $i]));
				}
				$short_proto = $post['transport' . $i];
				break;
			case "tlstcp":
			case "dtlsudp":
				if (!empty($post['ipaddress' . $i]) &&
				    (!is_hostname($post['ipaddress' . $i]) || is_ipaddr($post['ipaddress' . $i]))) {
					$input_errors[] = sprintf(gettext("TLS modes require a hostname, or the field must be blank. (%s)"), htmlspecialchars($post['ipaddress' . $i]));
				}
				$short_proto = substr($post['transport' . $i], -3, 3);
				break;
			default:
				$input_errors[] = gettext("Invalid transport specified.");
		}
		if (empty($post['snmpport' . $i])) {
			$post['snmpport' . $i] = '161';
		}
		if (!is_port($post['snmpport' . $i])) {
			$input_errors[] = sprintf(gettext("Invalid port number specified (%s)."), htmlspecialchars($post['snmpport' . $i]));
		} else {
			$test_port = "{$short_proto}:{$post['snmpport' . $i]}";
			if (in_array($test_port, $used_ports)) {
				$input_errors[] = sprintf(gettext("This port and protocol combination is already in use. (%s)"), htmlspecialchars($test_port));
			} else {
				$used_ports[] = $test_port;
			}
		}
		$i++;
	}

	/* Rate limits: integers, -1 and up. */
	if (!ctype_digit($post['maxgetbulkrepeats']) || ($post['maxgetbulkrepeats'] < -1)) {
		$input_errors[] = gettext("Max Get Bulk Repeats must be an integer -1 or greater.");
	}
	if (!ctype_digit($post['maxgetbulkresponses']) || ($post['maxgetbulkresponses'] < -1)) {
		$input_errors[] = gettext("Max Get Bulk Responses must be an integer -1 or greater.");
	}
}

/* snmpd - Host Information tab */
function netsnmp_validate_netsnmphostinfo($post, &$input_errors) {
	global $config;

	/* interface fadeout: blank or numeric, positive */
	if (!is_numericint($post['interface_fadeout'])) {
		$input_errors[] = gettext("Interface Fadeout must be an integer greater than 0.");
	}
	/* disk usage warning: blank or percentage (>0, <=100) */
	if (!empty($post['disk_usage_percent']) &&
	    (!is_numericint($post['disk_usage_percent']) || ($post['disk_usage_percent'] < 0) || ($post['disk_usage_percent'] > 100))) {
		$input_errors[] = gettext("Disk Usage Warning must be a percentage between 0 and 100, or left blank.");
	}
	/* load 1/5/15: blank or numeric, positive */
	if (!empty($post['load_1']) && !is_numericint($post['load_1'])) {
		$input_errors[] = gettext("The supplied 1-minute load average is invalid.");
	}
	if (!empty($post['load_5']) && !is_numericint($post['load_5'])) {
		$input_errors[] = gettext("The supplied 5-minute load average is invalid.");
	}
	if (!empty($post['load_15']) && !is_numericint($post['load_15'])) {
		$input_errors[] = gettext("The supplied 15-minute load average is invalid.");
	}

	/* Load average sanity check [ 1 [ 5 [ 15 ] ] ] */
	if (!empty($post['load_5']) && empty($post['load_1'])) {
		$input_errors[] = gettext("A 1-minute threshold is required when a 5-minute threshold is set.");
	}
	if (!empty($post['load_15']) && empty($post['load_1'])) {
		$input_errors[] = gettext("A 1-minute threshold is required when a 15-minute threshold is set.");
	}
	if (!empty($post['load_15']) && empty($post['load_5'])) {
		$input_errors[] = gettext("A 5-minute threshold is required when a 15-minute threshold is set.");
	}

	/* extend rowhelper validation */
	$i = 0;
	$used_ports = array();
	while (isset($post['ext_type' . $i])) {
		/* extend: type: extend, extendfix */
		if (!in_array($post['ext_type' . $i], array('extend', 'extendfix'))) {
			$input_errors[] = gettext("Invalid extended command type.");
		}
		/* extend: ext_prog: check if file exists/is executable */
		if (!empty($post['ext_prog' . $i]) &&
		    (!file_exists($post['ext_prog' . $i]) || !is_executable($post['ext_prog' . $i]))) {
			$input_errors[] = gettext("The supplied command does not exist or is not executable.");
		}
		$i++;
	}
}

/* snmpd - Users tab */
function netsnmp_validate_netsnmpusers($post, &$input_errors) {
	global $config;

	/* Username cannot be blank */
	if (empty($post['username'])) {
		$input_errors[] = gettext("The username cannot be blank.");
	}

	/* snmpv3_type: user, cert, both */
	if (!in_array($post['snmpv3_type'], array('user', 'cert', 'both'))) {
		$input_errors[] = gettext("Invalid SNMPv3 user type.");
	}
	/* authtype: SHA or MD5 */
	if (!in_array($post['authtype'], array('SHA', 'MD5'))) {
		$input_errors[] = gettext("Invalid authentication type.");
	}
	/* password: >=8 chars */
	if (strlen($post['password']) < 8) {
		$input_errors[] = gettext("The supplied Password is too short. It must be 8 characters or longer.");
	}
	/* privacyprotocol: AES, DES, none */
	if (!in_array($post['privacyprotocol'], array('AES', 'DES', 'none'))) {
		$input_errors[] = gettext("Invalid Privacy Protocol.");
	}
	/* passphrase: >=8 chars */
	if (strlen($post['passphrase']) < 8) {
		$input_errors[] = gettext("The supplied Passphrase is too short. It must be 8 characters or longer.");
	}
	/* rorwuser: rouser, rwuser */
	if (!in_array($post['rorwuser'], array('rouser', 'rwuser'))) {
		$input_errors[] = gettext("Invalid Read/Write Access type.");
	}
	/* minseclevusm: priv, auth, noauth, none */
	if (!in_array($post['minseclevusm'], array('priv', 'auth', 'noauth', 'none'))) {
		$input_errors[] = gettext("Invalid USM Minimum Security Level.");
	}
	/* priority: int, positive */
	if (!empty($post['priority']) && !is_numericint($post['priority'])) {
		$input_errors[] = gettext("The supplied certificate Priority is invalid. It must be a positive integer.");
	}
	/* options: 'cn', 'sn', 'rfc822', 'dns', 'ip', 'any' */
	if (!in_array($post['options'], array('cn', 'sn', 'rfc822', 'dns', 'ip', 'any'))) {
		$input_errors[] = gettext("Invalid Certificate Field type.");
	}
	/* minseclevtsm: priv, auth, noauth, none */
	if (!in_array($post['minseclevtsm'], array('priv', 'auth', 'noauth', 'none'))) {
		$input_errors[] = gettext("Invalid TSM Minimum Security Level.");
	}
}

/* snmpd - Communities tab */
function netsnmp_validate_netsnmpcommunities($post, &$input_errors) {
	global $config;

	/* rorwcommunity: rocommunity, rwcommunity */
	if (!in_array($post['rorwcommunity'], array('rocommunity', 'rwcommunity'))) {
		$input_errors[] = gettext("Invalid Read/Write Access type.");
	}
	/* commtype: IPv4, IPv6, both */
	if (!in_array($post['commtype'], array('IPv4', 'IPv6', 'both'))) {
		$input_errors[] = gettext("Invalid Address Family.");
	}
	/* source: 'default', IPv4 addr or subnet if commtype=IPv4, IPv6 addr or subnet if commtype = IPv6*/
	if (!empty($post['source']) && ($post['source'] != "default")) {
		switch ($post['commtype']) {
			case "IPv4":
				if (!is_ipaddrv4($post['source']) && !is_subnetv4($post['source'])) {
					$input_errors[] = gettext("The IP address or subnet is not valid or does not match the selecteed Address Family.");
				}
				break;
			case "IPv6":
				if (!is_ipaddrv6($post['source']) && !is_subnetv6($post['source'])) {
					$input_errors[] = gettext("The IP address or subnet is not valid or does not match the selecteed Address Family.");
				}
				break;
			case "both":
				$input_errors[] = gettext("A source may not be specified when Address Family is set to 'both'.");
				break;
			default:
		}
	}
}

/* snmpd - Trap Generation tab*/
function netsnmp_validate_netsnmptrapgen($post, &$input_errors) {
	global $config;

	if (is_array($config['installedpackages']['netsnmpusers'])) {
		$usettings = $config['installedpackages']['netsnmpusers']['config'];
	} else {
		$usettings = array();
	}
	/* authtrapenable: 1, 2 */
	if (!in_array($post['authtrapenable'], array('1', '2'))) {
		$input_errors[] = gettext("Invalid Authentication Traps value.");
	}
	/* linkupdownnotifications: yes, no */
	if (!in_array($post['linkupdownnotifications'], array('yes', 'no'))) {
		$input_errors[] = gettext("Invalid Link Up/Down Traps value.");
	}
	/* defaultmonitors: yes, no */
	if (!in_array($post['defaultmonitors'], array('yes', 'no'))) {
		$input_errors[] = gettext("Invalid Default Monitors value.");
	}
	/* iquerysecname: valid user */
	$found_user = false;
	foreach ($usettings as $user) {
		if ($post['iquerysecname'] == $user['username']) {
			$found_user = true;
		}
	}
	if (!$found_user) {
		$input_errors[] = gettext("Invalid Internal Query User value.");
	}
	/* v1trapaddress: blank, ip address */
	if (!empty($post['v1trapaddress']) && (!is_ipaddr($post['v1trapaddress']))) {
		$input_errors[] = gettext("An IPv4 Address/Hostname must be entered for SNMPv1 Local Trap Agent Address, or the field must be blank.");
	}

	/* trapsink1_host: blank, ip address, hostname */
	if (!empty($post['trapsink1_host']) && (!is_ipaddr($post['trapsink1_host']))) {
		$input_errors[] = gettext("An IPv4 Address/Hostname must be entered for SNMPv1 Trap Sink #1, or the field must be blank.");
	}
	/* trapsink1_port: blank, port number */
	if (!empty($post['trapsink1_port']) && (!is_port($post['trapsink1_port']))) {
		$input_errors[] = gettext(" port number must be entered for SNMPv1 Trap Sink #1, or the field must be blank.");
	}
	/* trapsink2_host: blank, ip address, hostname */
	if (!empty($post['trapsink2_host']) && (!is_ipaddr($post['trapsink2_host']))) {
		$input_errors[] = gettext("An IPv4 Address/Hostname must be entered for SNMPv1 Trap Sink #2, or the field must be blank.");
	}
	/* trapsink2_port: blank, port number */
	if (!empty($post['trapsink2_port']) && (!is_port($post['trapsink2_port']))) {
		$input_errors[] = gettext(" port number must be entered for SNMPv1 Trap Sink #2, or the field must be blank.");
	}
	/* trapsink3_host: blank, ip address, hostname */
	if (!empty($post['trapsink3_host']) && (!is_ipaddr($post['trapsink3_host']))) {
		$input_errors[] = gettext("An IPv4 Address/Hostname must be entered for SNMPv1 Trap Sink #3, or the field must be blank.");
	}
	/* trapsink3_port: blank, port number */
	if (!empty($post['trapsink3_port']) && (!is_port($post['trapsink3_port']))) {
		$input_errors[] = gettext(" port number must be entered for SNMPv1 Trap Sink #2, or the field must be blank.");
	}

	/* trap2sink1_host: blank, ip address, hostname */
	if (!empty($post['trap2sink1_host']) && (!is_ipaddr($post['trap2sink1_host']))) {
		$input_errors[] = gettext("An IPv4 Address/Hostname must be entered for SNMPv2c Trap Sink #1, or the field must be blank.");
	}
	/* trap2sink1_port: blank, port number */
	if (!empty($post['trap2sink1_port']) && (!is_port($post['trap2sink1_port']))) {
		$input_errors[] = gettext(" port number must be entered for SNMPv2c Trap Sink #1, or the field must be blank.");
	}
	/* trap2sink2_host: blank, ip address, hostname */
	if (!empty($post['trap2sink2_host']) && (!is_ipaddr($post['trap2sink2_host']))) {
		$input_errors[] = gettext("An IPv4 Address/Hostname must be entered for SNMPv2c Trap Sink #2, or the field must be blank.");
	}
	/* trap2sink2_port: blank, port number */
	if (!empty($post['trap2sink2_port']) && (!is_port($post['trap2sink2_port']))) {
		$input_errors[] = gettext(" port number must be entered for SNMPv2c Trap Sink #2, or the field must be blank.");
	}
	/* trap2sink3_host: blank, ip address, hostname */
	if (!empty($post['trap2sink3_host']) && (!is_ipaddr($post['trap2sink3_host']))) {
		$input_errors[] = gettext("An IPv4 Address/Hostname must be entered for SNMPv2c Trap Sink #3, or the field must be blank.");
	}
	/* trap2sink3_port: blank, port number */
	if (!empty($post['trap2sink3_port']) && (!is_port($post['trap2sink3_port']))) {
		$input_errors[] = gettext(" port number must be entered for SNMPv2c Trap Sink #3, or the field must be blank.");
	}

	/* informsink1_host: blank, ip address, hostname */
	if (!empty($post['informsink1_host']) && (!is_ipaddr($post['informsink1_host']))) {
		$input_errors[] = gettext("An IPv4 Address/Hostname must be entered for Inform Sink #1, or the field must be blank.");
	}
	/* informsink1_port: blank, port number */
	if (!empty($post['informsink1_port']) && (!is_port($post['informsink1_port']))) {
		$input_errors[] = gettext(" port number must be entered for Inform Sink #1, or the field must be blank.");
	}
	/* informsink2_host: blank, ip address, hostname */
	if (!empty($post['informsink2_host']) && (!is_ipaddr($post['informsink2_host']))) {
		$input_errors[] = gettext("An IPv4 Address/Hostname must be entered for Inform Sink #2, or the field must be blank.");
	}
	/* informsink2_port: blank, port number */
	if (!empty($post['informsink2_port']) && (!is_port($post['informsink2_port']))) {
		$input_errors[] = gettext(" port number must be entered for Inform Sink #2, or the field must be blank.");
	}
	/* informsink3_host: blank, ip address, hostname */
	if (!empty($post['informsink3_host']) && (!is_ipaddr($post['informsink3_host']))) {
		$input_errors[] = gettext("An IPv4 Address/Hostname must be entered for Inform Sink #3, or the field must be blank.");
	}
	/* informsink3_port: blank, port number */
	if (!empty($post['informsink3_port']) && (!is_port($post['informsink3_port']))) {
		$input_errors[] = gettext(" port number must be entered for Inform Sink #3, or the field must be blank.");
	}
}


/* snmptrapd - General tab*/
function netsnmp_validate_netsnmptrapd($post, &$input_errors) {
	global $config;

	$i = 0;
	$used_ports = array();
	while (isset($post['transport' . $i])) {
		$short_proto = "";
		switch ($post['transport' . $i]) {
			case "udp":
			case "tcp":
				if (!empty($post['ipaddress' . $i]) &&
				    (!is_ipaddrv4($post['ipaddress' . $i]) && !is_hostname($post['ipaddress' . $i]))) {
					$input_errors[] = sprintf(gettext("An IPv4 Address/Hostname must be entered, or the field must be blank. (%s)"), htmlspecialchars($post['ipaddress' . $i]));
				}
				$short_proto = $post['transport' . $i];
				break;
			case "udp6":
			case "tcp6":
				if (!empty($post['ipaddress' . $i]) &&
				    (!is_ipaddrv6($post['ipaddress' . $i]) && !is_hostname($post['ipaddress' . $i]))) {
					$input_errors[] = sprintf(gettext("An IPv6 Address/Hostname must be entered, or the field must be blank. (%s)"), htmlspecialchars($post['ipaddress' . $i]));
				}
				$short_proto = $post['transport' . $i];
				break;
			case "tlstcp":
			case "dtlsudp":
				if (!empty($post['ipaddress' . $i]) &&
				    (!is_hostname($post['ipaddress' . $i]) || is_ipaddr($post['ipaddress' . $i]))) {
					$input_errors[] = sprintf(gettext("TLS modes require a hostname, or the field must be blank. (%s)"), htmlspecialchars($post['ipaddress' . $i]));
				}
				$short_proto = substr($post['transport' . $i], -3, 3);
				break;
			default:
				$input_errors[] = gettext("Invalid transport specified.");
		}
		if (empty($post['snmpport' . $i])) {
			$post['snmpport' . $i] = '161';
		}
		if (!is_port($post['snmpport' . $i])) {
			$input_errors[] = sprintf(gettext("Invalid port number specified (%s)."), htmlspecialchars($post['snmpport' . $i]));
		} else {
			$test_port = "{$short_proto}:{$post['snmpport' . $i]}";
			if (in_array($test_port, $used_ports)) {
				$input_errors[] = sprintf(gettext("This port and protocol combination is already in use. (%s)"), htmlspecialchars($test_port));
			} else {
				$used_ports[] = $test_port;
			}
		}
		$i++;
	}

	/* donotretainnotificationlogs: yes, no */
	if (!in_array($post['donotretainnotificationlogs'], array('yes', 'no'))) {
		$input_errors[] = gettext("Invalid Retain Notification Logs value.");
	}
	/* donotlogtraps: yes, no */
	if (!in_array($post['donotlogtraps'], array('yes', 'no'))) {
		$input_errors[] = gettext("Invalid Log Received Traps value.");
	}
	/* ignoreauthfailure: yes, no */
	if (!in_array($post['ignoreauthfailure'], array('yes', 'no'))) {
		$input_errors[] = gettext("Invalid Ignore Auth Failure Traps value.");
	}
	/* disableauthorization: yes, no */
	if (!in_array($post['disableauthorization'], array('yes', 'no'))) {
		$input_errors[] = gettext("Invalid Use Authorization value.");
	}
}

/* snmptrapd - Community Auth tab*/
function netsnmp_validate_netsnmptrapdcommunities($post, &$input_errors) {
	global $config;

	/* type: log, net, execute */
	foreach ($post['type'] as $t) {
		if (!in_array($t, array('log', 'net', 'execute'))) {
			$input_errors[] = gettext("Invalid Process Type.");
		}
	}
	/* source restriction: 'default', IPv4 addr or subnet, IPv6 addr or subnet */
	if (!empty($post['source']) &&
	    ($post['source'] != "default") &&
	    !is_ipaddrv4($post['source']) && !is_subnetv4($post['source']) &&
	    !is_ipaddrv6($post['source']) && !is_subnetv6($post['source'])) {
		$input_errors[] = gettext("The IP address or subnet is not valid or does not match the selecteed Address Family.");
	}
}

/* snmptrapd - User Auth tab*/
function netsnmp_validate_netsnmptrapdusers($post, &$input_errors) {
	global $config;

	if (is_array($config['installedpackages']['netsnmpusers'])) {
		$usettings = $config['installedpackages']['netsnmpusers']['config'];
	} else {
		$usettings = array();
	}
	/* username: valid user */
	$found_user = false;
	foreach ($usettings as $user) {
		if ($post['username'] == $user['username']) {
			$found_user = true;
		}
	}
	if (!$found_user) {
		$input_errors[] = gettext("Invalid Trap User.");
	}
	/* type: log, net, execute */
	foreach ($post['type'] as $t) {
		if (!in_array($t, array('log', 'net', 'execute'))) {
			$input_errors[] = gettext("Invalid Process Type.");
		}
	}
	/* secmodel: usm, tsm */
	if (!in_array($post['secmodel'], array('usm', 'tsm'))) {
		$input_errors[] = gettext("Invalid Security Model type.");
	}
	/* level: priv, auth, noauth, none */
	if (!in_array($post['level'], array('priv', 'auth', 'noauth', 'none'))) {
		$input_errors[] = gettext("Invalid Minimum Security Level.");
	}
}

/* snmptrapd - Trap Handles tab */
function netsnmp_validate_netsnmptrapdtraphandles($post, &$input_errors) {
	global $config;

	/* program: file exists, is executable */
	if (!empty($post['program']) &&
	    (!file_exists($post['program']) || !is_executable($post['program']))) {
		$input_errors[] = gettext("The supplied command does not exist or is not executable.");
	}
}

/* snmptrapd - Forwarding of traps to other hosts */
function netsnmp_validate_netsnmptrapdforwards($post, &$input_errors) {
	global $config;

	/* Transport Type:
		D/TLS hostname.
		UDP/TCP hostname, IPv4 addr
		UDP6/TCP6 hostname, IPv6 addr
		 */
	$i = 0;
	$used_ports = array();
	while (isset($post['transport' . $i])) {
		$short_proto = "";
		switch ($post['transport' . $i]) {
			case "udp":
			case "tcp":
				if (empty($post['ipaddress' . $i]) ||
				    (!is_ipaddrv4($post['ipaddress' . $i]) && !is_hostname($post['ipaddress' . $i]))) {
					$input_errors[] = sprintf(gettext("An IPv4 Address/Hostname must be entered. (%s)"), htmlspecialchars($post['ipaddress' . $i]));
				}
				$short_proto = $post['transport' . $i];
				break;
			case "udp6":
			case "tcp6":
				if (empty($post['ipaddress' . $i]) ||
				    (!is_ipaddrv6($post['ipaddress' . $i]) && !is_hostname($post['ipaddress' . $i]))) {
					$input_errors[] = sprintf(gettext("An IPv6 Address/Hostname must be entered. (%s)"), htmlspecialchars($post['ipaddress' . $i]));
				}
				$short_proto = $post['transport' . $i];
				break;
			case "tlstcp":
			case "dtlsudp":
				if (empty($post['ipaddress' . $i]) ||
				    (!is_hostname($post['ipaddress' . $i]) || is_ipaddr($post['ipaddress' . $i]))) {
					$input_errors[] = sprintf(gettext("TLS modes require a hostname. (%s)"), htmlspecialchars($post['ipaddress' . $i]));
				}
				$short_proto = substr($post['transport' . $i], -3, 3);
				break;
			default:
				$input_errors[] = gettext("Invalid transport specified.");
		}
		if (empty($post['snmpport' . $i])) {
			$post['snmpport' . $i] = '161';
		}
		if (!is_port($post['snmpport' . $i])) {
			$input_errors[] = sprintf(gettext("Invalid port number specified (%s)."), htmlspecialchars($post['snmpport' . $i]));
		}
		$i++;
	}
}
