<?php
/*
 * frr_zebra.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2017-2025 Rubicon Communications, LLC (Netgate)
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

/* Fetch a list of lists for use in selection lists */
function frr_get_lists_list(&$listconfig, $ipv4 = true, $ipv6 = true) {
	$list = array();
	$list[] = PKG_FRR_LIST_NONE_VALUE;

	if (is_array($listconfig)) {
		foreach ($listconfig as $lst) {
			$name = empty($lst['descr']) ? "{$lst['iptype']}: {$lst['name']}" : "{$lst['iptype']}: {$lst['name']} - {$lst['descr']}";
			if (($ipv4 && (strtolower($lst['iptype']) == 'ipv4')) || ($ipv6 && (strtolower($lst['iptype']) == 'ipv6'))) {
				$list[] = array("name" => $name, "value" => $lst['name']);
			}
			unset($name);
		}
	}
	return ($list);
}

/* Fetch the list of access lists for use in selection lists */
function frr_get_accesslist_list($ipv4 = true, $ipv6 = true) {
	$frr_acls_conf = config_get_path('installedpackages/frrglobalacls/config', []);
	if (!empty($frr_acls_conf)) {
		return (frr_get_lists_list($frr_acls_conf, $ipv4, $ipv6));
	} else {
		return array(PKG_FRR_LIST_NONE_VALUE);
	}
}

/* Fetch the list of prefix lists for use in selection lists */
function frr_get_prefixlist_list($ipv4 = true, $ipv6 = true) {
	$frr_prefixes_conf = config_get_path('installedpackages/frrglobalprefixes/config', []);
	if (!empty($frr_prefixes_conf)) {
		return (frr_get_lists_list($frr_prefixes_conf, $ipv4, $ipv6));
	} else {
		return array(PKG_FRR_LIST_NONE_VALUE);
	}
}

/* Fetch the list of route maps for use in selection lists */
function frr_get_routemap_list($exitaction = false, $yesno = false) {
	$list = array();

	/* Some selections can be enabled/disabled without a specific value,
	 * for those, offer a "yes" or "no" choice. */
	if ($yesno) {
		$list[] = array("name" => "No", "value" => "no");
		$list[] = array("name" => "IPv4", "value" => "ipv4");
		$list[] = array("name" => "IPv6", "value" => "ipv6");
		$list[] = array("name" => "IPv4+IPv6", "value" => "yes");
	} else {
		$list[] = PKG_FRR_LIST_NONE_VALUE;
	}

	/* If this is an exit action, return 'next' as a possible choice. */
	if ($exitaction) {
		$list[] = array("name" => "Next", "value" => "next");
	}

	/* Build a list of route maps. If this is an exit action, return
	 * sequence numbers of entries as the value, not names */
	foreach (config_get_path('installedpackages/frrglobalroutemaps/config', []) as $rm) {
		if ($exitaction) {
			$list[] = array("name" => $rm['seq'], "value" => $rm['seq']);
		} else {
			$name = empty($rm['descr']) ? $rm['name'] : "{$rm['name']} - {$rm['descr']}";
			$list[] = array("name" => $name, "value" => $rm['name']);
			unset($name);
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
	$list = array();
	$list[] = PKG_FRR_LIST_NONE_VALUE;
	$list[] = array("name" => "Local (match only)", "value" => "local");
	$list[] = array("name" => "Unchanged (set only)", "value" => "unchanged");
	$list[] = array("name" => "Peer Address (set only)", "value" => "peer-address");
	$list = array_merge_recursive($list, frr_get_bgp_neighbor_list(false, true));
	return $list;
}

function frr_generate_acl_hostspec($address, $any, $acl_type) {
	$hostspec = "";

	if ($any) {
		$hostspec = "any";
	} else {
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
				}
				break;
			case "zebra":
				/* Networks are "x.x.x.x/YY" (CIDR) */
				if (is_subnet($address)) {
					$hostspec = $address;
				}
				break;
			default:
				$hostspec = "";
		}
	}

	return $hostspec;
}

/* Access lists for the zebra configuration based on GUI config */
function frr_zebra_generate_accesslists($ipv4 = true, $ipv6 = true) {
	global $frr_config_base;
	/* Populate FRR Global Settings */
	$frr_acls_conf = config_get_path('installedpackages/frrglobalacls/config', []);
	if (empty($frr_acls_conf)) {
		/* No ACL config, stop. */
		return null;
	}

	/* Section header */
	$aclconf = "";
	/* Loop through ACLs and process */
	frr_array_name_seq_sort($frr_acls_conf);
	foreach ($frr_acls_conf as $acl) {
		/* Skip if IP version is disabled */
		if ((!$ipv4 && (strtolower($acl['iptype']) == 'ipv4')) ||
		    (!$ipv6 && (strtolower($acl['iptype']) == 'ipv6'))) {
			continue;
		}
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
			switch($acl['type']) {
				case "standard":
					/* Source address only */
					$hostspec = frr_generate_acl_hostspec($line['source'], $line['sourceany'], $acl['type']);
					break;
				case "extended":
					/* Source and Destination addresses */
					$hostspec = frr_generate_acl_hostspec($line['source'], $line['sourceany'], $acl['type']);
					$hostspec .= " ";
					$hostspec .= frr_generate_acl_hostspec($line['destination'], $line['destinationany'], $acl['type']);
					break;
				case "zebra":
					/* Source address only */
					$hostspec = frr_generate_acl_hostspec($line['source'], $line['sourceany'], $acl['type']);
					/* If the source is IPv6, mark this ACL as being IPv6 */
					if (strtolower($acl['iptype']) == 'ipv6') {
						$ipv6 = "ipv6 ";
					}
					break;
				default:
					/* If this block is reached, the ACL Type was not valid, so skip this entry. */
					continue 2;
			}
			if (empty($hostspec)) {
				/* Nothing to do here, there was no valid source or destination for this line/type, so omit the entire line. */
				continue;
			}
			$aclconf .= "{$ipv6}access-list {$acl['name']}";
			if(!empty($line['seq'])) {
				$aclconf .= " seq {$line['seq']}";
			}
			$aclconf .=  " {$line['action']} ";
			/* Extended ACLs need "ip" before the src/dst specs */
			if ($acl['type'] == "extended") {
				$aclconf .= "ip ";
			}
			/* Add on the source/destination */
			$aclconf .= $hostspec;
			/* Add exact-match only for Zebra ACLs */
			if (($acl['type'] == "zebra") && isset($line['exactmatch'])) {
				$aclconf .= " exact-match";
			}
			$aclconf .= "\n";
		}
		/* Add the ACL Description in the configuration as a remark (limit 100 chars) */
		if (!empty($acl['descr'])) {
			$acl['descr'] = substr($acl['descr'], 0, 100);
			$aclconf .= "{$ipv6}access-list {$acl['name']} remark {$acl['descr']}\n";
		}
	}

	/* Append section seperator if not empty */
	if (!empty($aclconf)) {
		$aclconf .= "!\n";
	}

	return $aclconf;
}

/* Generate BGP AS Path configuration lines */
function frr_zebra_generate_aspaths() {
	global $frr_config_base;

	/* Do not generate AS Path if BGP daemon is disabled */
	if (!config_path_enabled('installedpackages/frrbgp/config/0')) {
		return null;
	}

	/* Populate FRR AS Path Settings */
	$frr_asp_conf = config_get_path('installedpackages/frrbgpaspaths/config', []);
	if (empty($frr_asp_conf)) {
		/* No AS Path config, stop. */
		return null;
	}

	/* Section header */
	$aspconf = "";
	/* Loop through AS Paths and process */
	foreach ($frr_asp_conf as $asp) {
		/* Sort by sequence if needed */
		frr_array_seq_sort($asp['row']);
		foreach ($asp['row'] as $line) {
			$aspconf .= "bgp as-path access-list {$asp['name']} {$line['action']} {$line['regex']}\n";
		}
	}
	return $aspconf;
}

/* Prefix Lists for the zebra configuration based on GUI config */
function frr_zebra_generate_prefixlists($ipv4 = true, $ipv6 = true) {
	global $frr_config_base;

	/* Populate FRR Global Settings */
	$frr_prefixes_conf = config_get_path('installedpackages/frrglobalprefixes/config', []);
	if (empty($frr_prefixes_conf)) {
		/* No Prefix List config, stop. */
		return null;
	}

	$plconf = "";

	/* Loop through Prefix Lists and process */
	frr_array_name_seq_sort($frr_prefixes_conf);
	foreach ($frr_prefixes_conf as $pl) {
		/* Skip if IP version is disabled */
		if ((!$ipv4 && (strtolower($pl['iptype']) == 'ipv4')) ||
		    (!$ipv6 && (strtolower($pl['iptype']) == 'ipv6'))) {
			continue;
		}

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
			if (strtolower($pl['iptype']) == 'ipv6') {
				$iptype = "ipv6";
			} elseif ((strtolower($pl['iptype']) == 'ipv4') || ($line['source'] == "any")) {
				$iptype = "ip";
			} else {
				/* If the source is empty or not a subnet, the line is invalid so skip it. */
				continue;
			}

			/* Override the source if the any checkbox is selected */
			if ($line['any']) {
				$line['source'] = "any";
			}

			$plconf .= "{$iptype} prefix-list {$pl['name']} ";
			if (!empty($line['seq'])) {
				$plconf .= "seq {$line['seq']} ";
			}
			$plconf .= "{$line['action']} {$line['source']} ";
			/* Minimum Prefix (greater than or equal to) */
			if ($line['ge'] && !$line['any']) {
				$plconf .= "ge {$line['ge']} ";
			}
			/* Maximum Prefix (less than or equal to) */
			if ($line['le'] && !$line['any']) {
				$plconf .= "le {$line['le']} ";
			}
			$plconf .= "\n";
		}
		/* Add the PL Description in the configuration as a remark (limit 80 chars) */
		if (!empty($pl['descr'])) {
			$pl['descr'] = substr($pl['descr'], 0, 80);
			$plconf .= "{$iptype} prefix-list {$pl['name']} description {$pl['descr']}\n";
		}
	}

	/* Append section seperator if not empty */
	if (!empty($plconf)) {
		$plconf .= "!\n";
	}

	return $plconf;
}

/* Generate BGP Community List configuration lines */
function frr_zebra_generate_communitylists() {
	global $frr_config_base, $frr_well_known_communities;

	/* Do not generate Community List if BGP daemon is disabled */
	if (!config_path_enabled('installedpackages/frrbgp/config/0')) {
		return null;
	}

	/* Populate FRR Community List Settings */
	$frr_cls_conf = config_get_path('installedpackages/frrbgpcommunities/config', []);
	if (empty($frr_cls_conf)) {
		/* No CL config, stop. */
		return null;
	}

	$clconf = "";
	/* Loop through CLs and process */
	foreach ($frr_cls_conf as $cl) {
		/* Get the type, either by number or explicitly */
		$cl_type = frr_get_community_type($cl['name'], $cl['listtype']);

		/* Number community names can go in as-is, but text names must explicitly state the type. */
		if (is_numeric($cl['name'])) {
			$name = $cl['name'];
		} elseif (frr_validate_word($cl['name'])) {
			$name = "{$cl['listtype']} {$cl['name']}";
		}
		/* Sort by sequence if needed */
		frr_array_seq_sort($cl['row']);
		foreach ($cl['row'] as $line) {
			/* This is a space-separated list, so check each entry. */
			if (($cl_type == "standard") && !frr_validate_community_asval_list($line['community'])) {
				/* If this list does not have a valid entry, skip the entire list definition */
				continue 2;
			}
			$clconf .= "bgp community-list {$name} {$line['action']} {$line['community']}\n";
		}
	}
	return $clconf;
}

/* Route Maps for the zebra configuration based on GUI config */
function frr_zebra_generate_routemaps() {
	global $frr_config_base;

	/* Populate FRR Global Settings */
	$frr_routemap_conf = config_get_path('installedpackages/frrglobalroutemaps/config', []);
	if (empty($frr_routemap_conf)) {
		/* No route map config, stop. */
		return null;
	}

	$rmconf = "";

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

		/* Description */
		if (!empty($rm['descr'])) {
			$rmconf .= " description {$rm['descr']}\n";
		}

		/* Logic Control */
		if (in_array($rm['call'], frr_get_list_values(frr_get_routemap_list())) &&
			($rm['call'] != "none")) {
			$rmconf .= " call {$rm['call']}\n";
		}
		if (isset($rm['exitaction']) &&
			($rm['exitaction'] == "next")) {
			$rmconf .= " on-match next\n";
		} elseif (in_array($rm['exitaction'], frr_get_list_values(frr_get_routemap_list(true))) &&
			($rm['exitaction'] != "none")) {
			$rmconf .= " on-match goto {$rm['exitaction']}\n";
		}

		/* Access List */
		if (($rm['acl_match'] != "none")) {
			if (in_array($rm['prefix_match'], frr_get_list_values(frr_get_prefixlist_list(true, false)))) {
				$rmconf .= " match ip address {$rm['acl_match']}\n";
			} elseif (in_array($rm['prefix_match'], frr_get_list_values(frr_get_prefixlist_list(false, true)))) {
				$rmconf .= " match ipv6 address {$rm['acl_match']}\n";
			}
		}

		/* Prefix List */
		if (($rm['prefix_match'] != "none")) {
			if (in_array($rm['prefix_match'], frr_get_list_values(frr_get_prefixlist_list(true, false)))) {
				$rmconf .= " match ip address prefix-list {$rm['prefix_match']}\n";
			} elseif (in_array($rm['prefix_match'], frr_get_list_values(frr_get_prefixlist_list(false, true)))) {
				$rmconf .= " match ipv6 address prefix-list {$rm['prefix_match']}\n";
			}
		}

		/* Next Hop */
		switch ($rm['nexthop_action']) {
			case "match-peer":
				if (in_array($rm['nexthop_peer'], frr_get_list_values(frr_get_routemap_nexthop_list())) &&
					(($rm['nexthop_peer'] == "local") || is_ipaddr($rm['nexthop_peer']))) {
					$rmconf .= " match peer {$rm['nexthop_peer']}\n";
				}
				break;
			case "match-acl":
				if (in_array($rm['nexthop_acl'], frr_get_list_values(frr_get_accesslist_list())) &&
					($rm['nexthop_acl'] != "none")) {
					$rmconf .= " match ip next-hop {$rm['nexthop_acl']}\n";
				}
				break;
			case "match-pl":
				if (in_array($rm['nexthop_pl'], frr_get_list_values(frr_get_prefixlist_list())) &&
					($rm['nexthop_pl'] != "none")) {
					$rmconf .= " match ip next-hop prefix-list {$rm['nexthop_pl']}\n";
				}
				break;
			case "set":
				if (in_array($rm['nexthop_peer'], frr_get_list_values(frr_get_routemap_nexthop_list())) &&
					(($rm['nexthop_peer'] == "peer-address") ||
					($rm['nexthop_peer'] == "unchanged") ||
					is_ipaddr($rm['nexthop_peer']))) {
					$rmconf .= " set ip next-hop {$rm['nexthop_peer']}\n";
				}
				break;
			default:
		}

		/* Metric */
		switch ($rm['metric_action']) {
			case "match":
				if (frr_validate_ulong($rm['metric_value'])) {
					$rmconf .= " match metric {$rm['metric_value']}\n";
				}
				break;
			case "set":
				if (strlen($rm['metric_value']) > 0) {
					$rmconf .= " set metric {$rm['metric_value']}\n";
				}
				break;
			case "settype1":
				if (strlen($rm['metric_value']) > 0) {
					$rmconf .= " set metric {$rm['metric_value']}\n";
					$rmconf .= " set metric-type type-1\n";
				}
				break;
			case "settype2":
				if (strlen($rm['metric_value']) > 0) {
					$rmconf .= " set metric {$rm['metric_value']}\n";
					$rmconf .= " set metric-type type-2\n";
				}
				break;
			default:
		}

		/* Weight */
		if (frr_validate_ulong($rm['weight'])) {
			$rmconf .= " set weight {$rm['weight']}\n";
		}

		/* Local Preference */
		switch ($rm['localpref_action']) {
			case "match":
				if (frr_validate_ulong($rm['localpref_value'])) {
					$rmconf .= " match local-preference {$rm['localpref_value']}\n";
				}
				break;
			case "set":
				if (frr_validate_ulong($rm['localpref_value'])) {
					$rmconf .= " set local-preference {$rm['localpref_value']}\n";
				}
				break;
			default:
		}

		/* BGP AS Paths */
		switch ($rm['aspath_action']) {
			case "match":
				if (in_array($rm['aspath_match'], frr_get_list_values(frr_get_bgp_aspath_list()))) {
					$rmconf .= " match as-path {$rm['aspath_match']}\n";
				}
				break;
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
				$rmconf .= " set as-path " . substr($rm['aspath_action'], 3) . " {$rm['aspath_value']}\n";
				break;
			case "setprependlast":
				/* For Last-AS, the peer AS is prepended and only a single number is allowed
				 * from 1-10 which specifies the number of times to prepend the peer's AS number. */
				if (frr_validate_intrange($rm['aspath_value'], 1, 10)) {
					$rmconf .= " set as-path prepend last-as {$rm['aspath_value']}\n";
				}
				break;
			default:
		}

		/* BGP Communities */
		switch ($rm['community_action']) {
			case "match":
			case "matchexact":
				if (in_array($rm['community_match'], frr_get_list_values(frr_get_bgp_community_list()))) {
					$rmconf .= " match community {$rm['community_match']}";
					if ($rm['community_action'] == "matchexact") {
						$rmconf .= " exact-match";
					}
					$rmconf .= "\n";
				}
				break;
			case "set":
				if (frr_validate_community_asval_list($rm['community_set'])) {
					$rmconf .= " set community {$rm['community_set']} ";
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
			$rmconf .= " set origin {$rm['origin_value']}\n";
		}

		/* Source Protocol */
		if (frr_validate_bgp_sourceproto($rm['source_proto'])) {
			$rmconf .= " match source-protocol {$rm['source_proto']}\n";
		}

		/* Tags */
		if (frr_validate_asnum($rm['tag_value'])) {
			switch ($rm['tag_action']) {
				case "match":
					$rmconf .= " match tag {$rm['tag_value']}\n";
					break;
				case "set":
					$rmconf .= " set tag {$rm['tag_value']}\n";
					break;
				default:
			}
		}

		/* RPKI */
		if (!empty($rm['rpki_state']) && ($rm['rpki_state'] != 'none')) {
			$rmconf .= " match rpki {$rm['rpki_state']}\n";
		}

	}

	/* Append section seperator if not empty */
	if (!empty($rmconf)) {
		$rmconf .= "!\n";
	}

	return $rmconf;
}

/* Null Routes for the zebra configuration based on GUI config */
function frr_zebra_generate_nullroutes() {
	global $frr_config_base;

	/* Populate FRR Global Settings */
	$frr_conf = config_get_path('installedpackages/frr/config/0/row', []);
	if (empty($frr_conf)) {
		/* If FRR is not configured or the route handling list is empty, stop. */
		return null;
	}

	/* Check FRR configuration network list for null route entries */
	foreach ($frr_conf as $redistr) {
		if (empty($redistr)) {
			continue;
		}
		if (isset($redistr['nullroute']) && is_subnet($redistr['routevalue'])) {
			$ipproto = is_subnetv4($redistr['routevalue']) ? 'ip' : 'ipv6';
			$nullroutes .= "{$ipproto} route {$redistr['routevalue']} Null0\n";
		}
	}
	if (!empty($nullroutes)) {
		return "" . $nullroutes;
	} else {
		return null;
	}
}

/* Static Routes for zebra configuration based on GUI config */
function frr_zebra_generate_staticroutes() {
	require_once("gwlb.inc");
	global $frr_config_base;
	/* Populate FRR Global Settings */
	$frr_conf = config_get_path('installedpackages/frr/config/0/row', []);
	if (empty($frr_conf)) {
		/* If FRR is not configured or the route handling list is empty, stop. */
		return null;
	}

	/* Check FRR configuration network list for null route entries */
	foreach ($frr_conf as $redistr) {
		if (empty($redistr)) {
			continue;
		}
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
					if (is_subnetv6($redistr['routevalue'])) {
						$target = str_replace("%", " ", $target);
					}
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
					continue 2;
			}
			if (!empty($target)) {
				$ipproto = is_subnetv4($redistr['routevalue']) ? 'ip' : 'ipv6';
				$staticroutes .= "{$ipproto} route {$redistr['routevalue']} {$target}\n";
			}
		}
	}
	if (!empty($staticroutes)) {
		return "" . $staticroutes;
	} else {
		return null;
	}
}

/* Generate a convenience list of accept filters */
function frr_zebra_generate_acceptfilters() {
	global $frr_config_base;
	$seq = 10;
	$seq6 = 10;
	$noaccept = '';
	$noaccept6 = '';

	/* Check OSPF Interfaces for automatic accept filters */
	foreach (config_get_path('installedpackages/frrospfdinterfaces/config', []) as $conf) {
		if (empty($conf)) {
			continue;
		}
		list($interface_ip, $interface_subnet, $subnet) = frr_get_interfaceinfo($conf['interface']);
		if (empty($interface_ip)) {
			continue;
		}
		if (!empty($conf['acceptfilter']) &&
		    is_ipaddrv4($subnet) &&
		    is_numericint($interface_subnet)) {
			$noaccept = $noaccept ?: "";
			$noaccept .= "ip prefix-list ACCEPTFILTER seq {$seq} deny {$subnet}/{$interface_subnet}\n";
			$seq += 10;
			$noaccept .= "ip prefix-list ACCEPTFILTER seq {$seq} deny {$interface_ip}/32\n";
			$seq += 10;
		}
	}

	/* Check FRR configuration network list for accept filter entries */
	$frr_conf = config_get_path('installedpackages/frr/config/0/row', []);
	if (empty($frr_conf)) {
		/* If FRR is not configured or the route handling list is empty, stop. */
		return null;
	}

	foreach (config_get_path('installedpackages/frr/config/0/row', []) as $redistr) {
		if (empty($redistr)) {
			continue;
		}
		if (isset($redistr['acceptfilter'])) {
			if (is_subnetv4($redistr['routevalue'])) {
				$noaccept .= "ip prefix-list ACCEPTFILTER seq {$seq} deny {$redistr['routevalue']}\n";
				$seq += 10;
			} else {
				$noaccept6 .= "ipv6 prefix-list ACCEPTFILTER6 seq {$seq6} deny {$redistr['routevalue']}\n";
				$seq6 += 10;
			}
		}
	}
	if (!empty($noaccept)) {
		$noaccept .= "ip prefix-list ACCEPTFILTER seq {$seq} permit any\n";
		$noaccept .= "!\n";
		$noaccept .= "route-map ACCEPTFILTER permit 10\n";
		$noaccept .= " match ip address prefix-list ACCEPTFILTER\n";
		$noaccept .= "!\n";
		$noaccept .= "ip protocol bgp route-map ACCEPTFILTER\n";
		$noaccept .= "!\n";
		$noaccept .= "ip protocol ospf route-map ACCEPTFILTER\n";
		$noaccept .= "!\n";
	}
	if (!empty($noaccept6)) {
		$noaccept .= $noaccept6;
		$noaccept .= "ipv6 prefix-list ACCEPTFILTER6 seq {$seq6} permit any\n";
		$noaccept .= "!\n";
		$noaccept .= "route-map ACCEPTFILTER6 permit 10\n";
		$noaccept .= " match ipv6 address prefix-list ACCEPTFILTER6\n";
		$noaccept .= "!\n";
		$noaccept .= "ipv6 protocol bgp route-map ACCEPTFILTER6\n";
		$noaccept .= "!\n";
		$noaccept .= "ipv6 protocol ospf6 route-map ACCEPTFILTER6\n";
		$noaccept .= "!\n";
	}
	return $noaccept;
}

/* Make zebra config */
function frr_generate_config_zebra() {
	global $frr_config_base, $frr_integrated_config;

	/* Populate FRR Global Settings */
	$frr_conf = config_get_path('installedpackages/frr/config/0', []);
	if (empty($frr_conf)) {
		frr_package_log("Zebra: No config data found.");
		return;
	}
	if (!array_key_exists('enable', $frr_conf) ||
	    empty($frr_conf['enable'])) {
		/* FRR master switch is off. */
		return;
	}

	$frr_integrated_config['zebra'] = "";
	/* Access Lists IPv4*/
	$frr_integrated_config['zebra'] .= frr_zebra_generate_accesslists(true, false);
	/* Prefix Lists IPv4 */
	$frr_integrated_config['zebra'] .= frr_zebra_generate_prefixlists(true, false);
	/* Access Lists IPv6*/
	$frr_integrated_config['zebra'] .= frr_zebra_generate_accesslists(false, true);
	/* Prefix Lists IPv6 */
	$frr_integrated_config['zebra'] .= frr_zebra_generate_prefixlists(false, true);
	/* BGP AS Paths */
	$frr_integrated_config['zebra'] .= frr_zebra_generate_aspaths();
	/* BGP Community Lists */
	$frr_integrated_config['zebra'] .= frr_zebra_generate_communitylists();
	/* Route Maps */
	$frr_integrated_config['zebra'] .= frr_zebra_generate_routemaps();
	/* Default Accept Filters */
	$frr_integrated_config['zebra'] .= frr_zebra_generate_acceptfilters();
	/* Null Routes */
	$frr_integrated_config['staticd'] .= frr_zebra_generate_nullroutes();
	/* Static Routes */
	$frr_integrated_config['staticd'] .= frr_zebra_generate_staticroutes();

	/* Trim trailing seperator */
	if (strcmp(substr($frr_integrated_config['zebra'], -2), "!\n") == 0) {
		$frr_integrated_config['zebra'] = substr($frr_integrated_config['zebra'],0, -2);
	}
}

/* The following function checks for the presence of and writes the contents of "/var/etc/frr/$module.conf" into the $module . "running" field within the config.xml file  */
function read_frr_running_config() {
	global $frr_config_base;

	$frrRunningFile = "{$frr_config_base}/frr.conf";
	if (file_exists($frrRunningFile) && (filesize($frrRunningFile) > 0)) {
		$moduleRunning = fopen("$frrRunningFile", "r");
		config_set_path('installedpackages/frrglobalraw/config/0/frrrunning',
				base64_encode(fread($moduleRunning, filesize($frrRunningFile))));
	} else {
		config_set_path('installedpackages/frrglobalraw/config/0/frrrunning',
				base64_encode("# file frr.conf does not exist or is empty."));
	}
	write_config(gettext("FRR: Saved running configuration into pfSense config file."), false, true);
}
