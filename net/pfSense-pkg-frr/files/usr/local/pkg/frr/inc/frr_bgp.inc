<?php
/*
 * frr_bgp.inc
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

define('PKG_FRR_BGP_ADDRESS_FAMILIES', array('ipv4 unicast', 'ipv6 unicast'));

/* Fetch the list of BGP AS Paths for use in selection lists */
function frr_get_bgp_aspath_list() {
	$list = array();
	$list[] = PKG_FRR_LIST_NONE_VALUE;
	foreach (config_get_path('installedpackages/frrbgpaspaths/config', []) as $asp) {
		$list[] = array("name" => "{$asp['name']} - {$asp['descr']}", "value" => $asp['name']);
	}
	return ($list);
}

/* Fetch the list of BGP Communities for use in selection lists */
function frr_get_bgp_community_list() {
	global $frr_well_known_communities;
	$list = array();
	$list[] = PKG_FRR_LIST_NONE_VALUE;

	foreach ($frr_well_known_communities as $wkc) {
		$tmp["name"] = $wkc;
		$tmp["value"] = $wkc;
		$list[] = $tmp;
	}

	foreach (config_get_path('installedpackages/frrbgpcommunities/config', []) as $cl) {
		$list[] = array("name" => "{$cl['name']} - {$cl['descr']}", "value" => $cl['name']);
	}

	return ($list);
}

/* Fetch the list of BGP Neighbor addresses for use in selection lists */
function frr_get_bgp_neighbor_list($grouponly = false, $peeronly = false, $includenone = true, $nameprefix = "", $valueprefix = "") {
	$list = array();
	if ($includenone) {
		$list[] = array("name" => "{$nameprefix}None", "value" => "{$valueprefix}none");
	}

	/* Build the list, and if only groups are wanted, return a list of only
	 * peers with names and not IP addresses. If only peers are wanted, only
	 * return a list that contains IP addresses. */
	foreach (config_get_path('installedpackages/frrbgpneighbors/config', []) as $nei) {
		if (($grouponly && is_ipaddr($nei['peer'])) ||
			($peeronly && !is_ipaddr($nei['peer']))) {
			continue;
		}
		if (!empty($nei['descr'])) {
			$nei['descr'] = " - {$nei['descr']}";
		}
		$list[] = array("name" => "{$nameprefix}{$nei['peer']}{$nei['descr']}", "value" => $valueprefix . $nei['peer']);
	}

	return ($list);
}

/* Make an associative array of neighbors with the peer name or IP address
 * as the key */
function frr_get_neighbors_with_name_key() {
	$allneighbors = array();
	foreach (config_get_path('installedpackages/frrbgpneighbors/config', []) as $neighbor) {
		$allneighbors[$neighbor['peer']] = $neighbor;
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
	global $frr_config_base, $frr_bgp_config_address_family;

	/* Populate FRR Settings */
	$frr_conf = config_get_path('installedpackages/frr/config/0', []);
	if (empty($frr_conf)) {
		/* If FRR is not configured, stop. */
		return null;
	}
	/* Populate FRR BGP Settings */
	$frr_bgp_conf = config_get_path('installedpackages/frrbgp/config/0', []);
	if (empty($frr_bgp_conf)) {
		/* No BGP config, stop. */
		return null;
	}
	$bgpconf = "";

	/* BGP Router Options */
	if (frr_validate_asnum($frr_bgp_conf['asnum'])) {
		$bgpconf .= "router bgp {$frr_bgp_conf['asnum']}\n";
	} else {
		return null;
	}
	/* Log adjacency changes */
	if (!empty($frr_bgp_conf['adjacencylog'])) {
		$bgpconf .= " bgp log-neighbor-changes\n";
	}
	/* IPv4 unicast default disable */
	if (!empty($frr_bgp_conf['nodefipv4unicast'])) {
		$bgpconf .= " no bgp default ipv4-unicast\n";
	}
	/* If the router ID is defined in BGP, use that, otherwise try to use
	 * the global router ID, if one is set. */
	if (is_ipaddrv4($frr_bgp_conf['routerid'])) {
		$bgpconf .= " bgp router-id {$frr_bgp_conf['routerid']}\n";
	} elseif (is_ipaddrv4($frr_conf['routerid'])) {
		$bgpconf .= " bgp router-id {$frr_conf['routerid']}\n";
	}

	/* Graceful restart/shutdown */
	if (!empty($frr_bgp_conf['grglobaldisable'])) {
		$bgpconf .= " bgp graceful-restart-disable\n";
	}
	if (!empty($frr_bgp_conf['disablepreservefwstate'])) {
		$bgpconf .= " bgp graceful-restart preserve-fw-state\n";
	}
	/* Graceful restart timers */
	if ((frr_validate_intrange($frr_bgp_conf['grstalepathtime'], 1, 4095))) {
		$bgpconf .= " bgp graceful-restart stalepath-time {$frr_bgp_conf['grstalepathtime']}\n";
	}
	if ((frr_validate_intrange($frr_bgp_conf['grrestarttimetime'], 1, 4095))) {
		$bgpconf .= " bgp graceful-restart restart-time {$frr_bgp_conf['grrestarttimetime']}\n";
	}
	if ((frr_validate_intrange($frr_bgp_conf['grselectdefertime'], 0, 3600))) {
		$bgpconf .= " bgp graceful-restart select-defer-time {$frr_bgp_conf['grselectdefertime']}\n";
	}
	if ((frr_validate_intrange($frr_bgp_conf['grribstaletime'], 1, 3600))) {
		$bgpconf .= " bgp graceful-restart rib-stale-time {$frr_bgp_conf['grribstaletime']}\n";
	}
	/* Graceful shutdown */
	if (!empty($frr_bgp_conf['gsenable'])) {
		$bgpconf .= " bgp graceful-shutdown\n";
	}

	/* Global neighbors administrative shutdown */
	if (!empty($frr_bgp_conf['shutdown'])) {
		$bgpconf .= " bgp shutdown";
		if (!empty($frr_bgp_conf['shutdownmessage'])) {
			$bgpconf .= " message \"{$frr_bgp_conf['shutdownmessage']}\"";
		}
		$bgpconf .= "\n";
	}

	/* Timers */
	/* Set keep-alive and holdtime, both MUST be set together */
	if ((frr_validate_intrange($frr_bgp_conf['timers_keepalive'], 0, 65535)) &&
		(frr_validate_intrange($frr_bgp_conf['timers_holdtime'], 0, 65535))) {
		$bgpconf .= " timers bgp {$frr_bgp_conf['timers_keepalive']} {$frr_bgp_conf['timers_holdtime']}\n";
	}
	/* Set update-delay and peer wait time */
	if (frr_validate_intrange($frr_bgp_conf['timers_updatedelay'], 0, 3600)) {
		$bgpconf .= " update-delay {$frr_bgp_conf['timers_updatedelay']}";

		/* Peer wait time can only be specified if update-delay is also specified
		 * Peer wait time must be less than or equal to update-delay */
		if (frr_validate_intrange($frr_bgp_conf['timers_peerwait'], 1, 3600) &&
			($frr_bgp_conf['timers_peerwait'] <= $frr_bgp_conf['timers_updatedelay'])) {
			$bgpconf .= " {$frr_bgp_conf['timers_peerwait']}";
		}
		$bgpconf .= "\n";
	}
	/* Address-family options */
	foreach (PKG_FRR_BGP_ADDRESS_FAMILIES as $af) {
		$ipfamily = explode(" ", $af)[0];
		$af_pre = $af . "-pre";

		$frr_bgpadv_conf = config_get_path('installedpackages/frrbgpadvanced/config/0', []);
		if (!empty($frr_bgpadv_conf)) {
			/* Aggregate Behavior */
			foreach ($frr_bgpadv_conf['row'] as $aggr) {
				if ((is_subnetv4($aggr['aggregateaddr']) && ($ipfamily == 'ipv4')) ||
				    (is_subnetv6($aggr['aggregateaddr']) && ($ipfamily == 'ipv6'))) {
					$frr_bgp_config_address_family[$af_pre] .= "  aggregate-address {$aggr['aggregateaddr']}";
					if (!empty($aggr['aggregateasset'])) {
						$frr_bgp_config_address_family[$af_pre] .= " as-set";
					}
					if (!empty($aggr['aggregatesummaryonly'])) {
						$frr_bgp_config_address_family[$af_pre] .= " summary-only";
					}
					$frr_bgp_config_address_family[$af_pre] .= "\n";
				}
			}
			/* Admin distance requires both a distance value and a subnet/prefix */
			if (frr_validate_intrange($frr_bgpadv_conf['distanceadmin'], 1, 255) &&
			    (is_subnetv4($frr_bgpadv_conf['distanceadminprefix']) && ($ipfamily == 'ipv4')) ||
			    (is_subnetv6($frr_bgpadv_conf['distanceadminprefix']) && ($ipfamily == 'ipv6'))) {
				$frr_bgp_config_address_family[$af_pre] .= "  distance {$frr_bgpadv_conf['distanceadmin']} {$frr_bgpadv_conf['distanceadminprefix']}";
				if (in_array($frr_bgpadv_conf['distanceadminacl'], frr_get_list_values(frr_get_accesslist_list())) &&
					($frr_bgpadv_conf['distanceadminacl'] != "none")) {
					$frr_bgp_config_address_family[$af_pre] .= "  {$frr_bgpadv_conf['distanceadminacl']}";
				}
				$frr_bgp_config_address_family[$af_pre] .= "\n";
			}
			/* For BGP distance, all three values must be set. */
			if (frr_validate_intrange($frr_bgpadv_conf['bgpdistanceext'], 1, 255) &&
			    frr_validate_intrange($frr_bgpadv_conf['bgpdistanceint'], 1, 255) &&
			    frr_validate_intrange($frr_bgpadv_conf['bgpdistancelocal'], 1, 255)) {
				$frr_bgp_config_address_family[$af_pre] .= "  distance bgp {$frr_bgpadv_conf['bgpdistanceext']} {$frr_bgpadv_conf['bgpdistanceint']} {$frr_bgpadv_conf['bgpdistancelocal']}\n";
			}
			if (in_array($frr_bgpadv_conf['tablemap'], frr_get_list_values(frr_get_routemap_list())) &&
				($frr_bgpadv_conf['tablemap'] != "none")) {
				$frr_bgp_config_address_family[$af_pre] .= "  table-map {$frr_bgpadv_conf['tablemap']}\n";
			}
		}

		/* (Re)distribute */
		foreach (array('connected', 'static', 'kernel', 'ospf', 'ospf6', 'rip') as $redist) {
			if ((($ipfamily == 'ipv4') && ($redist == 'ospf6')) ||
			    (($ipfamily == 'ipv6') && ($redist == 'ospf')) ||
			    (($ipfamily == 'ipv6') && ($redist == 'rip'))) {
					continue;
				}

			if (in_array($frr_bgp_conf['redistribute' . $redist], array($ipfamily, 'yes'))) {
				$frr_bgp_config_address_family[$af_pre] .= "  redistribute $redist\n";
			} elseif (!in_array($frr_bgp_conf['redistribute' . $redist], array('no', 'yes', 'ipv4', 'ipv6')) &&
				  in_array($frr_bgp_conf['redistribute' . $redist], frr_get_list_values(frr_get_routemap_list(false, true)))) {
					$frr_bgp_config_address_family[$af_pre] .= "  redistribute $redist route-map {$frr_bgp_conf['redistribute' . $redist]}\n";
			}
		}
		foreach (array_get_path($frr_bgp_conf, 'row', []) as $net) {
			if (is_subnet($net['distributeroutevalue']) || is_ipaddr($net['distributeroutevalue'])) {
				if ((is_subnetv4($net['distributeroutevalue']) && ($ipfamily == 'ipv4')) ||
					(is_subnetv6($net['distributeroutevalue']) && ($ipfamily == 'ipv6'))) {
					$frr_bgp_config_address_family[$af_pre] .= "  network {$net['distributeroutevalue']}";
					if (($net['distributeroutemap'] != "none") &&
						in_array($net['distributeroutemap'], frr_get_list_values(frr_get_routemap_list()))) {
						$frr_bgp_config_address_family[$af_pre] .= "  route-map {$net['distributeroutemap']}";
					}
					$frr_bgp_config_address_family[$af_pre] .= "\n";
				}
			}
		}
	}

	return $bgpconf;
}

/* Generate BGP Router Advanced configuration lines */
function frr_bgp_generate_routeradvanced() {
	global $frr_config_base;
	/* Populate FRR BGP Advanced Settings */
	$frr_bgpadv_conf = config_get_path('installedpackages/frrbgpadvanced/config/0', []);
	if (empty($frr_bgpadv_conf)) {
		/* No BGP Advanced config, return defaults. */
		return " no bgp network import-check\n";
	}
	$advconf = "";
	/* Advanced Options */
	if (frr_validate_ulong($frr_bgpadv_conf['default_localpref'])) {
		$advconf .= " bgp default local-preference {$frr_bgpadv_conf['default_localpref']}\n";
	}
	/* Advanced Timers */
	if (frr_validate_ulong($frr_bgpadv_conf['timers_coalesce'], 1)) {
		$advconf .= " coalesce-time {$frr_bgpadv_conf['timers_coalesce']}\n";
	}
	if (frr_validate_intrange($frr_bgpadv_conf['timers_routemapdelay'], 0, 600)) {
		$advconf .= " bgp route-map delay-timer {$frr_bgpadv_conf['timers_routemapdelay']}\n";
	}
	if (frr_validate_intrange($frr_bgpadv_conf['dampening_halflife'], 1, 45)) {
		$advconf .= " bgp dampening {$frr_bgpadv_conf['dampening_halflife']}";
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
		$advconf .= " no bgp fast-external-failover\n";
	}
	if (!empty($frr_bgpadv_conf['networkimportcheck'])) {
		$advconf .= " bgp network import-check\n";
	} else {
		$advconf .= " no bgp network import-check\n";
	}
	if (!empty($frr_bgpadv_conf['rejcectassetasconfedset'])) {
		$advconf .= " bgp reject-as-sets\n";
	}

	/* Route Reflecting */
	if (!empty($frr_bgpadv_conf['routereflectorallowoutboundpolicy'])) {
		$advconf .= " bgp route-reflector allow-outbound-policy\n";
	}
	if (frr_validate_ulong($frr_bgpadv_conf['routereflectorclusterid'], 1) || is_ipaddrv4($frr_bgpadv_conf['routereflectorclusterid'])) {
		$advconf .= " bgp cluster-id {$frr_bgpadv_conf['routereflectorclusterid']}\n";
	}
	if (!empty($frr_bgpadv_conf['routereflectorclienttoclient'])) {
		$advconf .= " no bgp client-to-client reflection\n";
	}

	/* Multi-Exit Discriminator */
	if (!empty($frr_bgpadv_conf['meddeterministic'])) {
		$advconf .= " bgp deterministic-med\n";
	}
	if (!empty($frr_bgpadv_conf['medalwayscompare'])) {
		$advconf .= " bgp always-compare-med\n";
	}

	if (!empty($frr_bgpadv_conf['maxmedadmin'])) {
		$advconf .= " bgp max-med administrative\n";
	} elseif (frr_validate_intrange($frr_bgpadv_conf['maxmedadminvalue'], 0, 4294967294)) {
		$advconf .= " bgp max-med administrative {$frr_bgpadv_conf['maxmedadminvalue']}\n";
	}
	if (frr_validate_intrange($frr_bgpadv_conf['maxmedstartup'], 5, 86400)) {
		$advconf .= " bgp max-med on-startup {$frr_bgpadv_conf['maxmedstartup']}";
		if (frr_validate_intrange($frr_bgpadv_conf['maxmedstartupvalue'], 0, 4294967294)) {
			$advconf .= " {$frr_bgpadv_conf['maxmedstartupvalue']}";
		}
		$advconf .= "\n";
	}

	/* Confederation */
	if (frr_validate_asnum($frr_bgpadv_conf['confedas'])) {
		$advconf .= " bgp confederation identifier {$frr_bgpadv_conf['confedas']}\n";
	}

	/* Confed Peers are a space-separated list of AS numbers. If there are any invalid values, skip the entire line. */
	$add_confedpeers = true;
	foreach (explode(' ', $frr_bgpadv_conf['confedpeers']) as $confedpeer) {
		if (!frr_validate_asnum($confedpeer)) {
			$add_confedpeers = false;
		}
	}
	if ($add_confedpeers) {
		$advconf .= " bgp confederation peers {$frr_bgpadv_conf['confedpeers']}\n";
	}
	/* Best Path Selection */
	if (!empty($frr_bgpadv_conf['bgpbestpathasconfed'])) {
		$advconf .= " bgp bestpath as-path confed\n";
	}
	if (!empty($frr_bgpadv_conf['bgpbestpathasignore'])) {
		$advconf .= " bgp bestpath as-path ignore\n";
	}

	if (!empty($frr_bgpadv_conf['bgpbestpathasmulti'])) {
		$advconf .= " bgp bestpath as-path multipath-relax";
		if (!empty($frr_bgpadv_conf['bgpbestpathasmultigenasset'])) {
			$advconf .= " as-set";
		}
		$advconf .= "\n";
	}

	if (!empty($frr_bgpadv_conf['bgpbestpathcomparerouterid'])) {
		$advconf .= " bgp bestpath compare-routerid\n";
	}

	if (!empty($frr_bgpadv_conf['bgpbestpathmedconfed']) || !empty($frr_bgpadv_conf['bgpbestpathmedmissing'])) {
		$advconf .= " bgp bestpath med";
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
		$advconf .= " bgp disable-ebgp-connected-route-check\n";
	}
	if (!empty($frr_bgpadv_conf['enforcefirstas'])) {
		$advconf .= " bgp enforce-first-as\n";
	}
	if (!empty($frr_bgpadv_conf['disableebgprequirepolicy'])) {
		$advconf .= " no bgp ebgp-requires-policy\n";
	}

	/* Networking Behavior */
	if (frr_validate_intrange($frr_bgpadv_conf['subgrouppktqueuemax'], 20, 100)) {
		$advconf .= " bgp default subgroup-pkt-queue-max {$frr_bgpadv_conf['subgrouppktqueuemax']}\n";
	}
	if (frr_validate_intrange($frr_bgpadv_conf['writequanta'], 1, 10000)) {
		$advconf .= " write-quanta {$frr_bgpadv_conf['writequanta']}\n";
	}

	return $advconf;
}

/* Generate BGP Neighbor configuration lines */
function frr_bgp_generate_neighbors() {
	global $frr_config_base, $frr_bgp_config_address_family;
	/* Populate FRR BGP Neighbor Settings */
	$frr_neighbor_conf = config_get_path('installedpackages/frrbgpneighbors/config', []);
	if (empty($frr_neighbor_conf)) {
		/* No BGP Neighbor config, stop. */
		return null;
	}

	/* Neighbor BGP AS top level context neighbor section placeholder */
	$nconf = "";

	/* Placeholder for directives that could be global or in address-family */
	/* Accumulated per-address family */
	foreach ($frr_neighbor_conf as $neighbor) {
		/* Per-neighbor address family enabled flag */
		$nei_af_flag = array();
		foreach (PKG_FRR_BGP_ADDRESS_FAMILIES as $af) {
			$nei_af_flag[$af] = false;
		}

		/* General Options */
		/* Check if this is a group or a peer */
		$ntype = "peer";
		if (!is_ipaddr($neighbor['peer']) && frr_validate_word($neighbor['peer'])) {
			$ntype = "group";
			$nconf .= " neighbor {$neighbor['peer']} peer-group\n";
		} elseif (is_ipaddr($neighbor['peer'])) {
			if (!empty($neighbor['peergroup']) &&
				($neighbor['peergroup'] != 'none') &&
				in_array($neighbor['peergroup'], frr_get_list_values(frr_get_bgp_neighbor_list(true)))) {
				$nconf .= " neighbor {$neighbor['peer']} peer-group {$neighbor['peergroup']}\n";
			}

			/* Both cases may be required by a neighbor. */
			if (is_ipaddrv4($neighbor['peer']) || !empty($neighbor['bothaf'])) {
				$frr_bgp_config_address_family["ipv4 unicast-pre"] .= "  neighbor {$neighbor['peer']} activate\n";
				$nei_af_flag["ipv4 unicast"] = true;
			}
			if (is_ipaddrv6($neighbor['peer']) || !empty($neighbor['bothaf'])) {
				$frr_bgp_config_address_family["ipv6 unicast-pre"] .= "  neighbor {$neighbor['peer']} activate\n";
				$nei_af_flag["ipv6 unicast"] = true;
			}
		} else {
			/* This is not a valid neighbor! */
			continue;
		}

		/* Global neighbor configuration items */
		/* remote-as must come first, except for peer-group def/member line! */
		if (frr_validate_asnum($neighbor['asnum']) ||
		    in_array($neighbor['asnum'], array('internal', 'external'))) {
			$nconf .= " neighbor {$neighbor['peer']} remote-as {$neighbor['asnum']}\n";
		}

		if (!empty($neighbor['descr'])) {
			$neighbor['descr'] = substr($neighbor['descr'], 0, 80);
			$nconf .= " neighbor {$neighbor['peer']} description {$neighbor['descr']}\n";
		}

		/* Neighbor administrative/automatic shutdown */
		if (!empty($neighbor['shutdown'])) {
			$nconf .= " neighbor {$neighbor['peer']} shutdown";
			if (!empty($neighbor['shutdownmessage'])) {
				$nconf .= " message \"{$neighbor['shutdownmessage']}\"";
			}
			$nconf .= "\n";
		}

		if (!empty($neighbor['shutdownautortt'])) {
			$nconf .= " neighbor {$neighbor['peer']} shutdown rtt {$neighbor['shutdownautortt']}";
			if (!empty($neighbor['shutdownautokeepalive'])) {
				$nconf .= " count {$neighbor['shutdownautokeepalive']}";
			} else {
				$nconf .= " count 1";
			}
			$nconf .= "\n";
		}

		switch ($neighbor['grmode']) {
			case "restart":
				$nconf .= "  neighbor {$neighbor['peer']} graceful-restart\n";
				break;
			case "helper":
				$nconf .= "  neighbor {$neighbor['peer']} graceful-restart-helper\n";
				break;
			case "disable":
				$nconf .= "  neighbor {$neighbor['peer']} graceful-restart-disable\n";
				break;
			default:
		}

		if (in_array($neighbor['updatesource'], frr_get_list_values(frr_get_interfaces())) &&
		    ($neighbor['updatesource'] != 'default')) {
			$updatesource = frr_get_neighbor_updatesource($neighbor['updatesource'], $neighbor['updatesource_type']);
			if (!empty($updatesource)) {
				$nconf .= " neighbor {$neighbor['peer']} update-source {$updatesource}\n";
			}
		}

		list($pwtype_base, $pwtype_bidir) = explode('-', $neighbor['password_type'], 2);
		if (!empty($neighbor['password']) &&
			(($pwtype_base == "frr") ||
			($pwtype_base == "both"))) {
			$nconf .= " neighbor {$neighbor['peer']} password {$neighbor['password']}\n";
		}

		if (frr_validate_intrange($neighbor['timers_keepalive'], 0, 65535)) {
			$nconf .= " neighbor {$neighbor['peer']} timers {$neighbor['timers_keepalive']}";

			/* Hold time can only be specified if keep-alive is also specified */
			if (frr_validate_intrange($neighbor['timers_holdtime'], 0, 65535)) {
				$nconf .= " {$neighbor['timers_holdtime']}";
			}
			$nconf .= "\n";
		}
		if (frr_validate_intrange($neighbor['timers_connect'], 1, 65535)) {
			$nconf .= " neighbor {$neighbor['peer']} timers connect {$neighbor['timers_connect']}\n";
		}

		if (!empty($neighbor['passive'])) {
			$nconf .= " neighbor {$neighbor['peer']} passive\n";
		}

		if (frr_validate_intrange($neighbor['advertisementinterval'], 0, 600)) {
			$nconf .= " neighbor {$neighbor['peer']} advertisement-interval {$neighbor['advertisementinterval']}\n";
		}

		if (!empty($neighbor['bfdenable'])) {
			if (!empty($neighbor['bfd_peer']) &&
			    ($neighbor['bfd_peer'] <> 'default')) {
				$bfd_peer = $neighbor['bfd_peer'];
			} else {
				$bfd_peer = $neighbor['peer'];
			}
			$nconf .= " neighbor {$bfd_peer} bfd\n";
			if (!empty($neighbor['bfd_check_control_plane_failure'])) {
				$nconf .= " neighbor {$bfd_peer} bfd check-control-plane-failure\n";
			}
		}

		if (!empty($neighbor['capability'])) {
			$nconf .= " neighbor {$neighbor['peer']} capability dynamic\n";
		}

		if (!empty($neighbor['capability_nexthop'])) {
			$nconf .= " neighbor {$neighbor['peer']} capability extended-nexthop\n";
		}

		if (in_array($neighbor['capability_orf'], array('send', 'receive', 'both'))) {
			$nconf .= " neighbor {$neighbor['peer']} capability orf prefix-list {$neighbor['capability_orf']}\n";
		}

		if (!empty($neighbor['dontcapabilitynegotiate'])) {
			$nconf .= " neighbor {$neighbor['peer']} dont-capability-negotiate\n";
		}

		if (!empty($neighbor['overridecapability'])) {
			$nconf .= " neighbor {$neighbor['peer']} override-capability\n";
		}

		if (frr_validate_intrange($neighbor['ttlsecurityhops'], 1, 254)) {
			$nconf .= " neighbor {$neighbor['peer']} ttl-security hops {$neighbor['ttlsecurityhops']}\n";
		}

		if (!empty($neighbor['disableconnectedcheck'])) {
			$nconf .= " neighbor {$neighbor['peer']} disable-connected-check\n";
		}

		if (frr_validate_intrange($neighbor['ebgpmultihop'], 1, 255)) {
			$nconf .= " neighbor {$neighbor['peer']} ebgp-multihop {$neighbor['ebgpmultihop']}\n";
		} elseif ($neighbor['ebgpmultihop'] === "0") {
			$nconf .= " neighbor {$neighbor['peer']} ebgp-multihop\n";
		}

		if (!empty($neighbor['enforcemultihop'])) {
			$nconf .= " neighbor {$neighbor['peer']} enforce-multihop\n";
		}

		if (frr_validate_asnum($neighbor['localas_num'])) {
			$nconf .= " neighbor {$neighbor['peer']} local-as {$neighbor['localas_num']}";
			if (!empty($neighbor['localas_noprepend'])) {
				$nconf .= " no-prepend";
				if (!empty($neighbor['localas_replaceas'])) {
					$nconf .= " replace-as";
				}
			}
			$nconf .= "\n";
		}

		if (!empty($neighbor['solo'])) {
			$nconf .= " neighbor {$neighbor['peer']} solo\n";
		}

		/* Address family neighbor configuration items */
		foreach(PKG_FRR_BGP_ADDRESS_FAMILIES as $af) {
			/* Check if neighbor is enabled for the current address family */
			if (!($nei_af_flag[$af]) && ($ntype == 'peer')) {
				continue;
			}

			if (!empty($neighbor['defaultoriginate']) &&
			    ((strpos($af, $neighbor['defaultoriginate']) !== false) ||
			    ($neighbor['defaultoriginate'] == "yes"))) {
				$frr_bgp_config_address_family[$af]  .= "  neighbor {$neighbor['peer']} default-originate";
				if (!empty($neighbor['defaultoriginateroutemap']) &&
				    ($neighbor['defaultoriginateroutemap'] != "none") &&
				    in_array($neighbor['defaultoriginateroutemap'], frr_get_list_values(frr_get_routemap_list(false, false)))) {
					$frr_bgp_config_address_family[$af] .= " route-map {$neighbor['defaultoriginateroutemap']}";
				}
				$frr_bgp_config_address_family[$af] .= "  \n";
			}

			switch ($neighbor['sendcommunity']) {
				case "disabled":
					$frr_bgp_config_address_family[$af] .= "  no neighbor {$neighbor['peer']} send-community\n";
					break;
				case "enabled":
					$frr_bgp_config_address_family[$af] .= "  neighbor {$neighbor['peer']} send-community\n";
					break;
				case "standard":
				case "extended":
				case "both":
					$frr_bgp_config_address_family[$af] .= "  neighbor {$neighbor['peer']} send-community {$neighbor['sendcommunity']}\n";
					break;
				default:
			}

			if (frr_validate_ulong($neighbor['maximumprefix_num'], 1)) {
				$frr_bgp_config_address_family[$af] .= " neighbor {$neighbor['peer']} maximum-prefix {$neighbor['maximumprefix_num']}";
				if (frr_validate_intrange($neighbor['maximumprefix_threshold'], 1, 100)) {
					$frr_bgp_config_address_family[$af] .= " {$neighbor['maximumprefix_threshold']}";
				}
				if (!empty($neighbor['maximumprefix_warnonly'])) {
					$frr_bgp_config_address_family[$af] .= " warning-only";
				} elseif (frr_validate_intrange($neighbor['maximumprefix_restart'], 1, 65535)) {
					$frr_bgp_config_address_family[$af] .= " restart {$neighbor['maximumprefix_restart']}";
				}
				if (!empty($neighbor['maximumprefix_force'])) {
					$frr_bgp_config_address_family[$af] .= " force";
				}
				$frr_bgp_config_address_family[$af] .= "\n";
			}

			if (frr_validate_ulong($neighbor['maximumprefixout_num'], 1)) {
				$frr_bgp_config_address_family[$af] .= " neighbor {$neighbor['peer']} maximum-prefix-out {$neighbor['maximumprefixout_num']}\n";
			}

			if ($neighbor['nexthopself'] == "enabled") {
				$frr_bgp_config_address_family[$af] .= "  neighbor {$neighbor['peer']} next-hop-self\n";
			} elseif ($neighbor['nexthopself'] == "force") {
				$frr_bgp_config_address_family[$af] .= "  neighbor {$neighbor['peer']} next-hop-self force\n";
			}

			if (!empty($neighbor['softreconfigurationinbound'])) {
				$frr_bgp_config_address_family[$af] .= "  neighbor {$neighbor['peer']} soft-reconfiguration inbound\n";
			}

			/* Peer Filtering */
			if (in_array($neighbor['distribute_in'], frr_get_list_values(frr_get_accesslist_list())) &&
				($neighbor['distribute_in'] != "none")) {
				$frr_bgp_config_address_family[$af] .= "  neighbor {$neighbor['peer']} distribute-list {$neighbor['distribute_in']} in\n";
			}
			if (in_array($neighbor['distribute_out'], frr_get_list_values(frr_get_accesslist_list())) &&
				($neighbor['distribute_out'] != "none")) {
				$frr_bgp_config_address_family[$af] .= "  neighbor {$neighbor['peer']} distribute-list {$neighbor['distribute_out']} out\n";
			}

			if (in_array($neighbor['prefixfilter_in'], frr_get_list_values(frr_get_prefixlist_list())) &&
				($neighbor['prefixfilter_in'] != "none")) {
				$frr_bgp_config_address_family[$af] .= "  neighbor {$neighbor['peer']} prefix-list {$neighbor['prefixfilter_in']} in\n";
			}
			if (in_array($neighbor['prefixfilter_out'], frr_get_list_values(frr_get_prefixlist_list())) &&
				($neighbor['prefixfilter_out'] != "none")) {
				$frr_bgp_config_address_family[$af] .= "  neighbor {$neighbor['peer']} prefix-list {$neighbor['prefixfilter_out']} out\n";
			}

			if (in_array($neighbor['aspathfilter_in'], frr_get_list_values(frr_get_bgp_aspath_list())) &&
				($neighbor['aspathfilter_in'] != "none")) {
				$frr_bgp_config_address_family[$af] .= "  neighbor {$neighbor['peer']} filter-list {$neighbor['aspathfilter_in']} in\n";
			}
			if (in_array($neighbor['aspathfilter_out'], frr_get_list_values(frr_get_bgp_aspath_list())) &&
				($neighbor['aspathfilter_out'] != "none")) {
				$frr_bgp_config_address_family[$af] .= "  neighbor {$neighbor['peer']} filter-list {$neighbor['aspathfilter_out']} out\n";
			}

			if (in_array($neighbor['routemap_in'], frr_get_list_values(frr_get_routemap_list())) &&
				($neighbor['routemap_in'] != "none")) {
				$frr_bgp_config_address_family[$af] .= "  neighbor {$neighbor['peer']} route-map {$neighbor['routemap_in']} in\n";
			}
			if (in_array($neighbor['routemap_out'], frr_get_list_values(frr_get_routemap_list())) &&
				($neighbor['routemap_out'] != "none")) {
				$frr_bgp_config_address_family[$af] .= "  neighbor {$neighbor['peer']} route-map {$neighbor['routemap_out']} out\n";
			}

			if (in_array($neighbor['unsuppressmap'], frr_get_list_values(frr_get_routemap_list())) &&
				($neighbor['unsuppressmap'] != "none")) {
				$frr_bgp_config_address_family[$af] .= "  neighbor {$neighbor['peer']} unsuppress-map {$neighbor['unsuppressmap']}\n";
			}

			/* Advanced Options */
			if (frr_validate_intrange($neighbor['weight'], 0, 65535)) {
				$frr_bgp_config_address_family[$af] .= "  neighbor {$neighbor['peer']} weight {$neighbor['weight']}\n";
			}

			if (!empty($neighbor['addpathtxallpaths'])) {
				$frr_bgp_config_address_family[$af] .= "  neighbor {$neighbor['peer']} addpath-tx-all-paths\n";
			}
			if (!empty($neighbor['addpathtxbestpathperas'])) {
				$frr_bgp_config_address_family[$af] .= "  neighbor {$neighbor['peer']} addpath-tx-bestpath-per-AS\n";
			}

			if (frr_validate_intrange($neighbor['advertisementinterval'], 0, 600)) {
				$nconf .= " neighbor {$neighbor['peer']} advertisement-interval {$neighbor['advertisementinterval']}\n";
			}

			if (!empty($neighbor['allowasin']) &&
			    ($neighbor['allowasin'] != "disabled")) {
				$frr_bgp_config_address_family[$af] .= "  neighbor {$neighbor['peer']} allowas-in";
				if ($neighbor['allowasin'] == "origin") {
					$frr_bgp_config_address_family[$af] .= "  origin";
				} elseif (frr_validate_intrange($neighbor['allowasin'], 1, 10)) {
					$frr_bgp_config_address_family[$af] .= "  {$neighbor['allowasin']}";
				}
				$frr_bgp_config_address_family[$af] .= "  \n";
			}

			if (!empty($neighbor['asoverride'])) {
				$frr_bgp_config_address_family[$af] .= "  neighbor {$neighbor['peer']} as-override\n";
			}

			if (!empty($neighbor['attributeunchanged'])) {
				$frr_bgp_config_address_family[$af] .= "  neighbor {$neighbor['peer']} attribute-unchanged";
				if (!empty($neighbor['attributeunchanged_aspath'])) {
					$frr_bgp_config_address_family[$af] .= "  as-path";
				}
				if (!empty($neighbor['attributeunchanged_med'])) {
					$frr_bgp_config_address_family[$af] .= "  med";
				}
				if (!empty($neighbor['attributeunchanged_nexthop'])) {
					$frr_bgp_config_address_family[$af] .= "  next-hop";
				}
				$frr_bgp_config_address_family[$af] .= "  \n";
			}

			if (!empty($neighbor['removeprivateas'])) {
				$frr_bgp_config_address_family[$af] .= "  neighbor {$neighbor['peer']} remove-private-AS";
				if (!empty($neighbor['removeprivateas_all'])) {
					$frr_bgp_config_address_family[$af] .= "  all";
				}
				if (!empty($neighbor['removeprivateas_replace'])) {
					$frr_bgp_config_address_family[$af] .= "  replace-AS";
				}
				$frr_bgp_config_address_family[$af] .= "  \n";
			}

			if (!empty($neighbor['routeclient_reflector'])) {
				$frr_bgp_config_address_family[$af] .= "  neighbor {$neighbor['peer']} route-reflector-client\n";
			}
			if (!empty($neighbor['routeclient_server'])) {
				$frr_bgp_config_address_family[$af] .= "  neighbor {$neighbor['peer']} route-server-client\n";
			}
		}
	}

	$nconf .= " !\n";
	return $nconf;
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

/* Generate BGP RPKI lines */
function frr_bgp_generate_rpki_servers() {
	global $frr_config_base, $frr_integrated_config;

	$frr_bgp_conf = config_get_path('installedpackages/frrbgp/config/0', []);
	if (empty($frr_bgp_conf)) {
		/* If FRR is not configured, stop. */
		return null;
	}

	if (empty($frr_bgp_conf['enablerpki'])) {
		/* RPKI is not configured, stop. */
		return null;
	}

	$rpki_config = "rpki\n";
	if (!empty($frr_bgp_conf['rpkipollingperiod'])) {
		$rpki_config .= " rpki polling_period {$frr_bgp_conf['rpkipollingperiod']}\n";
	}
	if (!empty($frr_bgp_conf['rpkiexpireinterval'])) {
		$rpki_config .= " rpki expire_interval {$frr_bgp_conf['rpkiexpireinterval']}\n";
	}
	if (!empty($frr_bgp_conf['rpkiretryinterval'])) {
		$rpki_config .= " rpki retry_interval {$frr_bgp_conf['rpkiretryinterval']}\n";
	}

	$frr_bgp_rpki_cs = config_get_path('installedpackages/frrbgprpkicacheservers/config', []);
	if (!empty($frr_bgp_rpki_cs)) {
		foreach ($frr_bgp_rpki_cs as $cs) {
			$rpki_config .= " rpki cache {$cs['cacheserver']} {$cs['port']}";
			foreach (array("username", "privkeypath", "pubkeypath") as $key) {
				if (empty($cs[$key])) {
					continue;
				}
				$rpki_config .= " {$cs[$key]}";
			}
			$rpki_config .= " preference {$cs['preference']}\n";
		}
	} else {
		/* No RPKI cache servers configured, stop. */
		return null;
	}

	return $rpki_config;
}

/* Generate accumulated BGP address families */
function frr_bgp_generate_address_families() {
	global $frr_bgp_config_address_family;

	$afconf = "";

	foreach (PKG_FRR_BGP_ADDRESS_FAMILIES as $af) {
		if (empty($frr_bgp_config_address_family["{$af}-pre"])) {
			continue;
		}
		$afconf .=  " address-family " . $af . "\n" . $frr_bgp_config_address_family["{$af}-pre"] . $frr_bgp_config_address_family[$af] . " exit-address-family\n" . " !\n";
	}

	return $afconf;
}

function frr_generate_config_bgp() {
	global $frr_config_base, $frr_integrated_config, $frr_bgp_config_address_family;

	/* Make bgpd config */
	$frr_conf = config_get_path('installedpackages/frr/config/0', []);
	if (empty($frr_conf)) {
		/* If FRR is not configured, stop. */
		frr_package_log("BGPd: No FRR global config data found.");
		return null;
	}
	/* Populate FRR BGP Settings */
	$frr_bgp_conf = config_get_path('installedpackages/frrbgp/config/0', []);

	if (!empty(config_get_path('installedpackages/frrglobalraw/config/0/bgpd'))) {
		// if there is a raw config specified in the config.xml use that instead of the assisted config
		$frr_integrated_config['bgpd'] = str_replace("\r","",base64_decode(config_get_path('installedpackages/frrglobalraw/config/0/bgpd')));
	} else {
		/* If there is no raw configuration and no GUI configuration, stop. */
		if (empty($frr_bgp_conf)) {
			frr_package_log("BGPd: No config data found.");
			return;
		} elseif (empty($frr_bgp_conf['enable'])) {
			/* BGP daemon is not enabled. */
			frr_package_log("BGPd: BGP disabled.");
			return;
		} elseif (empty($frr_conf['enable'])) {
			/* FRR is disabled or BFD Daemon is not enabled. */
			frr_package_log("BGPd: FRR master disabled.");
			return;
		}

		$frr_integrated_config['bgpd'] = "";

		$frr_bgp_config_address_family = array();
		foreach (PKG_FRR_BGP_ADDRESS_FAMILIES as $af) {
			$frr_bgp_config_address_family[$af] = "";
			$frr_bgp_config_address_family["{$af}-pre"] = "";
		}

		/* BGP Router Config */
		$frr_integrated_config['bgpd'] .= frr_bgp_generate_router();
		/* BGP Router Advanced Config */
		$frr_integrated_config['bgpd'] .= frr_bgp_generate_routeradvanced();
		/* BGP Neighbors */
		$frr_integrated_config['bgpd'] .= frr_bgp_generate_neighbors();
		/* BGP Address Families */
		$frr_integrated_config['bgpd'] .= frr_bgp_generate_address_families();

		/* BGP RPKI */
		$frr_integrated_config['rpki'] = frr_bgp_generate_rpki_servers();
	}
}
