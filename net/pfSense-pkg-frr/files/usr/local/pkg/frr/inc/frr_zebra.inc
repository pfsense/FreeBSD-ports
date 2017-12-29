<?php
/*
 * frr_zebra.inc
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

/* Fetch the list of access lists for use in selection lists */
function frr_get_accesslist_list() {
	global $config;
	$list = array();
	$list[] = array("name" => "None", "value" => "none");
	if (is_array($config['installedpackages']['frrglobalacls']['config'])) {
		foreach ($config['installedpackages']['frrglobalacls']['config'] as $acl) {
			$list[] = array("name" => "{$acl['name']} - {$acl['descr']}", "value" => $acl['name']);
		}
	}
	return ($list);
}

/* Fetch the list of prefix lists for use in selection lists */
function frr_get_prefixlist_list() {
	global $config;
	$list = array();
	$list[] = array("name" => "None", "value" => "none");
	if (is_array($config['installedpackages']['frrglobalprefixes']['config'])) {
		foreach ($config['installedpackages']['frrglobalprefixes']['config'] as $pl) {
			$list[] = array("name" => "{$pl['name']} - {$pl['descr']}", "value" => $pl['name']);
		}
	}
	return ($list);
}

/* Fetch the list of route maps for use in selection lists */
function frr_get_routemap_list($exitaction = false, $yesno = false) {
	global $config;
	$list = array();

	/* Some selections can be enabled/disabled without a specific value,
	 * for those, offer a "yes" or "no" choice. */
	if ($yesno) {
		$list[] = array("name" => "No", "value" => "no");
		$list[] = array("name" => "Yes", "value" => "yes");
	} else {
		$list[] = array("name" => "None", "value" => "none");
	}

	/* If this is an exit action, return 'next' as a possible choice. */
	if ($exitaction) {
		$list[] = array("name" => "Next", "value" => "next");
	}

	/* Build a list of route maps. If this is an exit action, return
	 * sequence numbers of entries as the value, not names */
	if (is_array($config['installedpackages']['frrglobalroutemaps']['config'])) {
		foreach ($config['installedpackages']['frrglobalroutemaps']['config'] as $rm) {
			if ($exitaction) {
				$list[] = array("name" => $rm['seq'], "value" => $rm['seq']);
			} else {
				$list[] = array("name" => "{$rm['name']} - {$rm['descr']}", "value" => $rm['name']);
			}
		}
	}

	/* An exit action list contains only sequence numbers, so put them in
	 * order. */
	if ($exitaction) {
		sort($list);
	}

	return ($list);
}

/* Build a list of possible "next hop" values including standard options
 * and a list of neighbors which are IP addresses and not groups. */
function frr_get_routemap_nexthop_list() {
	global $config;
	$list = array();
	$list[] = array("name" => "None", "value" => "none");
	$list[] = array("name" => "Local (match only)", "value" => "local");
	$list[] = array("name" => "Unchanged (set only)", "value" => "unchanged");
	$list[] = array("name" => "Peer Address (set only)", "value" => "peer-address");
	$list = array_merge_recursive($list, frr_get_bgp_neighbor_list(false, true));
	return $list;
}

/* Determine the ACL type based on the given name */
function frr_get_acl_type($aclname) {
	/* 0-99, 1300-1999: Standard
	 * 100-199, 2000-2699: Extended
	 * WORD: Zebra
	 */
	$acl_type = "";
	if (is_numeric($aclname)) {
		if ((($aclname > 0) && ($aclname < 100)) ||
		    (($aclname > 1299) && ($aclname < 2000))) {
			$acl_type = "standard";
		} elseif ((($aclname > 99) && ($aclname < 200)) ||
		    (($aclname > 1999) && ($aclname < 2700))) {
			$acl_type = "extended";
		}
	} elseif (frr_validate_word($aclname)) {
		$acl_type = "zebra";
	}
	return $acl_type;
}

function frr_generate_acl_hostspec($address, $acl_type) {
	$hostspec = "";
	switch($acl_type) {
		case "standard":
		case "extended":
			if (is_subnet($address)) {
				/* Networks are:
				 * "x.x.x.x y.y.y.y"
				 * 'subnet id'<space>'wildcard bits'
				 */
				list($ip, $bits) = explode('/', $address);
				$hostspec = gen_subnet($ip, $bits);
				$hostspec .= " ";
				$hostspec .= frr_cidr_to_wildcard_bits($bits);
			} elseif (is_ipaddr($address)) {
				/* Hosts are "host x.x.x.x" */
				$hostspec = "host {$address}";
			} elseif (($address == "any") ||
			    (empty($address) &&
			    ($acl_type == "extended"))) {
				/* "any" */
				$hostspec = "any";
			}
			break;
		case "zebra":
			/* Networks are "x.x.x.x/YY" (CIDR) */
			if (is_subnet($address) || ($address == "any")) {
				$hostspec = $address;
			} if ($address == "any6") {
				$hostspec = "any";
			}
			break;
		default:
			$hostspec = "";
	}
	return $hostspec;
}

/* Access lists for the zebra configuration based on GUI config */
function frr_zebra_generate_accesslists() {
	global $config, $frr_config_base;
	/* Populate FRR Global Settings */
	if (is_array($config['installedpackages']['frrglobalacls']['config'])) {
		$frr_acls_conf = &$config['installedpackages']['frrglobalacls']['config'];
	} else {
		/* No ACL config, stop. */
		return null;
	}
	/* Section header */
	$aclconf = "\n# Access Lists\n";
	/* Loop through ACLs and process */
	foreach ($frr_acls_conf as $acl) {
		/* Determine ACL type */
		$acl_type = frr_get_acl_type($acl['name']);
		/* Once an ACL is marked IPv6, all of its directives must be IPv6. */
		$ipv6 = "";

		/* Sort the array by seq, if needed. */
		frr_array_seq_sort($acl['row']);

		/* Loop through the contents of each ACL and form directives */
		foreach ($acl['row'] as $line) {
			$hostspec = "";
			/* By this point, rows must be "permit" or "deny" otherwise they are invalid. */
			if (($line['action'] != "permit") && ($line['action'] != "deny")) {
				continue;
			}
			/* The ACL Type determines how the line is formed, what is valid, etc. */
			switch($acl_type) {
				case "standard":
					/* Source address only */
					$hostspec = frr_generate_acl_hostspec($line['source'], $acl_type);
					break;
				case "extended":
					/* Source and Destination addresses */
					$hostspec = frr_generate_acl_hostspec($line['source'], $acl_type);
					$hostspec .= " ";
					$hostspec .= frr_generate_acl_hostspec($line['destination'], $acl_type);
					break;
				case "zebra":
					/* Source address only */
					$hostspec = frr_generate_acl_hostspec($line['source'], $acl_type);
					/* If the source is IPv6, mark this ACL as being IPv6 */
					if (is_subnetv6($line['source']) || ($line['source'] == "any6")) {
						$ipv6 = "ipv6 ";
					}
					break;
				default:
					/* If this block is reached, the ACL Type was not valid, so skip this entry. */
					continue;
			}
			if (empty($hostspec)) {
				/* Nothing to do here, there was no valid source or destination for this line/type, so omit the entire line. */
				continue;
			}
			$aclconf .= "{$ipv6}access-list {$acl['name']} {$line['action']} ";
			/* Extended ACLs need "ip" before the src/dst specs */
			if ($acl_type == "extended") {
				$aclconf .= "ip ";
			}
			/* Add on the source/destination */
			$aclconf .= $hostspec;
			/* Add exact-match only for Zebra ACLs */
			if (($acl_type == "zebra") && isset($line['exactmatch'])) {
				$aclconf .= " exact-match";
			}
			$aclconf .= "\n";
		}
		/* Add the ACL Description in the configuration as a remark (limit 100 chars) */
		$acl['descr'] = substr($acl['descr'], 0, 100);
		$aclconf .= "{$ipv6}access-list {$acl['name']} remark {$acl['descr']}\n";
	}

	return $aclconf;
}

/* Prefix Lists for the zebra configuration based on GUI config */
function frr_zebra_generate_prefixlists() {
	global $config, $frr_config_base;
	/* Populate FRR Global Settings */
	if (is_array($config['installedpackages']['frrglobalprefixes']['config'])) {
		$frr_prefixlists_conf = &$config['installedpackages']['frrglobalprefixes']['config'];
	} else {
		/* No Prefix List config, stop. */
		return null;
	}
	$plconf = "\n# Prefix Lists\n";

	/* Loop through Prefix Lists and process */
	foreach ($frr_prefixlists_conf as $pl) {
		/* Once a Prefix List is marked IPv6, all of its directives must be IPv6. */
		$iptype = "";

		/* Sort the array by seq, if needed. */
		frr_array_seq_sort($pl['row']);

		/* Loop through the contents of each ACL and form directives */
		foreach ($pl['row'] as $line) {
			/* By this point, rows must be "permit" or "deny" otherwise they are invalid. */
			if (($line['action'] != "permit") && ($line['action'] != "deny")) {
				continue;
			}
			/* If the source is IPv6, mark this ACL as being IPv6 */
			if (is_subnetv6($line['source']) || ($line['source'] == "any6")) {
				$iptype = "ipv6";
				if ($line['source'] == "any6") {
					$line['source'] = "any";
				}
			} elseif (is_subnetv4($line['source']) || ($line['source'] == "any")) {
				$iptype = "ip";
			} else {
				/* If the source is empty or not a subnet, the line is invalid so skip it. */
				continue;
			}

			$plconf .= "{$iptype} prefix-list {$pl['name']} ";
			if (!empty($line['seq'])) {
				$plconf .= "seq {$line['seq']} ";
			}
			$plconf .= "{$line['action']} {$line['source']} ";
			/* Minimum Prefix (greater than or equal to) */
			if ($line['ge']) {
				$plconf .= "ge {$line['ge']} ";
			}
			/* Maximum Prefix (less than or equal to) */
			if ($line['le']) {
				$plconf .= "le {$line['le']} ";
			}
			$plconf .= "\n";
		}
		/* Add the PL Description in the configuration as a remark (limit 80 chars) */
		$acl['descr'] = substr($acl['descr'], 0, 80);
		$plconf .= "{$iptype} prefix-list {$pl['name']} description {$pl['descr']}\n";
	}

	return $plconf;
}

/* Route Maps for the zebra configuration based on GUI config */
function frr_zebra_generate_routemaps() {
	global $config, $frr_config_base;
	/* Populate FRR Global Settings */
	if (is_array($config['installedpackages']['frrglobalroutemaps']['config'])) {
		$frr_routemap_conf = &$config['installedpackages']['frrglobalroutemaps']['config'];
	} else {
		/* No route map config, stop. */
		return null;
	}
	$rmconf = "\n# Route Maps\n";

	/* Need to sort by name AND seq */
	usort($frr_routemap_conf, "frr_array_name_seq_compare");

	/* Generate Route Map list */
	foreach ($frr_routemap_conf as $rm) {
		/* General Options */
		/* By this point, action must be "permit" or "deny" otherwise they are invalid, the name must be a valid word, and sequence must exist. */
		if ((($rm['action'] != "permit") && ($rm['action'] != "deny")) ||
		    !frr_validate_word($rm['name']) ||
		    !is_numeric($rm['seq'])) {
			continue;
		}
		$rmconf .= "route-map {$rm['name']} {$rm['action']} {$rm['seq']}\n";

		/* Logic Control */
		if (in_array($rm['call'], frr_get_list_values(frr_get_routemap_list())) &&
		    ($rm['call'] != "none")) {
			$rmconf .= "  call {$rm['call']}\n";
		}
		if (isset($rm['exitaction']) &&
		    ($rm['exitaction'] == "next")) {
			$rmconf .= "  on-match next\n";
		} elseif (in_array($rm['exitaction'], frr_get_list_values(frr_get_routemap_list(true))) &&
		    ($rm['exitaction'] != "none")) {
			$rmconf .= "  on-match goto {$rm['exitaction']}\n";
		}

		/* Access List */
		if (in_array($rm['acl_match'], frr_get_list_values(frr_get_accesslist_list())) &&
		    ($rm['acl_match'] != "none")) {
			$rmconf .= "  match ip address {$rm['acl_match']}\n";
		}

		/* Prefix List */
		if (in_array($rm['prefix_match'], frr_get_list_values(frr_get_prefixlist_list())) &&
		    ($rm['prefix_match'] != "none")) {
			$rmconf .= "  match ip address prefix-list {$rm['prefix_match']}\n";
		}

		/* Next Hop */
		switch ($rm['nexthop_action']) {
			case "match-peer":
				if (in_array($rm['nexthop_peer'], frr_get_list_values(frr_get_routemap_nexthop_list())) &&
				    (($rm['nexthop_peer'] == "local") || is_ipaddr($rm['nexthop_peer']))) {
					$rmconf .= "  match peer {$rm['nexthop_peer']}\n";
				}
				break;
			case "match-acl":
				if (in_array($rm['nexthop_acl'], frr_get_list_values(frr_get_accesslist_list())) &&
				    ($rm['nexthop_acl'] != "none")) {
					$rmconf .= "  match ip next-hop {$rm['nexthop_acl']}\n";
				}
				break;
			case "match-pl":
				if (in_array($rm['nexthop_pl'], frr_get_list_values(frr_get_prefixlist_list())) &&
				    ($rm['nexthop_pl'] != "none")) {
					$rmconf .= "  match ip next-hop prefix-list {$rm['nexthop_pl']}\n";
				}
				break;
			case "set":
				if (in_array($rm['nexthop_peer'], frr_get_list_values(frr_get_routemap_nexthop_list())) &&
				    (($rm['nexthop_peer'] == "peer-address") ||
				    ($rm['nexthop_peer'] == "unchanged") ||
				    is_ipaddr($rm['nexthop_peer']))) {
					$rmconf .= "  set ip next-hop {$rm['nexthop_peer']}\n";
				}
				break;
			default:
		}

		/* Metric */
		switch ($rm['metric_action']) {
			case "match":
				if (frr_validate_ulong($rm['metric_value'])) {
					$rmconf .= "  match metric {$rm['metric_value']}\n";
				}
				break;
			case "set":
				if (strlen($rm['metric_value']) > 0) {
					$rmconf .= "  set metric {$rm['metric_value']}\n";
				}
				break;
			case "settype1":
				if (strlen($rm['metric_value']) > 0) {
					$rmconf .= "  set metric {$rm['metric_value']}\n";
					$rmconf .= "  set metric-type type-1\n";
				}
				break;
			case "settype2":
				if (strlen($rm['metric_value']) > 0) {
					$rmconf .= "  set metric {$rm['metric_value']}\n";
					$rmconf .= "  set metric-type type-2\n";
				}
				break;
			default:
		}

		/* Weight */
		if (frr_validate_ulong($rm['metric_value'])) {
			$rmconf .= "  match metric {$rm['metric_value']}\n";
		}

		/* Local Preference */
		switch ($rm['localpref_action']) {
			case "match":
				if (frr_validate_ulong($rm['localpref_value'])) {
					$rmconf .= "  match local-preference {$rm['localpref_value']}\n";
				}
				break;
			case "set":
				if (frr_validate_ulong($rm['localpref_value'])) {
					$rmconf .= "  set local-preference {$rm['localpref_value']}\n";
				}
				break;
			default:
		}

		/* BGP AS Paths */
		switch ($rm['aspath_action']) {
			case "match":
				if (in_array($rm['aspath_match'], frr_get_list_values(frr_get_bgp_aspath_list()))) {
					$rmconf .= "  match as-path {$rm['aspath_match']}\n";
				}
				break;
			case "set":
			case "setexclude":
			case "setprepend":
				/* When setting the AS, the following is a space-separated
				 * list of valid AS numbers */
				foreach (explode(' ', $rm['aspath_value']) as $asnum) {
					if (!frr_validate_asnum($asnum)) {
						/* If there is an invalid AS number, skip this entire line */
						break;
					}
				}

				$rmconf .= "  set as-path ";
				/* Add the string for either 'exclude' or 'prepend' */
				if ((strlen($rm['aspath_action']) > 3) && (substr($rm['aspath_action'], 0, 3) == 'set')) {
					$rmconf .= substr($rm['aspath_action'], 3) . " ";
				}
				$rmconf .= "{$rm['aspath_value']}\n";
				break;
			case "setprependlast":
				/* For Last-AS, the peer AS is prepended and only a single number is allowed
				 * from 1-10 which specifies the number of times to prepend the peer's AS number. */
				if (frr_validate_intrange($rm['aspath_value'], 1, 10)) {
					$rmconf .= "  set as-path prepend last-as {$rm['aspath_value']}\n";
				}
				break;
			default:
		}

		/* BGP Communities */
		switch ($rm['community_action']) {
			case "match":
			case "matchexact":
				if (in_array($rm['community_match'], frr_get_list_values(frr_get_bgp_community_list()))) {
					$rmconf .= "  match community {$rm['community_match']}";
					if ($rm['community_action'] == "matchexact") {
						$rmconf .= " exact-match";
					}
					$rmconf .= "\n";
				}
				break;
			case "set":
				if (frr_validate_community_asval_list($rm['community_set'])) {
					$rmconf .= "  set community {$rm['community_set']} ";
					if (!empty($rm['community_additive'])) {
						$rmconf .= " additive";
					}
					$rmconf .= "\n";
				}
				break;
			default:
		}

		/* Origin */
		if (frr_validate_bgp_origin($rm['origin_value'])) {
			$rmconf .= "  set origin {$rm['origin_value']}\n";
		}

		/* Source Protocol */
		if (frr_validate_bgp_sourceproto($rm['source_proto'])) {
			$rmconf .= "  match source-protocol {$rm['source_proto']}\n";
		}

		/* Tags */
		if (frr_validate_asnum($rm['tag_value'])) {
			switch ($rm['tag_action']) {
				case "match":
					$rmconf .= "  match tag {$rm['tag_value']}\n";
					break;
				case "set":
					$rmconf .= "  set tag {$rm['tag_value']}\n";
					break;
				default:
			}
		}

	}
	return $rmconf;
}

/* Null Routes for the zebra configuration based on GUI config */
function frr_zebra_generate_nullroutes() {
	global $config, $frr_config_base;
	/* Populate FRR Global Settings */
	if (!is_array($config['installedpackages']['frr']['config'][0]['row'])) {
		/* If FRR is not configured or the route handling list is empty, stop. */
		return null;
	}
	$nullroutes = "\n# Null Routes\n";
	/* Check FRR configuration network list for null route entries */
	foreach ($config['installedpackages']['frr']['config'][0]['row'] as $redistr) {
		if (isset($redistr['nullroute']) && is_subnet($redistr['routevalue'])) {
			$nullroutes .= "ip route {$redistr['routevalue']} Null0\n";
		}
	}
	return $nullroutes;
}

/* Static Routes for zebra configuration based on GUI config */
function frr_zebra_generate_staticroutes() {
	require_once("gwlb.inc");
	global $config, $frr_config_base;
	/* Populate FRR Global Settings */
	if (!is_array($config['installedpackages']['frr']['config'][0]['row'])) {
		/* If FRR is not configured or the route handling list is empty, stop. */
		return null;
	}
	$staticroutes = "\n# Static Routes\n";
	/* Check FRR configuration network list for null route entries */
	foreach ($config['installedpackages']['frr']['config'][0]['row'] as $redistr) {
		if (isset($redistr['nullroute']) || !is_subnet($redistr['routevalue'])) {
			/* Null routes are handled separately, and we need a valid subnet! */
			continue;
		}
		if (!empty($redistr['routetarget'])) {
			/* Resolve target based on type|value */
			list ($type, $target) = explode("|", $redistr['routetarget'], 2);
			switch ($type) {
				/* Gateways */
				case "gw":
					$target = lookup_gateway_ip_by_name($target);
					break;
				/* BGP Neighbors */
				case "bgp":
					/* Target is already a usable address */
					break;
				/* Interfaces */
				case "if":
					$family = "all";
					if (is_subnetv6($target)) {
						$family = "inet6";
					}
					$target = get_real_interface($target, $family);
					break;
				default:
					/* Invalid target!*/
					continue;
			}
			if (!empty($target)) {
				$staticroutes .= "ip route {$redistr['routevalue']} {$target}\n";
			}
		}
	}
	return $staticroutes;
}

/* Generate a convenience list of accept filters */
function frr_zebra_generate_acceptfilters() {
	global $config, $frr_config_base;
	$noaccept = "\n# Accept Filters\n";
	/* Check OSPF Interfaces for automatic accept filters */
	if (is_array($config['installedpackages']['frrospfdinterfaces']['config'])) {
		foreach ($config['installedpackages']['frrospfdinterfaces']['config'] as $conf) {
			list($interface_ip, $interface_subnet, $subnet) = frr_get_interfaceinfo($conf['interface']);
			if (empty($interface_ip)) {
				continue;
			}
			if (!empty($conf['acceptfilter']) && is_ipaddrv4($subnet) && is_numericint($interface_subnet)) {
				$noaccept .= "ip prefix-list ACCEPTFILTER deny {$subnet}/{$interface_subnet}\n";
				$noaccept .= "ip prefix-list ACCEPTFILTER deny {$interface_ip}/32\n";
			}
		}
	}

	/* Check FRR configuration network list for accept filter entries */
	if (is_array($config['installedpackages']['frr']['config'][0]['row'])) {
		foreach ($config['installedpackages']['frr']['config'][0]['row'] as $redistr) {
			if (isset($redistr['acceptfilter'])) {
				$noaccept .= "ip prefix-list ACCEPTFILTER deny {$redistr['routevalue']}\n";
			}
		}
	}
	if (!empty($noaccept)) {
		$noaccept .= "ip prefix-list ACCEPTFILTER permit any\n";
		$noaccept .= "route-map ACCEPTFILTER permit 10\n";
		$noaccept .= " match ip address prefix-list ACCEPTFILTER\n";
		$noaccept .= "ip protocol ospf route-map ACCEPTFILTER\n";
	}
	return $noaccept;
}

/* Make zebra config */
function frr_generate_config_zebra() {
	global $config, $frr_config_base, $frr_auto_config_warning;
	/* Populate FRR Global Settings */
	if (is_array($config['installedpackages']['frr']['config'])) {
		$frr_conf = &$config['installedpackages']['frr']['config'][0];
	}

	if (isset($config['installedpackages']['frrglobalraw']['config'][0]['zebra']) &&
	    !empty($config['installedpackages']['frrglobalraw']['config'][0]['zebra'])) {
		// if there is a raw config specifyed in tthe config.xml use that instead of the assisted config
		$zebraconffile = str_replace("\r", "", base64_decode($config['installedpackages']['frrglobalraw']['config'][0]['zebra']));
	} else {
		/* If there is no raw configuration and no GUI configuration, stop. */
		if (empty($frr_conf)) {
			log_error("FRR Zebra: No config data found.");
			return;
		}
		if (empty($frr_conf['enable'])) {
			/* FRR master switch is off. */
			return;
		}
		$zebraconffile = $frr_auto_config_warning;
		/* Management Password */
		if ($frr_conf['password']) {
			$zebraconffile .= "password {$frr_conf['password']}\n";
		}
		/* Logging Options */
		if ($frr_conf['logging']) {
			$zebraconffile .= "log syslog\n";
		}

		/* Access Lists */
		$zebraconffile .= frr_zebra_generate_accesslists();
		/* Prefix Lists */
		$zebraconffile .= frr_zebra_generate_prefixlists();
		/* Route Maps */
		/* Per-protocol applications of Route Maps*/
		$zebraconffile .= frr_zebra_generate_routemaps();
		/* Null Routes */
		$zebraconffile .= frr_zebra_generate_nullroutes();
		/* Static Routes */
		$zebraconffile .= frr_zebra_generate_staticroutes();
		/* Default Accept Filters */
		$zebraconffile .= frr_zebra_generate_acceptfilters();
	}
	file_put_contents("{$frr_config_base}/zebra.conf", $zebraconffile);
}
