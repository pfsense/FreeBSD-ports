<?php
/*
	haproxy_utils.php
	part of pfSense (https://www.pfsense.org/)
	Copyright (C) 2013-2015 PiBa-NL
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
	AUTHOR BE LIABLE FOR ANY DIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
/*
  This file contains functions which are NOT specific to HAProxy and may/could/should 
  be moved to the general pfSense php library for possible easy use by other parts of pfSense
*/

require_once("config.inc");

class haproxy_utils {
	public static $pf_version;

	public function query_dns($host, $querytype="A,AAAA") {
		$result = array();
		$types = explode(',',$querytype);
		$recordtype = 0;
		foreach($types as $type){
			switch ($type) {
				case 'A': 
					$recordtype = DNS_A; 
					break;
				case 'AAAA': 
					$recordtype = DNS_AAAA; 
					break;
			}
			if ($recordtype != 0) {
				//query one type at a time, querying multiple types in one call dns_get_record fails if one is not present..
				$errreporting = error_reporting();
				error_reporting($errreporting & ~E_WARNING);// dns_get_record throws a warning if nothing is resolved..
				$dnsresult = dns_get_record($host, $recordtype);
				error_reporting($errreporting);
				if (is_array($dnsresult)) {
					foreach($dnsresult as $item) {
						$newitem["typeid"] = $item['type'];
						switch ($item['type']) {
							case 'A':
								$newitem["data"] = $item['ip']; 
								break;
							case 'AAAA':
								$newitem["data"] = $item['ipv6']; 
								break;
						}
						$result[] = $newitem;
					}
				}
			}
		}
		return $result;
	}
}
haproxy_utils::$pf_version = substr(trim(file_get_contents("/etc/version")),0,3);

function haproxy_check_isarray(&$item){
	if (!is_array($item)){
		$item = array();
	}
}

if(!function_exists('ifset')){
	function ifset(&$var, $default = ''){
		return isset($var) ? $var : $default;
}
}

if(!function_exists('is_arrayset')){
	function is_arrayset(&$array/*, $items, $items, $items*/) {
		if (!isset($array)) {
			return false;
		}
		$item = $array;
		$arg = func_get_args();
		for($i = 1; $i < count($arg); $i++) {

			$itemindex = $arg[$i];
			if (!isset($item[$itemindex]) || !is_array($item[$itemindex])) {
				return false;
			}
			$item = $item[$itemindex];

		}
		return true;
	}
}

function haproxy_compareByName($a, $b) {
	return strcasecmp($a['name'], $b['name']);
}

function haproxy_interface_ip($interfacebindname,$userfriendly=false){
	$list = haproxy_get_bindable_interfaces();
	$item = $list[$interfacebindname];
	$result = $item['ip'];
	if ($userfriendly && !$result) {
		$result = $item['name'];
	}
	return $result;
}

function haproxy_get_bindable_interfaces($ipv="ipv4,ipv6", $interfacetype="any,localhost,real,carp,ipalias"){
	// returns a list of ALL interface/IPs that can be used to bind a service to.
	// filtered by the conditions given in the two filter parameters.
	// result array includes:
	// $bindable[key]                can be stored and compared with previous setings
	// $bindable[key]['ip']          the current IP (possibly changes for dhcp enabled interfaces..)
	// $bindable[key]['description'] can be shown to user in a selection box

	global $config;
	$ipversions = split(',',$ipv);
	$interfacetypes= split(',',$interfacetype);

	$bindable = array();
	if (in_array("ipv4",$ipversions)){
		if (in_array('any',$interfacetypes)){
			$item = array();
			$item[ip] = '0.0.0.0';
			$item[name] = 'any (IPv4)';
			$bindable['any_ipv4'] = $item;
		}
		if (in_array('localhost',$interfacetypes)){
			$item = array();
			$item[ip] = '127.0.0.1';
			$item[name] = 'localhost (IPv4)';
			$bindable['localhost_ipv4'] = $item;
		}
		if (in_array('real',$interfacetypes)){
			foreach($config['interfaces'] as $if => $ifdetail) {
				if (!isset($ifdetail['enable'])) {
					continue;
				}
				if (!isset($ifdetail['ipaddr'])) {
					continue;
				}
				$descr = $ifdetail['descr'];
				if (!$descr){
					if ($if == "wan" && !$ifdetail['descr']) {
						$descr = "WAN";
					} else if ($if == "lan" && !$ifdetail['descr']) {
						$descr = "LAN";
					} else {
						$descr = $if;
				}
				}
				$item = array();
				$item['ip'] = get_interface_ip($if);
				$item['name'] = "$descr address (IPv4)";
				$bindable[$if.'_ipv4'] = $item;
			}
		}
		if (in_array('carp',$interfacetypes)){
			$carplist = get_configured_vip_list('inet', VIP_CARP);
			foreach ($carplist as $carpif => $carpip){
				$item = array();
				$item['ip'] = $carpip;
				$item['name'] = $carpip." (".get_vip_descr($carpip).")";
				$bindable[$carpip] = $item;
			}

		}
		if (in_array('ipalias',$interfacetypes)){
			$aliaslist = get_configured_vip_list('inet', VIP_IPALIAS);
			foreach ($aliaslist as $aliasif => $aliasip){
				$item = array();
				$item['ip'] = $aliasip;
				$item['name'] = $aliasip." (".get_vip_descr($aliasip).")";
				$bindable[$aliasip.'_ipv4'] = $item;
			}
		}
	}
	if (!isset($config['system']['ipv6allow'])) {
		return $bindable;// skip adding the IPv6 addresses if those are not 'allowed'
	}
	
	if (in_array("ipv6",$ipversions)){
		if (in_array('any',$interfacetypes)){
			$item = array();
			$item[ip] = '::';
			$item[name] = 'any (IPv6)';
			$bindable['any_ipv6'] = $item;
		}
		if (in_array('localhost',$interfacetypes)){
			$item = array();
			$item[ip] = '::1';
			$item[name] = 'localhost (IPv6)';
			$bindable['localhost_ipv6'] = $item;
		}
		if (in_array('real',$interfacetypes)){
			foreach($config['interfaces'] as $if => $ifdetail) {
				if (!isset($ifdetail['enable'])) {
					continue;
				}
				if (!isset($ifdetail['ipaddrv6'])) {
					continue;
				}
				$descr = $ifdetail['descr'];
				if (!$descr){
					if ($if == "wan" && !$ifdetail['descr']) {
						$descr = "WAN";
					} else if ($if == "lan" && !$ifdetail['descr']) {
						$descr = "LAN";
					} else {
						$descr = $if;
				}
				}
				$item = array();
				$item['ip'] = get_interface_ipv6($if);
				$item['name'] = "$descr  address (IPv6)";
				$bindable[$if.'_ipv6'] = $item;
			}
		}		
		if (in_array('carp',$interfacetypes)){
			$carplist = get_configured_vip_list('inet6', VIP_CARP);
			foreach ($carplist as $carpif => $carpip){
				$item = array();
				$item['ip'] = $carpip;
				$item['name'] = $carpip." (".get_vip_descr($carpip).")";
				$bindable[$carpip] = $item;
			}

		}
		if (in_array('ipalias',$interfacetypes)){
			$aliaslist = get_configured_vip_list('inet6', VIP_IPALIAS);
			foreach ($aliaslist as $aliasif => $aliasip){
				$item = array();
				$item['ip'] = $aliasip;
				$item['name'] = $aliasip." (".get_vip_descr($aliasip).")";
				$bindable[$aliasip] = $item;
			}
		}
	}
	return $bindable;
}

function haproxy_get_cert_extensions($crt){
	$cert = openssl_x509_parse(base64_decode($crt['crt']));
	return $cert['extensions'];
}

function haproxy_get_cert_authoritykeyidentifier($cert)
{
	$certextension = haproxy_get_cert_extensions($cert);
	$lines = preg_split('/[\n]+/',$certextension['authorityKeyIdentifier']);
	return substr($lines[0],6);// cut off the starting string 'keyid:'
}
function haproxy_get_cert_subjectKeyIdentifier($cert)
{
	$certextension = haproxy_get_cert_extensions($cert);
	$lines = preg_split('/[\n]+/',$certextension['subjectKeyIdentifier']);
	return $lines[0];
}

function haproxy_cert_signed_by($cert, $signedbycert) {
	// checks if $cert was signed by $signedbycert
	// this does NOT validate a proper signature but only checks if the extension properties match.
	$authoritykeyid = haproxy_get_cert_authoritykeyidentifier($cert);
	$subjectid = haproxy_get_cert_subjectKeyIdentifier($signedbycert);
	return $authoritykeyid == $subjectid;
}

function haproxy_recalculate_certifcate_chain(){
	// and set "selfsigned" for certificates that where used to sign themselves
	// recalculate the "caref" for all certificates where it is currently unkown.
	
	$allcertificates = haproxy_get_certificates('ca,server,user',true);
	$items_recalculated = 0;
	foreach($allcertificates as &$cert){
		$recalculate=false;
		if (!isset($cert['selfsigned'])){
			if (!isset($cert['caref'])) {
				$recalculate=true;
			} else {		
				$ca = lookup_ca($cert['caref']);
				if (!$ca) {
					$recalculate=true;
			}
		}
		}
		if ($recalculate){
			foreach($allcertificates as &$signedbycert){
				if(haproxy_cert_signed_by($cert, $signedbycert)){
					if ($cert['refid'] == $signedbycert['refid']){
						$cert['selfsigned'] = true;
					} else {					
						$cert['caref'] = $signedbycert['refid'];
					}
					$items_recalculated++;
				}
			}
		}
	}
	if ($items_recalculated > 0) {
		write_config("Services: HAProxy: Recalculated $items_recalculated certificate chains.");
	}
	return $items_recalculated;
}

function get_certificat_usage($refid) {
	$usage = array();
	$cert = lookup_cert($refid);
	if (is_cert_revoked($cert)) {
		$usage[] = "*Revoked";
	}
	if (is_webgui_cert($refid)) {
		$usage[] = "webConfigurator";
	}
	if (is_user_cert($refid)) {
		$usage[] = "User Cert";
	}
	if (is_openvpn_server_cert($refid)) {
		$usage[] = "OpenVPN Server";
	}
	if (is_openvpn_client_cert($refid)) {
		$usage[] = "OpenVPN Client";
	}
	if (is_ipsec_cert($refid)) {
		$usage[] = "IPsec Tunnel";
	}
	if (function_exists("is_captiveportal_cert")) {
		if (is_captiveportal_cert($refid)){
			$usage[] = "Captive Portal";
		}
	}
	return $usage;
}

function haproxy_get_certificate_descriptivename($cert) {
	$usage = get_certificat_usage($cert['crt']);
	foreach($usage as $use){
		$usagestr .= " " . $use;
	}		
	if ($usagestr != "") {
		$usagestr = " (".trim($usagestr).")";
	}
	$purpose = cert_get_purpose($cert['crt']);
	$certserverpurpose = $purpose['server'] == 'Yes' ? " [Server cert]" : "";
		
	$caname = "";
	$ca = lookup_ca($cert['caref']);
	if ($ca) {
		$caname = " (CA: {$ca['descr']})";
	}
	return $cert['descr'] . $caname . $certserverpurpose . $usagestr;
}

function haproxy_get_certificates($type = 'server,user', $get_includeWebCert=false) {
	// $type one or multiple of these separated by a comma:  ca,server,user
	// $get_includeWebCert if the webgui certificate may be included.

	// This function (is intended to) provide a uniform way to retrieve a list of server certificates
	global $config;
	$type = ",$type,";
	$certificates = array();
	if (strpos($type,',server,') !== false || strpos($type,',user,') !== false ) {
		if (is_array($config['cert'])) {
			$a_cert = &$config['cert'];
			foreach ($a_cert as $cert) {
				$purpose = cert_get_purpose($cert['crt']);
				
				$ok = false;
				$ok |= stristr($type,',server,') && $purpose['server'] == 'Yes';
				$ok |= stristr($type,',user,') && $purpose['server'] != 'Yes';
				if (!$ok) {
					continue;
				}
				if ($get_includeWebCert == false && $cert['descr'] == "webConfigurator default") {
					continue;
				}
				$certificates[$cert['refid']]['name'] = haproxy_get_certificate_descriptivename($cert);
			}
		}
	}
	if (strpos($type,',ca,') !== false) {
		if (is_array($config['ca'])) {
			$a_cert = &$config['ca'];
			foreach ($a_cert as $cert) {
				$certificates[$cert['refid']]['name'] = haproxy_get_certificate_descriptivename($cert);
			}
		}
	}
	uasort($certificates, haproxy_compareByName);
	return $certificates;
}

function haproxy_get_certificate_subjectAltNames($str_crt, $decode = true) {
	if ($decode) {
		$str_crt = base64_decode($str_crt);
	}
	$result = array();
	$ext = openssl_x509_parse($str_crt, false);
	$subjectAltName = $ext['extensions']['subjectAltName'];
	$lines = explode('\n', $subjectAltName);
	foreach($lines as $line) {
		$items = explode(',', $line);
		foreach($items as $item) {
			$item = trim($item);
			if (strpos($item, "DNS:") === 0) {
				$DNSitem = substr($item, 4);
				$result[] = $DNSitem;
			}
		}
	}
	return $result;
}

function haproxy_get_crls() {
	global $config;
	$certificates=array();
	if (is_array($config['crl'])) {
		foreach ($config['crl'] as $crl) {
			$caname = "";
			$ca = lookup_ca($crl['caref']);
			if ($ca) {
				$caname = " (CA: {$ca['descr']})";
			}
			$certificates[$crl['refid']]['name'] = $crl['descr'] . $caname;
		}
	}
	uasort($certificates, haproxy_compareByName);
	return $certificates;
}

function phparray_to_javascriptarray_recursive($nestID, $path, $items, $nodeName, $includeitems) {
	$offset = str_repeat('  ',$nestID);
	$itemName = "item$nestID";
	//echo "{$offset}$nodeName = {};\n";
	echo "{$offset}$nodeName = Object.create(null);\n";
	if (is_array($items)) {
		foreach ($items as $key => $item)
		{
			if (in_array($path.'/'.$key, $includeitems)) {
				$subpath = $path.'/'.$key;
			} else {
				$subpath = $path.'/*';
			}
			if (in_array($subpath, $includeitems) || in_array($path.'/*', $includeitems)) {
				if (is_array($item)) {
					$subNodeName = "item$nestID";
					phparray_to_javascriptarray_recursive($nestID+1, $subpath, $items[$key], $subNodeName, $includeitems);
					echo "{$offset}{$nodeName}['{$key}'] = $itemName;\n";
				} else {
					$item = json_encode($item);
					echo "{$offset}{$nodeName}['$key'] = $item;\n";
				}
			}
		}
}
}

function phparray_to_javascriptarray($items, $javaMapName, $includeitems) {
	phparray_to_javascriptarray_recursive(1,'',$items, $javaMapName, $includeitems);
}

function haproxy_html_select_options($keyvaluelist, $selected="") {
	$result = "";
	foreach($keyvaluelist as $key => $desc){
		$selectedhtml = $key == $selected ? "selected" : "";
		if ($desc['deprecated'] && $key != $selected){
			continue;
		}
		$name = htmlspecialchars($desc['name']);
		$result .= "<option value='{$key}' {$selectedhtml}>{$name}</option>";
	}
	return $result;
}

function haproxy_js_select_options($keyvaluelist, $selected="") {
	$result = "";
	foreach($keyvaluelist as $key => $desc){
		$selectedhtml = $key == $selected ? "selected" : "";
		if ($desc['deprecated'] && $key != $selected){
			continue;
		}
		$name = htmlspecialchars($desc['name']);
		$result .= "<option value='{$key}' {$selectedhtml}>{$name}<\/option>";
	}
	return $result;
}

function echo_html_select($name, $keyvaluelist, $selected, $listEmptyMessage="", $onchangeEvent="", $style="") {
	$result = "";
	if (count($keyvaluelist)>0){
		if ($onchangeEvent != "") {
			$onchangeEvent = " onchange='$onchangeEvent'";
		}
		if ($style != "") {
			$style = " style='$style'";
		}
		$result .= "<select name=\"$name\" id=\"$name\" class=\"formselect\"$onchangeEvent$style>";
		$result .= haproxy_html_select_options($keyvaluelist, $selected);
		$result .= "</select>";
	} else {
		$result .= $listEmptyMessage;
	}
	return $result;
}

function haproxy_keyvalue_array($hap_array) {
	$result = array();
	foreach($hap_array as $key => $item) {
		$result[$key] = $item['name'];
	}
	return $result;
}
