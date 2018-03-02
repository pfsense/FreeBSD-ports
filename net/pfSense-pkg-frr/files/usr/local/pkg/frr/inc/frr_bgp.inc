<?php
/*
 * frr_bgp.inc
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

/* Fetch the list of BGP AS Paths for use in selection lists */
function frr_get_bgp_aspath_list() {
	global $config;
	$list = array();
	$list[] = array("name" => "None", "value" => "none");
	if (is_array($config['installedpackages']['frrbgpaspaths']['config'])) {
		foreach ($config['installedpackages']['frrbgpaspaths']['config'] as $asp) {
			$list[] = array("name" => "{$asp['name']} - {$asp['descr']}", "value" => $asp['name']);
		}
	}
	return ($list);
}

/* Fetch the list of BGP Communities for use in selection lists */
function frr_get_bgp_community_list() {
	global $config, $frr_well_known_communities;
	$list = array();
	$list[] = array("name" => "None", "value" => "none");

	foreach ($frr_well_known_communities as $wkc) {
		$tmp["name"] = $wkc;
		$tmp["value"] = $wkc;
		$list[] = $tmp;
	}

	if (is_array($config['installedpackages']['frrbgpcommunities']['config'])) {
		foreach ($config['installedpackages']['frrbgpcommunities']['config'] as $cl) {
			$list[] = array("name" => "{$cl['name']} - {$cl['descr']}", "value" => $cl['name']);
		}
	}

	return ($list);
}

/* Fetch the list of BGP Neighbor addresses for use in selection lists */
function frr_get_bgp_neighbor_list($grouponly = false, $peeronly = false, $includenone = true, $nameprefix = "", $valueprefix = "") {
	global $config;
	$list = array();
	if ($includenone) {
		$list[] = array("name" => "{$nameprefix}None", "value" => "{$valueprefix}none");
	}

	/* Build the list, and if only groups are wanted, return a list of only
	 * peers with names and not IP addresses. If only peers are wanted, only
	 * return a list that contains IP addresses. */
	if (is_array($config['installedpackages']['frrbgpneighbors']['config'])) {
		foreach ($config['installedpackages']['frrbgpneighbors']['config'] as $nei) {
			if (($grouponly && is_ipaddr($nei['peer'])) ||
			    ($peeronly && !is_ipaddr($nei['peer']))) {
				continue;
			}
			if (!empty($nei['descr'])) {
				$nei['descr'] = " - {$nei['descr']}";
			}
			$list[] = array("name" => "{$nameprefix}{$nei['peer']}{$nei['descr']}", "value" => $valueprefix . $nei['peer']);
		}
	}

	return ($list);
}

/* Make an associative array of neighbors with the peer name or IP address
 * as the key */
function frr_get_neighbors_with_name_key() {
	global $config;
	$allneighbors = array();
	if (is_array($config['installedpackages']['frrbgpneighbors']['config'])) {
		foreach ($config['installedpackages']['frrbgpneighbors']['config'] as $neighbor) {
			$allneighbors[$neighbor['peer']] = $neighbor;
		}
	}
	return $allneighbors;
}

/* Find the IP address for a local update source which could be a VIP/IP address,
 * or an interface name. */
function frr_get_neighbor_updatesource($source, $type) {
	if (in_array($source, frr_get_list_values(frr_get_interfaces())) &&
	    ($source != "default")) {
		/* If this is a VIP, split off the VIP ID from the IP address. */
		if (substr($source, 0, 4) == "_vip") {
			list ($vipid, $source) = explode('|', $source);
		}
		/* If the source is already an IP address (v4 or v6) from a VIP,
		 * return it. */
		if (is_ipaddr($source)) {
			return $source;
		} else {
			/* If it reached here, this must be an interface name,
			 * so find the address that way */
			if ($type == "ipv6") {
				return get_interface_ipv6($source);
			} else {
				return get_interface_ip($source);
			}
		}
	}
	/* Whatever was passed must not be a valid source */
	return "";
}

/* Generate BGP Router configuration lines */
function frr_bgp_generate_router() {
	global $config, $frr_config_base;
	/* Populate FRR Settings */
	if (is_array($config['installedpackages']['frr']['config'])) {
		$frr_conf = &$config['installedpackages']['frr']['config'][0];
	} else {
		/* If FRR is not configured, stop. */
		return null;
	}
	/* Populate FRR BGP Settings */
	if (is_array($config['installedpackages']['frrbgp']['config'])) {
		$frr_bgp_conf = &$config['installedpackages']['frrbgp']['config'][0];
	} else {
		/* No BGP config, stop. */
		return null;
	}
	$bgpconf = "\n# BGP Config\n";

	/* BGP Router Options */
	if (frr_validate_asnum($frr_bgp_conf['asnum'])) {
		$bgpconf .= "router bgp {$frr_bgp_conf['asnum']}\n";
	} else {
		return null;
	}
	/* Log adjacency changes */
	if (!empty($frr_bgp_conf['adjacencylog'])) {
		$bgpconf .= "  bgp log-neighbor-changes\n";
	}
	/* If the router ID is defined in BGP, use that, otherwise try to use
	 * the global router ID, if one is set. */
	if (is_ipaddrv4($frr_bgp_conf['routerid'])) {
		$bgpconf .= "  bgp router-id {$frr_bgp_conf['routerid']}\n";
	} elseif (is_ipaddrv4($frr_conf['routerid'])) {
		$bgpconf .= "  bgp router-id {$frr_conf['routerid']}\n";
	}

	/* Timers */

	/* Set keep-alive and holdtime, both MUST be set together */
	if ((frr_validate_intrange($frr_bgp_conf['timers_keepalive'], 0, 65535)) &&
	    (frr_validate_intrange($frr_bgp_conf['timers_holdtime'], 0, 65535))) {
		$bgpconf .= "  timers bgp {$frr_bgp_conf['timers_keepalive']} {$frr_bgp_conf['timers_holdtime']}\n";
	}
	/* Set update-delay and peer wait time */
	if (frr_validate_intrange($frr_bgp_conf['timers_updatedelay'], 0, 3600)) {
		$bgpconf .= "  update-delay {$frr_bgp_conf['timers_updatedelay']}";

		/* Peer wait time can only be specified if update-delay is also specified
		 * Peer wait time must be less than or equal to update-delay */
		if (frr_validate_intrange($frr_bgp_conf['timers_peerwait'], 1, 3600) &&
		    ($frr_bgp_conf['timers_peerwait'] <= $frr_bgp_conf['timers_updatedelay'])) {
			$bgpconf .= " {$frr_bgp_conf['timers_peerwait']}";
		}
		$bgpconf .= "\n";
	}

	/* (Re)distribute */
	/* TODO: Candidate for moving to address-family */
	/* In each of these cases, if the user selected "yes" then the action is
	 * enabled. If a route-map was chosen instead, it is added at the end. */
	if (!empty($frr_bgp_conf['redistributeconnectedsubnets']) && ($frr_bgp_conf['redistributeconnectedsubnets'] != "no")) {
		$bgpconf .= "  redistribute connected";
		if (($frr_bgp_conf['redistributeconnectedsubnets'] != "yes") &&
		    in_array($frr_bgp_conf['redistributeconnectedsubnets'], frr_get_list_values(frr_get_routemap_list(false, true)))) {
			$bgpconf .= " route-map {$frr_bgp_conf['redistributeconnectedsubnets']}";
		}
		$bgpconf .= "\n";
	}
	if (!empty($frr_bgp_conf['redistributestatic']) && ($frr_bgp_conf['redistributestatic'] != "no")) {
		$bgpconf .= "  redistribute static";
		if (($frr_bgp_conf['redistributestatic'] != "yes") &&
		    in_array($frr_bgp_conf['redistributestatic'], frr_get_list_values(frr_get_routemap_list(false, true)))) {
			$bgpconf .= " route-map {$frr_bgp_conf['redistributestatic']}";
		}
		$bgpconf .= "\n";
	}
	if (!empty($frr_bgp_conf['redistributekernel']) && ($frr_bgp_conf['redistributekernel'] != "no")) {
		$bgpconf .= "  redistribute kernel";
		if (($frr_bgp_conf['redistributekernel'] != "yes") &&
		    in_array($frr_bgp_conf['redistributekernel'], frr_get_list_values(frr_get_routemap_list(false, true)))) {
			$bgpconf .= " route-map {$frr_bgp_conf['redistributekernel']}";
		}
		$bgpconf .= "\n";
	}
	if (!empty($frr_bgp_conf['redistributeospf']) && ($frr_bgp_conf['redistributeospf'] != "no")) {
		$bgpconf .= "  redistribute ospf";
		if (($frr_bgp_conf['redistributeospf'] != "yes") &&
		    in_array($frr_bgp_conf['redistributeospf'], frr_get_list_values(frr_get_routemap_list(false, true)))) {
			$bgpconf .= " route-map {$frr_bgp_conf['redistributeospf']}";
		}
		$bgpconf .= "\n";
	}

	/* Network Distribution */
	/* TODO: Candidate for moving to address-family */

	/* Loop through the defined network list and create appropriate entries,
	 * using address-family directives for IPv4 or IPv6 unicast as needed
	 */
	foreach ($frr_bgp_conf['row'] as $net) {
		/* Networks and route-map */
		if (is_subnet($net['distributeroutevalue']) || is_ipaddr($net['distributeroutevalue'])) {
			$bgpconf .= "  address-family";
			if (is_subnetv4($net['distributeroutevalue'])) {
				$bgpconf .= " ipv4";
			} elseif (is_subnetv6($net['distributeroutevalue'])) {
				$bgpconf .= " ipv6";
			}
			$bgpconf .= " unicast\n";
			$bgpconf .= "   network {$net['distributeroutevalue']}";
			if (($net['distributeroutemap'] != "none") &&
			    in_array($net['distributeroutemap'], frr_get_list_values(frr_get_routemap_list()))) {
				$bgpconf .= " route-map {$net['distributeroutemap']}";
			}
			$bgpconf .= "\n";
			$bgpconf .= "  exit-address-family \n";
		}
	}

	return $bgpconf;
}

/* Generate BGP Router Advanced configuration lines */
function frr_bgp_generate_routeradvanced() {
	global $config, $frr_config_base;
	/* Populate FRR BGP Advanced Settings */
	if (is_array($config['installedpackages']['frrbgpadvanced']['config'])) {
		$frr_bgpadv_conf = &$config['installedpackages']['frrbgpadvanced']['config'][0];
	} else {
		/* No BGP Advanced config, stop. */
		return null;
	}
	$advconf = "\n  # Advanced BGP Config\n";
	/* Advanced Options */
	if (frr_validate_ulong($frr_bgpadv_conf['default_localpref'])) {
		$advconf .= "  bgp default local-preference {$frr_bgpadv_conf['default_localpref']}\n";
	}
	/* TODO: Candidate for moving to address-family */
	if (in_array($frr_bgpadv_conf['tablemap'], frr_get_list_values(frr_get_routemap_list())) &&
	    ($frr_bgpadv_conf['tablemap'] != "none")) {
		$advconf .= "  table-map {$frr_bgpadv_conf['tablemap']}\n";
	}

	/* Advanced Timers */
	if (frr_validate_ulong($frr_bgpadv_conf['timers_coalesce'], 1)) {
		$advconf .= "  coalesce-time {$frr_bgpadv_conf['timers_coalesce']}\n";
	}
	if (frr_validate_intrange($frr_bgpadv_conf['timers_routemapdelay'], 0, 600)) {
		$advconf .= "  bgp route-map delay-timer {$frr_bgpadv_conf['timers_routemapdelay']}\n";
	}
	if (frr_validate_intrange($frr_bgpadv_conf['dampening_halflife'], 1, 45)) {
		$advconf .= "  bgp dampening {$frr_bgpadv_conf['dampening_halflife']}";
		if (frr_validate_intrange($frr_bgpadv_conf['dampening_reuse'], 1, 20000)) {
			$advconf .= " {$frr_bgpadv_conf['dampening_reuse']}";
			if (frr_validate_intrange($frr_bgpadv_conf['dampening_suppress'], 1, 20000)) {
				$advconf .= " {$frr_bgpadv_conf['dampening_suppress']}";
				if (frr_validate_intrange($frr_bgpadv_conf['dampening_suppressduration'], 1, 255)) {
					$advconf .= " {$frr_bgpadv_conf['dampening_suppressduration']}";
				}
			}
		}
		$advconf .= "\n";
	}

	/* Advanced Routing Behavior */
	if (!empty($frr_bgpadv_conf['fastexternalfailover'])) {
		$advconf .= "  no bgp fast-external-failover\n";
	}
	if (!empty($frr_bgpadv_conf['networkimportcheck'])) {
		$advconf .= "  bgp network import-check\n";
	}

	/* Route Reflecting */
	if (!empty($frr_bgpadv_conf['routereflectorallowoutboundpolicy'])) {
		$advconf .= "  bgp route-reflector allow-outbound-policy\n";
	}
	if (frr_validate_ulong($frr_bgpadv_conf['routereflectorclusterid'], 1) || is_ipaddrv4($frr_bgpadv_conf['routereflectorclusterid'])) {
		$advconf .= "  bgp cluster-id {$frr_bgpadv_conf['routereflectorclusterid']}\n";
	}
	if (!empty($frr_bgpadv_conf['routereflectorclienttoclient'])) {
		$advconf .= "  no bgp client-to-client reflection\n";
	}

	/* Aggregate Behavior -- Candidate for moving to address-family */
	foreach ($frr_bgpadv_conf['row'] as $aggr) {
		if (is_subnetv4($aggr['aggregateaddr'])) {
			$advconf .= "  aggregate-address {$aggr['aggregateaddr']}";
			if (!empty($aggr['aggregateasset'])) {
				$advconf .= " as-set";
			}
			if (!empty($aggr['aggregatesummaryonly'])) {
				$advconf .= " summary-only";
			}
			$advconf .= "\n";
		}
	}

	/* Multi-Exit Discriminator */
	if (!empty($frr_bgpadv_conf['meddeterministic'])) {
		$advconf .= "  bgp deterministic-med\n";
	}
	if (!empty($frr_bgpadv_conf['medalwayscompare'])) {
		$advconf .= "  bgp always-compare-med\n";
	}

	if (!empty($frr_bgpadv_conf['maxmedadmin'])) {
		$advconf .= "  bgp max-med administrative\n";
	} elseif (frr_validate_intrange($frr_bgpadv_conf['maxmedadminvalue'], 0, 4294967294)) {
		$advconf .= "  bgp max-med administrative {$frr_bgpadv_conf['maxmedadminvalue']}\n";
	}
	if (frr_validate_intrange($frr_bgpadv_conf['maxmedstartup'], 5, 86400)) {
		$advconf .= "  bgp max-med on-startup {$frr_bgpadv_conf['maxmedstartup']}";
		if (frr_validate_intrange($frr_bgpadv_conf['maxmedstartupvalue'], 0, 4294967294)) {
			$advconf .= " {$frr_bgpadv_conf['maxmedstartupvalue']}";
		}
		$advconf .= "\n";
	}

	/* Confederation */
	if (frr_validate_asnum($frr_bgpadv_conf['confedas'])) {
		$advconf .= "  bgp confederation identifier {$frr_bgpadv_conf['confedas']}\n";
	}

	/* Confed Peers are a space-separated list of AS numbers. If there are any invalid values, skip the entire line. */
	$add_confedpeers = true;
	foreach (explode(' ', $frr_bgpadv_conf['confedpeers']) as $confedpeer) {
		if (!frr_validate_asnum($confedpeer)) {
			$add_confedpeers = false;
		}
	}
	if ($add_confedpeers) {
		$advconf .= "  bgp confederation peers {$frr_bgpadv_conf['confedpeers']}\n";
	}

	/* Distance -- Candidate for moving to address-family (both)*/

	/* Admin distance requires both a distance value and a subnet/prefix */
	if (frr_validate_intrange($frr_bgpadv_conf['distanceadmin'], 1, 255) &&
	    is_subnetv4($frr_bgpadv_conf['distanceadminprefix'])) {
		$advconf .= "  distance {$frr_bgpadv_conf['distanceadmin']} {$frr_bgpadv_conf['distanceadminprefix']}";
		if (in_array($frr_bgpadv_conf['distanceadminacl'], frr_get_list_values(frr_get_accesslist_list())) &&
		    ($frr_bgpadv_conf['distanceadminacl'] != "none")) {
			$advconf .= " {$frr_bgpadv_conf['distanceadminacl']}";
		}
		$advconf .= "\n";
	}

	/* For BGP distance, all three values must be set. */
	if (frr_validate_intrange($frr_bgpadv_conf['bgpdistanceext'], 1, 255) &&
	    frr_validate_intrange($frr_bgpadv_conf['bgpdistanceint'], 1, 255) &&
	    frr_validate_intrange($frr_bgpadv_conf['bgpdistancelocal'], 1, 255)) {
		$advconf .= "  distance bgp {$frr_bgpadv_conf['bgpdistanceext']} {$frr_bgpadv_conf['bgpdistanceint']} {$frr_bgpadv_conf['bgpdistancelocal']}\n";
	}

	/* Best Path Selection */
	if (!empty($frr_bgpadv_conf['bgpbestpathasconfed'])) {
		$advconf .= "  bgp bestpath as-path confed\n";
	}
	if (!empty($frr_bgpadv_conf['bgpbestpathasignore'])) {
		$advconf .= "  bgp bestpath as-path ignore\n";
	}

	if (!empty($frr_bgpadv_conf['bgpbestpathasmulti'])) {
		$advconf .= "  bgp bestpath as-path multipath-relax";
		if (!empty($frr_bgpadv_conf['bgpbestpathasmultigenasset'])) {
			$advconf .= " as-set";
		}
		$advconf .= "\n";
	}

	if (!empty($frr_bgpadv_conf['bgpbestpathcomparerouterid'])) {
		$advconf .= "  bgp bestpath compare-routerid\n";
	}

	if (!empty($frr_bgpadv_conf['bgpbestpathmedconfed']) || !empty($frr_bgpadv_conf['bgpbestpathmedmissing'])) {
		$advconf .= "  bgp bestpath med";
		if (!empty($frr_bgpadv_conf['bgpbestpathmedconfed'])) {
			$advconf .= " confed";
		}
		if (!empty($frr_bgpadv_conf['bgpbestpathmedmissing'])) {
			$advconf .= " missing-as-worst";
		}
		$advconf .= "\n";
	}

	/* EBGP */
	if (!empty($frr_bgpadv_conf['disableebgpconnectedroutecheck'])) {
		$advconf .= "  bgp disable-ebgp-connected-route-check\n";
	}
	if (!empty($frr_bgpadv_conf['enforcefirstas'])) {
		$advconf .= "  bgp enforce-first-as\n";
	}

	/* Networking Behavior */
	if (frr_validate_intrange($frr_bgpadv_conf['subgrouppktqueuemax'], 20, 100)) {
		$advconf .= "  bgp default subgroup-pkt-queue-max {$frr_bgpadv_conf['subgrouppktqueuemax']}\n";
	}
	if (frr_validate_intrange($frr_bgpadv_conf['writequanta'], 1, 10000)) {
		$advconf .= "  write-quanta {$frr_bgpadv_conf['writequanta']}\n";
	}

	return $advconf;
}

/* Generate BGP Neighbor configuration lines */
function frr_bgp_generate_neighbors() {
	global $config, $frr_config_base;
	/* Populate FRR BGP Neighbor Settings */
	if (is_array($config['installedpackages']['frrbgpneighbors']['config'])) {
		$frr_neighbor_conf = &$config['installedpackages']['frrbgpneighbors']['config'];
	} else {
		/* No BGP Neighbor config, stop. */
		return null;
	}

	/* Section header */
	$nconf = "\n  # BGP Neighbors\n";

	foreach ($frr_neighbor_conf as $neighbor) {
		/* Placeholder for directives that could be global or in address-family */
		$tconf = "";
		$afconf = "";
		/* General Options */
		/* Check if this is a group or a peer */
		$ntype = "peer";
		if (!is_ipaddr($neighbor['peer']) && frr_validate_word($neighbor['peer'])) {
			$ntype = "group";
			$nconf .= "  neighbor {$neighbor['peer']} peer-group\n";
		} elseif (is_ipaddr($neighbor['peer'])) {
			if (!empty($neighbor['peergroup']) &&
			    ($neighbor['peergroup'] != 'none') &&
			    in_array($neighbor['peergroup'], frr_get_list_values(frr_get_bgp_neighbor_list(true)))) {
				$nconf .= "  neighbor {$neighbor['peer']} peer-group {$neighbor['peergroup']}\n";
				$tconf .= "  neighbor {$neighbor['peer']} peer-group {$neighbor['peergroup']}\n";
			}
			$afconf .= "  address-family";
			if (is_ipaddrv4($neighbor['peer'])) {
				$afconf .= " ipv4";
			} elseif (is_ipaddrv6($neighbor['peer'])) {
				$afconf .= " ipv6";
			}
			$afconf .= " unicast\n";
			$afconf .= "    neighbor {$neighbor['peer']} activate\n";
		} else {
			/* This is not a valid neighbor! */
			continue;
		}

		/* remote-as must come first, except for peer-group def/member line! */
		if (frr_validate_asnum($neighbor['asnum'])) {
			$nconf .= "  neighbor {$neighbor['peer']} remote-as {$neighbor['asnum']}\n";
		}

		if (!empty($neighbor['descr'])) {
			$neighbor['descr'] = substr($neighbor['descr'], 0, 80);
			$nconf .= "  neighbor {$neighbor['peer']} description {$neighbor['descr']}\n";
		}

		if (in_array($neighbor['updatesource'], frr_get_list_values(frr_get_interfaces())) &&
		    ($neighbor['updatesource'] != 'default')) {
			$updatesource = frr_get_neighbor_updatesource($neighbor['updatesource'], $neighbor['updatesource_type']);
			if (!empty($updatesource)) {
				$nconf .= "  neighbor {$neighbor['peer']} update-source {$updatesource}\n";
			}
		}

		if (!empty($neighbor['defaultoriginate']) && ($neighbor['defaultoriginate'] != "no")) {
			$tconf .= "  neighbor {$neighbor['peer']} default-originate";
			if (($neighbor['defaultoriginate'] != "yes") &&
			    in_array($neighbor['defaultoriginate'], frr_get_list_values(frr_get_routemap_list(false, true)))) {
				$tconf .= " route-map {$neighbor['defaultoriginate']}";
			}
			$tconf .= "\n";
		}

		switch ($neighbor['sendcommunity']) {
			case "disabled":
				$tconf .= "  no neighbor {$neighbor['peer']} send-community\n";
				break;
			case "enabled":
				$tconf .= "  neighbor {$neighbor['peer']} send-community\n";
				break;
			case "standard":
			case "extended":
			case "both":
				$tconf .= "  neighbor {$neighbor['peer']} send-community {$neighbor['sendcommunity']}\n";
				break;
			default:
		}

		list($pwtype_base, $pwtype_bidir) = explode('-', $neighbor['password_type'], 2);
		if (!empty($neighbor['password']) &&
		    (($pwtype_base == "frr") ||
		    ($pwtype_base == "both"))) {
			$nconf .= "  neighbor {$neighbor['peer']} password {$neighbor['password']}\n";
		}

		if ($neighbor['nexthopself'] == "enabled") {
			$tconf .= "  neighbor {$neighbor['peer']} next-hop-self\n";
		} elseif ($neighbor['nexthopself'] == "force") {
			$tconf .= "  neighbor {$neighbor['peer']} next-hop-self force\n";
		}

		if (!empty($neighbor['softreconfigurationinbound'])) {
			$tconf .= "  neighbor {$neighbor['peer']} soft-reconfiguration inbound\n";
		}

		if (frr_validate_intrange($neighbor['timers_keepalive'], 0, 65535)) {
			$nconf .= "  neighbor {$neighbor['peer']} timers {$neighbor['timers_keepalive']}";

			/* Hold time can only be specified if keep-alive is also specified */
			if (frr_validate_intrange($neighbor['timers_holdtime'], 0, 65535)) {
				$nconf .= " {$neighbor['timers_holdtime']}";
			}
			$nconf .= "\n";
		}
		if (frr_validate_intrange($neighbor['timers_connect'], 1, 65535)) {
			$nconf .= "  neighbor {$neighbor['peer']} timers connect {$neighbor['timers_connect']}\n";
		}

		/* Peer Filtering */
		if (in_array($neighbor['distribute_in'], frr_get_list_values(frr_get_accesslist_list())) &&
		    ($neighbor['distribute_in'] != "none")) {
			$tconf .= "  neighbor {$neighbor['peer']} distribute-list {$neighbor['distribute_in']} in\n";
		}
		if (in_array($neighbor['distribute_out'], frr_get_list_values(frr_get_accesslist_list())) &&
		    ($neighbor['distribute_out'] != "none")) {
			$tconf .= "  neighbor {$neighbor['peer']} distribute-list {$neighbor['distribute_out']} out\n";
		}

		if (in_array($neighbor['prefixfilter_in'], frr_get_list_values(frr_get_prefixlist_list())) &&
		    ($neighbor['prefixfilter_in'] != "none")) {
			$tconf .= "  neighbor {$neighbor['peer']} prefix-list {$neighbor['prefixfilter_in']} in\n";
		}
		if (in_array($neighbor['prefixfilter_out'], frr_get_list_values(frr_get_prefixlist_list())) &&
		    ($neighbor['prefixfilter_out'] != "none")) {
			$tconf .= "  neighbor {$neighbor['peer']} prefix-list {$neighbor['prefixfilter_out']} out\n";
		}

		if (in_array($neighbor['aspathfilter_in'], frr_get_list_values(frr_get_bgp_aspath_list())) &&
		    ($neighbor['aspathfilter_in'] != "none")) {
			$tconf .= "  neighbor {$neighbor['peer']} filter-list {$neighbor['aspathfilter_in']} in\n";
		}
		if (in_array($neighbor['aspathfilter_out'], frr_get_list_values(frr_get_bgp_aspath_list())) &&
		    ($neighbor['aspathfilter_out'] != "none")) {
			$tconf .= "  neighbor {$neighbor['peer']} filter-list {$neighbor['aspathfilter_out']} out\n";
		}

		if (in_array($neighbor['routemap_in'], frr_get_list_values(frr_get_routemap_list())) &&
		    ($neighbor['routemap_in'] != "none")) {
			$tconf .= "  neighbor {$neighbor['peer']} route-map {$neighbor['routemap_in']} in\n";
		}
		if (in_array($neighbor['routemap_out'], frr_get_list_values(frr_get_routemap_list())) &&
		    ($neighbor['routemap_out'] != "none")) {
			$tconf .= "  neighbor {$neighbor['peer']} route-map {$neighbor['routemap_out']} out\n";
		}

		if (in_array($neighbor['unsuppressmap'], frr_get_list_values(frr_get_routemap_list())) &&
		    ($neighbor['unsuppressmap'] != "none")) {
			$tconf .= "  neighbor {$neighbor['peer']} unsuppress-map {$neighbor['unsuppressmap']}\n";
		}

		/* Advanced Options */
		if (frr_validate_intrange($neighbor['weight'], 0, 65535)) {
			$tconf .= "  neighbor {$neighbor['peer']} weight {$neighbor['weight']}\n";
		}

		if (!empty($neighbor['passive'])) {
			$nconf .= "  neighbor {$neighbor['peer']} passive\n";
		}

		if (!empty($neighbor['addpathtxallpaths'])) {
			$tconf .= "  neighbor {$neighbor['peer']} addpath-tx-all-paths\n";
		}
		if (!empty($neighbor['addpathtxbestpathperas'])) {
			$tconf .= "  neighbor {$neighbor['peer']} addpath-tx-bestpath-per-AS\n";
		}

		if (frr_validate_intrange($neighbor['advertisementinterval'], 0, 600)) {
			$nconf .= "  neighbor {$neighbor['peer']} advertisement-interval {$neighbor['advertisementinterval']}\n";
		}

		if (!empty($neighbor['allowasin']) && ($neighbor['allowasin'] != "disabled")) {
			$tconf .= "  neighbor {$neighbor['peer']} allowas-in";
			if ($neighbor['allowasin'] == "origin") {
				$tconf .= " origin";
			} elseif (frr_validate_intrange($neighbor['allowasin'], 1, 10)) {
				$tconf .= " {$neighbor['allowasin']}";
			}
			$tconf .= "\n";
		}

		if (!empty($neighbor['asoverride'])) {
			$tconf .= "  neighbor {$neighbor['peer']} as-override\n";
		}

		if (!empty($neighbor['attributeunchanged'])) {
			$tconf .= "  neighbor {$neighbor['peer']} attribute-unchanged";
			if (!empty($neighbor['attributeunchanged_aspath'])) {
				$tconf .= " as-path";
			}
			if (!empty($neighbor['attributeunchanged_med'])) {
				$tconf .= " med";
			}
			if (!empty($neighbor['attributeunchanged_nexthop'])) {
				$tconf .= " next-hop";
			}
			$tconf .= "\n";
		}

		if (frr_validate_intrange($neighbor['bfd_multiplier'], 2, 255)) {
			$nconf .= "  neighbor {$neighbor['peer']} bfd {$neighbor['bfd_multiplier']}";
			if (frr_validate_intrange($neighbor['bfd_minrx'], 50, 60000)) {
				$nconf .= " {$neighbor['bfd_minrx']}";
				if (frr_validate_intrange($neighbor['bfd_mintx'], 50, 60000)) {
					$nconf .= " {$neighbor['bfd_mintx']}";
				}
			}
			$nconf .= "\n";
		}

		if (in_array($neighbor['capability'], array('dynamic', 'extended-nexthop', 'orf'))) {
			$nconf .= "  neighbor {$neighbor['peer']} capability {$neighbor['capability']}\n";
		}

		if (!empty($neighbor['dontcapabilitynegotiate'])) {
			$nconf .= "  neighbor {$neighbor['peer']} dont-capability-negotiate\n";
		}

		if (!empty($neighbor['overridecapability'])) {
			$nconf .= "  neighbor {$neighbor['peer']} override-capability\n";
		}

		if (frr_validate_intrange($neighbor['ttlsecurityhops'], 1, 254)) {
			$nconf .= "  neighbor {$neighbor['peer']} ttl-security hops {$neighbor['ttlsecurityhops']}\n";
		}

		if (!empty($neighbor['disableconnectedcheck'])) {
			$nconf .= "  neighbor {$neighbor['peer']} disable-connected-check\n";
		}

		if (frr_validate_intrange($neighbor['ebgpmultihop'], 1, 255)) {
			$nconf .= "  neighbor {$neighbor['peer']} ebgp-multihop {$neighbor['ebgpmultihop']}\n";
		} elseif ($neighbor['ebgpmultihop'] === "0") {
			$nconf .= "  neighbor {$neighbor['peer']} ebgp-multihop\n";
		}

		if (!empty($neighbor['enforcemultihop'])) {
			$nconf .= "  neighbor {$neighbor['peer']} enforce-multihop\n";
		}

		if (frr_validate_asnum($neighbor['localas_num'])) {
			$nconf .= "  neighbor {$neighbor['peer']} local-as {$neighbor['localas_num']}";
			if (!empty($neighbor['localas_noprepend'])) {
				$nconf .= " no-prepend";
				if (!empty($neighbor['localas_replaceas'])) {
					$nconf .= " replace-as";
				}
			}
			$nconf .= "\n";
		}

		if (frr_validate_ulong($neighbor['maximumprefix_num'], 1)) {
			$nconf .= "  neighbor {$neighbor['peer']} maximum-prefix {$neighbor['maximumprefix_num']}";
			if (frr_validate_intrange($neighbor['maximumprefix_threshold'], 1, 100)) {
				$nconf .= " {$neighbor['maximumprefix_threshold']}";
			}
			if (!empty($neighbor['maximumprefix_warnonly'])) {
				$nconf .= " warning-only";
			} elseif (frr_validate_intrange($neighbor['maximumprefix_restart'], 1, 65535)) {
				$nconf .= " {$neighbor['maximumprefix_restart']}";
			}
			$nconf .= "\n";
		}

		if (!empty($neighbor['removeprivateas'])) {
			$tconf .= "  neighbor {$neighbor['peer']} remove-private-AS";
			if (!empty($neighbor['removeprivateas_all'])) {
				$tconf .= " all";
			}
			if (!empty($neighbor['removeprivateas_replace'])) {
				$tconf .= " replace-AS";
			}
			$tconf .= "\n";
		}

		if (!empty($neighbor['routeclient_reflector'])) {
			$tconf .= "  neighbor {$neighbor['peer']} route-reflector-client\n";
		}
		if (!empty($neighbor['routeclient_server'])) {
			$tconf .= "  neighbor {$neighbor['peer']} route-server-client\n";
		}

		if (!empty($neighbor['solo'])) {
			$nconf .= "  neighbor {$neighbor['peer']} solo\n";
		}

		if (!empty($afconf)) {
			/* Fix spacing for extra directives */
			$tconf = str_replace("\n", "\n  ", $tconf);
			/* Add the AF header, extra directives, and then exit the block */
			$nconf .= $afconf . '  ' . $tconf . "exit-address-family \n";
		} else {
			$nconf .= $tconf;
		}

	}

	return $nconf;
}

/* Generate BGP AS Path configuration lines */
function frr_bgp_generate_aspaths() {
	global $config, $frr_config_base;
	/* Populate FRR AS Path Settings */
	if (is_array($config['installedpackages']['frrbgpaspaths']['config'])) {
		$frr_asp_conf = &$config['installedpackages']['frrbgpaspaths']['config'];
	} else {
		/* No AS Path config, stop. */
		return null;
	}
	/* Section header */
	$aspconf = "\n# AS Paths\n";
	/* Loop through AS Paths and process */
	foreach ($frr_asp_conf as $asp) {
		/* Sort by sequence if needed */
		frr_array_seq_sort($asp['row']);
		foreach ($asp['row'] as $line) {

			$aspconf .= "ip as-path access-list {$asp['name']} {$line['action']} {$line['regex']}\n";
		}
	}
	return $aspconf;
}

/* Determine the Community List type based on the given name */
function frr_get_community_type($clname, $type) {
	/* 0-99: Standard
	 * 100-500: Extended
	 * WORD: Determined by selection
	 */
	$cl_type = "";
	if (is_numeric($clname)) {
		if (($clname > 0) && ($clname < 100)) {
			$cl_type = "standard";
		} elseif (($clname > 99) && ($clname <= 500)) {
			$cl_type = "extended";
		}
	} elseif (frr_validate_word($clname)) {
		/* If the name is a word, use the given type instead. */
		$cl_type = $type;
	}
	return $cl_type;
}

/* Generate BGP Community List configuration lines */
function frr_bgp_generate_communitylists() {
	global $config, $frr_config_base, $frr_well_known_communities;
	/* Populate FRR Community List Settings */
	if (is_array($config['installedpackages']['frrbgpcommunities']['config'])) {
		$frr_cls_conf = &$config['installedpackages']['frrbgpcommunities']['config'];
	} else {
		/* No CL config, stop. */
		return null;
	}
	/* Section header */
	$clconf = "\n# Community Lists\n";
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
			$clconf .= "ip community-list {$name} {$line['action']} {$line['community']}\n";
		}
	}
	return $clconf;
}

function frr_generate_config_bgp() {
	global $config, $frr_config_base, $frr_auto_config_warning;
	/* Make bgpd config */
	if (is_array($config['installedpackages']['frr']['config'])) {
		$frr_conf = &$config['installedpackages']['frr']['config'][0];
	} else {
		/* If FRR is not configured, stop. */
		return null;
	}
	/* Populate FRR BGP Settings */
	if (is_array($config['installedpackages']['frrbgp']['config'])) {
		$frr_bgp_conf = &$config['installedpackages']['frrbgp']['config'][0];
	} else {
		/* No BGP config, stop. */
		return null;
	}

	if (isset($config['installedpackages']['frrglobalraw']['config'][0]['bgpd']) &&
	    !empty($config['installedpackages']['frrglobalraw']['config'][0]['bgpd'])) {
		// if there is a raw config specified in the config.xml use that instead of the assisted config
		$bgpdconffile = str_replace("\r","",base64_decode($config['installedpackages']['frrglobalraw']['config'][0]['bgpd']));
	} else {
		/* If there is no raw configuration and no GUI configuration, stop. */
		if (empty($frr_conf['enable']) || empty($frr_bgp_conf['enable'])) {
			/* FRR is disabled or BGP Daemon is not enabled. */
			return;
		}
		$bgpdconffile = $frr_auto_config_warning;

		/* Pull the password and logging option from the global settings */
		if ($frr_conf['password']) {
			$bgpdconffile .= "password {$frr_conf['password']}\n";
		}
		if ($frr_conf['logging']) {
			$bgpdconffile .= "log syslog\n";
		}

		/* BGP Router Config */
		$bgpdconffile .= frr_bgp_generate_router();
		/* BGP Router Advanced Config */
		$bgpdconffile .= frr_bgp_generate_routeradvanced();
		/* BGP Neighbors */
		$bgpdconffile .= frr_bgp_generate_neighbors();
		/* BGP AS Paths */
		$bgpdconffile .= frr_bgp_generate_aspaths();
		/* BGP Community Lists */
		$bgpdconffile .= frr_bgp_generate_communitylists();
		/* BGP Prefix Lists */
		$bgpdconffile .= frr_zebra_generate_prefixlists();
		/* Some route-map commands are BGP-specific, so print them here, too. */
		$bgpdconffile .= frr_zebra_generate_routemaps();
	}
	file_put_contents("{$frr_config_base}/bgpd.conf", $bgpdconffile);
}
