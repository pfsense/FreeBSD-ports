<?php

require_once("globals.inc");
require("config.inc");
require("ipsec.inc");
require("pkg-utils.inc");
require("aws/aws-autoloader.php");

use Aws\Ec2\Ec2Client;

$openbgpd_include = "/usr/local/pkg/openbgpd.inc";
if (file_exists($openbgpd_include)) {
	require_once($openbgpd_include);
}


/*
 *
 **** Functions for interacting with AWS EC2 API ****
 *
 */



/* return an array containing API credentials formatted for use in API calls */
function makeCredsArray($awskey, $secret, $region) {

	$aws_creds = array(
		'key' => $awskey,
		'secret' => $secret,
		'region' => $region
	);

	return($aws_creds);
}

/* get the list of VPCs for a user/region specified in the credentials */
function retrieveVPCs($aws_creds) {
        
	$client = Ec2Client::factory($aws_creds);
	$vpcs = $client->describeVpcs(array(
		'DryRun' => false
	));
         
	return($vpcs['Vpcs']);
}                               

/* get the CIDR block associated with a particular VPC */
function getVpcCidr($aws_creds, $vpc_id) {
    
	$client = Ec2Client::factory($aws_creds);
	$vpcs = $client->describeVpcs(array(
		'DryRun' => false,
		'VpcIds' => array($vpc_id)
	));
                
	return($vpcs['Vpcs'][0]['CidrBlock']);
}

function addTags($aws_creds, $resource_id, $tag_key, $tag_value) {

	$client = Ec2Client::factory($aws_creds);
	$result = $client->createTags(array(
		'DryRun' => false,
		'Resources' => array($resource_id),
		'Tags' => array(
			array(
				'Key' => $tag_key,
				'Value' => $tag_value
			)
		)
	));

}


function addVpnGateway($aws_creds, $vpc_id) {

	/* creation of a VPN Gateway and attaching it to a particular
	 * VPC are two separate operations in the API.
	 */

	$client = Ec2Client::factory($aws_creds);
	$vp_gw = $client->createVpnGateway(array(
			'DryRun' => false,
			'Type' => 'ipsec.1'
		)	
	);

	/* set a tag to name this gateway */
	$vp_gw_id = $vp_gw['VpnGateway']['VpnGatewayId'];
	$gw_name = "auto-created by pfSense - VPC $vpc_id";
	addTags($aws_creds, $vp_gw_id, 'Name', $gw_name);

	/* attach the gateway to the VPC */
	$vpc_att = $client->attachVpnGateway(array(
			'DryRun' => false,
			'VpnGatewayId' => $vp_gw_id,
			'VpcId' => $vpc_id
		)
	);

	return($vp_gw_id);
}

/*
 *  Find whether a "VPN Gateway" (AWS object) is attached to a particular VPC
 * and return it's ID. If none is attached, create one and attach it.
 */
function getVpnGateway($aws_creds, $vpc_id) {

	global $savemsg;

	$client = Ec2Client::factory($aws_creds);
	$vp_gws = $client->describeVpnGateways(array(
		'DryRun' => false,
		'Filters' => array(
			array(
				'Name' => 'attachment.vpc-id',
				'Values' => array($vpc_id)
			),
			array(
				'Name' => 'state',
				'Values' => array('available')
			)
		)
	));

	if (count($vp_gws['VpnGateways']) > 0) {
		$vp_gw_id = $vp_gws['VpnGateways'][0]['VpnGatewayId'];
		$savemsg .= "<br>Using existing VPN gateway $vp_gw_id.";
	} else {
		$vp_gw_id = addVpnGateway($aws_creds, $vpc_id);
		$savemsg .= "<br>Added new VPN gateway $vp_gw_id.";
	}
                
	return($vp_gw_id);
}

function addCustomerGateway($aws_creds, $pub_ip, $bgp_asn = '65000') {

	/*
	 * Create a new customer gateway with the appropriate public IP address.
	 * Return the ID.
	 */

	$client = Ec2Client::factory($aws_creds);
	$result = $client->createCustomerGateway(array(
			'DryRun' => false,
			'Type' => 'ipsec.1',
			'PublicIp' => $pub_ip,
			'BgpAsn' => $bgp_asn
		)
	);

	$cust_gw_id = $result['CustomerGateway']['CustomerGatewayId'];

	/* set a name for this customer gw */
	$gw_name = "Auto-created by pfSense - $pub_ip";
	if (isset($cust_gw_id)) {
		addTags($aws_creds, $cust_gw_id, 'Name', $gw_name);
	}

	return($cust_gw_id);
}

function getCustomerGateway($aws_creds, $pub_ip, $bgp_asn = '65000') {

	global $savemsg;

	/*
	 * Search for a customer gateway matching the public IP address that
	 * the user entered. If there is none, create one. Return the ID.
	 */

	$client = Ec2Client::factory($aws_creds);
	$cust_gws = $client->describeCustomerGateways(
		array(
			'DryRun' => false,
			'Filters' =>
			array(
				array(
					'Name' => 'ip-address',
					'Values' => array($pub_ip)
				),
				array(
					'Name' => 'state',
					'Values' => array('available')
				)
			)
		)
	);

	if (count($cust_gws['CustomerGateways']) > 0) {
		$cust_gw_id = $cust_gws['CustomerGateways'][0]['CustomerGatewayId'
];
		$savemsg .= gettext("<br>Using existing customer gateway $cust_gw_id.");
	} else {
		$cust_gw_id = addCustomerGateway($aws_creds, $pub_ip, $bgp_asn);
		$savemsg .= gettext("<br>Added customer gateway $cust_gw_id.");
	}
        
	return($cust_gw_id);
}

function addVpnConnection($aws_creds, $vp_gw_id, $cust_gw_id, $static_only) {

	/* create a new VPN connection */
	$client = Ec2Client::factory($aws_creds);
	$result = $client->createVpnConnection(array(
		'DryRun' => false,
		'Type' => 'ipsec.1',
		'CustomerGatewayId' => $cust_gw_id,
		'VpnGatewayId' => $vp_gw_id,
		'Options' => array(
			'StaticRoutesOnly' => $static_only
		)
	));

	$vpn_conn_id = $result['VpnConnection']['VpnConnectionId'];
	$vpn_conn_name = "Auto-created by pfSense - VPN gateway $vp_gw_id to Customer gateway $cust_gw_id";
	if (isset($vpn_conn_id))
		addTags($aws_creds, $vpn_conn_id, 'Name', $vpn_conn_name);

	return($result['VpnConnection']);
}

function getVpnConnection($aws_creds, $vp_gw_id, $cust_gw_id, $static_only) {

	global $savemsg;
	/*
	 * Search for VPN connections matching the customer gateway ID and
	 * VPN gateway ID. If none exist, create a new one. Otherwise use
	 * the existing one.
	 */

	$client = Ec2Client::factory($aws_creds);
	$vpn_conns = $client->describeVpnConnections(array(
		'DryRun' => false,
		'Filters' => array(
			array(
				'Name' => 'customer-gateway-id',
				'Values' => array($cust_gw_id)
			),
			array(
				'Name' => 'vpn-gateway-id',
				'Values' => array($vp_gw_id)
			),
			array(
				'Name' => 'state',
				'Values' => array('available','pending')
			)
		)
	));

	if (count($vpn_conns['VpnConnections']) > 0) {
		$vpn_conn = $vpn_conns['VpnConnections'][0];
                $savemsg .= gettext("<br>Using existing VPN Connection " . $vpn_conn['VpnConnectionId'] . ".");
        } else {
		$vpn_conn = addVpnConnection($aws_creds, $vp_gw_id, $cust_gw_id, $static_only);
                $savemsg .= gettext("<br>Added VPN Connection " . $vpn_conn['VpnConnectionId'] . ".");
	}

	return($vpn_conn);
}


function updateSecurityGroups($aws_creds, $vpc_id, $loc_cidrs) {

	/*
	 * Add rules to allow traffic inbound to the VPC from the local subnets
	 * on the pfSense side
	 */

	$client = Ec2Client::factory($aws_creds);
	$sec_groups = $client->describeSecurityGroups(
		array(
			'DryRun' => false,
			'Filters' => array(
				array(
					'Name' => 'vpc-id',
					'Values' => array($vpc_id)
				)
			)
		)
	);

	$all_sec_groups = '';
	foreach ($sec_groups['SecurityGroups'] as $sec_group) {
		if ($all_sec_groups != '') {
			$all_sec_groups .= ", ";
		}
		$all_sec_groups .= $sec_group['GroupId'];

		$cidrs_to_add = array();
		foreach (explode(',', $loc_cidrs) as $cidr) {
			$cidr = trim($cidr);
			$cidrs_to_add[$cidr] = true;
		}

		foreach ($sec_group['IpPermissions'] as $rule) {
			if ($rule['IpProtocol'] == '-1' && !isset($rule['FromPort']) && !isset($rule['ToPort'])) {
				foreach ($rule['IpRanges'] as $ip_range) {
					if (isset($cidrs_to_add[$ip_range['CidrIp']])) {
						unset($cidrs_to_add[$ip_range['CidrIp']]);
					}
				}
			}
		}
			

		if (count($cidrs_to_add) == 0)
			continue;

		$cidr_arr = array();
		foreach (array_keys($cidrs_to_add) as $cidr)
			$cidr_arr[] = array('CidrIp' => $cidr);

		$sec_group_id = $sec_group['GroupId'];
		$missing_cidrs = implode(',', array_keys($cidrs_to_add));

		$result = $client->authorizeSecurityGroupIngress(array(
			'DryRun' => false,
			'GroupId' => $sec_group['GroupId'],
			'IpPermissions' => array(
				array(
					'IpProtocol' => '-1',
					'IpRanges' => $cidr_arr
				)
			)
		));
	}
	$savemsg .= gettext("<br>Allowed traffic from local subnets ( $loc_cidrs ) inbound to VPC $vpc_id in security groups $all_sec_groups.");
}


function addVpnConnectionRoutes($aws_creds, $vpn_conn_id, $loc_cidrs) {

	/*
	 * Statically routed VPNs require routes to be manually added to the
	 * VPN connection. Wait for the VPN connection to become available
	 * after creation so that operation can proceed.
	 */

	global $savemsg;

	$client = Ec2Client::factory($aws_creds);
	$client->waitUntil('VpnConnectionAvailable',
		array('VpnConnectionIds' => array($vpn_conn_id))
	);


	$cidr_arr = explode(',', $loc_cidrs);
	foreach ($cidr_arr as $loc_cidr) {
		$loc_cidr = trim($loc_cidr, ' \t');
		$result = $client->createVpnConnectionRoute(array(
			'DryRun' => false,
			'VpnConnectionId' => $vpn_conn_id,
			'DestinationCidrBlock' => $loc_cidr
			)
		);
	}

	$savemsg .= gettext("<br>Added routes for $loc_cidrs to VPN $vpn_conn_id.");

}

function propagateVpnRoutes($aws_creds, $vpc_id, $vp_gw_id) {

	global $savemsg;
	/*
	 * get all of the routing tables for the VPC and cause routes
	 * to the local subnets to be propagated into them
	 */

	$client = Ec2Client::factory($aws_creds);
	$route_tables = $client->describeRouteTables(
		array(
			'DryRun' => false,
			'Filters' => array(
				array(
					'Name' => 'vpc-id',
					'Values' => array($vpc_id)
				)
			)
		)
	);

	$all_route_tables = '';
	foreach ($route_tables['RouteTables'] as $route_table) {

		$route_table_id = $route_table['RouteTableId'];

		if ($all_route_tables != '') {
			$all_route_tables .= ", ";
		}
		$all_route_tables .= $route_table_id;

		/* Figure out whether we need to add it */
		$already_there = false;
		$prop_vp_gws = $route_table['PropagatingVgws'];
		foreach ($prop_vp_gws as $prop_vp_gw) {
			if ($prop_vp_gw['GatewayId'] == $vp_gw_id)
				$already_there = true;
		}

		if (!$already_there) {
			$result = $client->enableVgwRoutePropagation(
				array(
					'RouteTableId' => $route_table_id,
					'GatewayId' => $vp_gw_id
				)
			);
		}
	}
	$savemsg .= gettext("<br>Route propagation for VPN Gateway $vp_gw_id enabled on route tables $all_route_tables.");
			
}


/*
 *
 **** Local functions to aid in making appropriate configurations in pfSense ****
 *
 */

function addVpcAlias($alias_name, $alias_cidrs, $alias_descr) {

	global $config;

	$a_aliases = &$config['aliases']['alias'];

	/*
	 * if the alias already exists, get it's ID
	 * this might happen if a single pfSense firewall is to terminate
	 * tunnels to more than one VPC
	 */
	if (is_alias($alias_name)) {
		$i = 0;
		foreach ($a_aliases as $alias) {
			if ($alias['name'] == $alias_name)
				$alias_id = $i;
			$i++;
		}
	}

	$alias = array();
	$alias_addrs = array();
	$alias_addr_details = array();

	$alias['name'] = $alias_name;
	$alias['type'] = 'network';
	$alias['descr'] = $alias_descr;

	/*
	 * if the alias already exists, merge the new addresses and
	 * de-duplicate. Else we could add keep adding the same local subnets
	 */
	if (isset($alias_id)) {
		$old_addrs = explode(" ", $a_aliases[$alias_id]['address']);
		$merged_addrs = array_merge($old_addrs, $alias_cidrs);
		$alias['address'] = implode(" ", array_unique($merged_addrs));
	} else
		$alias['address'] = implode(" ", $alias_cidrs);

	if (isset($alias_id))
		$a_aliases[$alias_id] = $alias;
	else
		$a_aliases[] = $alias;

	write_config();
}

function cidrsToArray($cidrs) {

	/*
	 * take a comma-separated list of cidrs, which is easy to pass as
	 * a CGI parameter, and convert it to an array, which is easier to
	 * use in processing.
	 */

	$cidr_arr = explode(',', $cidrs);
	foreach ($cidr_arr as $cidr) {
		$cidr = trim($cidr);
	}

	return($cidr_arr);
}

function addVpcAliases($tunnel_cfgs, $vpc_id, $vpc_cidr, $loc_cidrs) {

	global $savemsg;

	/*
	 * a firewall rule will be setup to allow inbound traffic on enc0
	 * from remote VPC subnets to the local subnets that were set up to
	 * be routed over the VPC. It will use aliases for the source and
	 * destination.
	 *
	 * The alias names will be VPC_Remote_<vpcid> and VPC_Local_<vpcid>
	 */

	$our_cidrs = array();
	$aws_cidrs = array();
	
	foreach ($tunnel_cfgs['vpn_connection']['ipsec_tunnel'] as $tunnel_cfg){
		$tun_inside_addr =
		    $tunnel_cfg['customer_gateway']['tunnel_inside_address']['ip_address'];
                $remote_inside_addr =
		    $tunnel_cfg['vpn_gateway']['tunnel_inside_address']['ip_address'];

		$our_cidrs[] = $tun_inside_addr . "/32";
		$aws_cidrs[] = $remote_inside_addr . "/32";
	}

	$aws_cidrs[] = $vpc_cidr;
	$loc_cidr_arr = cidrsToArray($loc_cidrs);
	$our_cidrs = array_merge($our_cidrs, $loc_cidr_arr);
	$vpc_tag = str_replace("-", "", $vpc_id);

	addVpcAlias("VPC_Remote_$vpc_tag", $aws_cidrs, "Networks on the VPC side for $vpc_id");
	addVpcAlias("VPC_Local_$vpc_tag", $our_cidrs, "Networks on the pfSense side for $vpc_id");
	
	$savemsg .= gettext("<br>Aliases VPC_Remote_$vpc_tag and VPC_Local_$vpc_tag added.");
}

function addTunnelInsideAddresses($tunnel_cfgs) {

	global $config, $savemsg;

	$a_virtualip = &$config['virtualip']['vip'];

	$all_ip_addrs = '';
	foreach ($tunnel_cfgs['vpn_connection']['ipsec_tunnel'] as $tunnel_cfg) {

		$ip_addr =
		    $tunnel_cfg['customer_gateway']['tunnel_inside_address']['ip_address'];
		if ($all_ip_addrs != '') {
			$all_ip_addrs .= ',';
		}
		$all_ip_addrs .= $ip_addr;

		$bits =
		    $tunnel_cfg['customer_gateway']['tunnel_inside_address']['network_cidr'];
		$descr =
		    "Inside address for tunnel to " .
		    $tunnel_cfg['vpn_gateway']['tunnel_outside_address']['ip_address'];

		/* skip it if there's already a virtual IP with that address */
		if (find_virtual_ip_alias($ip_addr))
			continue;

		$virtual_ip = array(
			'mode' => 'ipalias',
			'interface' => 'lo0',
			'type' => 'single',
			'subnet' => $ip_addr,
			'subnet_bits' => $bits,
			'descr' => $descr
		);

		$a_virtualip[] = $virtual_ip;
		interface_ipalias_configure($virtual_ip);
	}

	$savemsg .= gettext("<br>Tunnel inside IP addresses $all_ip_addrs configured as virtual IPs");
	write_config();
}


function addTunnelFirewallRules($tunnel_cfgs, $vpc_id) {

	global $config, $savemsg;

	$a_filter = &$config['filter']['rule'];
	$rule_name = "VPC IPsec auto-rule for $vpc_id";
	$vpc_tag = str_replace("-", "", $vpc_id);

	/* if the rule has already been created, don't create it again */
	foreach ($a_filter as $rule)
		if ($rule['descr'] == $rule_name)
			return;

	$filterent = array();
	$filterent['id'] = '';
	$filterent['type'] = 'Pass';
	/* all IPsec traffic filtered on enc0 */
	$filterent['interface'] = 'enc0';
	/* AWS only supports v4 right now*/
	$filterent['ipprotocol'] = 'inet';
	$filterent['statetype'] = 'keep state';
	$filterent['source'] = array(
		'address' => "VPC_Remote_$vpc_tag"
	);
	$filterent['destination'] = array(
		'address' => "VPC_Local_$vpc_tag"
	);
	$filterent['descr'] = $rule_name;
	$filterent['created'] = make_config_revision_entry();

	$a_filter[] = $filterent;

	$savemsg .= gettext("<br>Firewall rule $rule_name added.");
	write_config();
}

function phase1Exists($descr) {

	global $config;

	$phase1_arr = $config['ipsec']['phase1'];
	if (!is_array($phase1_arr)) {
		return(false);
	}

	foreach ($phase1_arr as $phase1) {
		if ($phase1['descr'] == $descr) {
			return(true);
		}
	}

	return(false);
}


function addLocalIpsecCfg($vpc_id, $tunnel_cfgs, $loc_intf, $vpc_cidr, $loc_cidrs) {

	/*
	 * Take the configuration data passed back by the vpn connection
	 * creation, copy it into the local structures and save it in config.xml
	 */

	global $config;

	if (!is_array($config['ipsec']['phase1']))
		$config['ipsec']['phase1'] = array();

	if (!is_array($config['ipsec']['phase2']))
		$config['ipsec']['phase2'] = array();

	$a_phase1 = &$config['ipsec']['phase1'];
	$a_phase2 = &$config['ipsec']['phase2'];
	$config['ipsec']['enable'] = true;

	$all_remote_peers = '';

	foreach ($tunnel_cfgs['vpn_connection']['ipsec_tunnel'] as $tunnel_cfg) {

		$tun_inside_addr = $tunnel_cfg['customer_gateway']['tunnel_inside_address']['ip_address'];
		$tun_inside_bits = $tunnel_cfg['customer_gateway']['tunnel_inside_address']['network_cidr'];
		$remote_inside_addr = $tunnel_cfg['vpn_gateway']['tunnel_inside_address']['ip_address'];

		/* phase 1 stuff */
		$ph1 = array();
		$ph1['ikeid'] = ipsec_ikeid_next();

		$ph1['interface'] = $loc_intf;
		$ph1['remote-gateway'] = $tunnel_cfg['vpn_gateway']['tunnel_outside_address']['ip_address'];
		$ph1['descr'] = "$vpc_id - " . $ph1['remote-gateway'];

		if (phase1Exists($ph1['descr'])) {
			continue;
		}
		if ($all_remote_peers == '') {
			$all_remote_peers .= ",";
		}
		$all_remote_peers .= $ph1['remote-gateway'];
		$ph1['mode'] = $tunnel_cfg['ike']['mode'];
		$ph1['protocol'] = 'inet';
		$ph1['myid_type'] = 'address';
		$ph1['myid_data'] = $tunnel_cfg['customer_gateway']['tunnel_outside_address']['ip_address'];
		$ph1['peerid_type'] = 'peeraddress';
		/* aws returns an encryption algorithm like aes-128-cbc */
		/* right now they always return exactly aes-128-cbc actually */
		$aws_crypto = explode('-', $tunnel_cfg['ike']['encryption_protocol']);
		$ph1['encryption-algorithm'] = array('name' => $aws_crypto[0], 'keylen' => $aws_crypto[1]);
		$ph1['hash-algorithm'] = $tunnel_cfg['ike']['authentication_protocol'];
		$ph1['dhgroup'] = str_replace("group", "", $tunnel_cfg['ike']['perfect_forward_secrecy']);
		$ph1['lifetime'] = $tunnel_cfg['ike']['lifetime'];
		$ph1['pre-shared-key'] = $tunnel_cfg['ike']['pre_shared_key'];
		$ph1['authentication_method'] = 'pre_shared_key';
		$ph1['nat_traversal'] = 'off';
		$ph1['dpd_delay'] = $tunnel_cfg['ipsec']['dead_peer_detection']['interval'];
		$ph1['dpd_maxfail'] = $tunnel_cfg['ipsec']['dead_peer_detection']['retries'];
		$a_phase1[] = $ph1;


		/* phase 2 */
		/* tunnel inside addresses */
		$ph2 = array();
		$ph2['ikeid'] = $ph1['ikeid'];
		$ph2['mode'] = $tunnel_cfg['ipsec']['mode'];
		$ph2['localid'] = array(
			'type' => 'network',
			'address' => $tunnel_cfg['customer_gateway']['tunnel_inside_address']['ip_address'],
			'netbits' => $tunnel_cfg['customer_gateway']['tunnel_inside_address']['network_cidr']
		);		
		$ph2['remoteid'] = array(
			'type' => 'network',
			'address' => $remote_inside_addr,
			'netbits' => $tunnel_cfg['vpn_gateway']['tunnel_inside_address']['network_cidr']
		);
		$ph2['protocol'] = $tunnel_cfg['ipsec']['protocol'];
		$aws_crypto_ph2 = explode('-', $tunnel_cfg['ipsec']['encryption_protocol']);
		$ph2['encryption-algorithm-option'] = array(
			array(
				'name' => $aws_crypto_ph2[0],
				'keylen' => $aws_crypto_ph2[1]
			)
		);
		$aws_hash_ph2 = explode('-', $tunnel_cfg['ipsec']['authentication_protocol']);
		$ph2['hash-algorithm-option'] = $aws_hash_ph2[0] . "_" . $aws_hash_ph2[1];
		$ph2['pfsgroup'] = str_replace("group", "", $tunnel_cfg['ipsec']['perfect_forward_secrecy']);
		$ph2['lifetime'] = $tunnel_cfg['ipsec']['lifetime'];
		$ph2['descr'] = "Tunnel inside endpoint traffic " . $ph2['ikeid'];
		$ph2['pinghost'] = $remote_inside_addr;
		$ph2['uniqid'] = uniqid();
		$a_phase2[] = $ph2;

		/* local subnets to VPC subnet */
		$ph2_cidrs = explode(',', $loc_cidrs);
		$vpc_cidr_arr = explode('/', $vpc_cidr);
		foreach ($ph2_cidrs as $ph2_cidr) {
			$ph2_cidr_arr = explode('/', $ph2_cidr);
			$ph2 = array();
			$ph2['ikeid'] = $ph1['ikeid'];
			$ph2['mode'] = $tunnel_cfg['ipsec']['mode'];
			$ph2['localid'] = array(
				'type' => 'network',
				'address' => $ph2_cidr_arr[0],
				'netbits' => $ph2_cidr_arr[1]
			);
			$ph2['remoteid'] = array(
				'type' => 'network',
				'address' => $vpc_cidr_arr[0],
				'netbits' => $vpc_cidr_arr[1]
			);
			$ph2['protocol'] = $tunnel_cfg['ipsec']['protocol'];
			$aws_crypto_ph2 = explode('-', $tunnel_cfg['ipsec']['encryption_protocol']);
			$ph2['encryption-algorithm-option'] = array(
				array(
					'name' => $aws_crypto_ph2[0],
					'keylen' => $aws_crypto_ph2[1]
				)
			);
			/*
			 * AWS provides values of the format hmac-sha1-96.
			 * pfSense likes values like hmac_sha1.
			 * Discuss.
			 */
			$aws_hash_ph2 = explode('-', $tunnel_cfg['ipsec']['authentication_protocol']);
			$ph2['hash-algorithm-option'] = $aws_hash_ph2[0] . "_" . $aws_hash_ph2[1];
			$ph2['pfsgroup'] = str_replace("group", "", $tunnel_cfg['ipsec']['perfect_forward_secrecy']);
			$ph2['lifetime'] = $tunnel_cfg['ipsec']['lifetime'];
			$ph2['descr'] = "Local net $ph2_cidr to VPN net $vpc_cidr";
			$ph2['uniqid'] = uniqid();
			$a_phase2[] = $ph2;
		}
	}
	$savemsg .= gettext("<br>Configured IPsec VPN to remote peers $all_remote_peers");
	write_config();

}


function is_bgp_group_configured($group_name) {

	global $config;

	if (!is_array($config['installedpackages']) ||
	    !is_array($config['installedpackages']['openbgpdgroups']) ||
	    !is_array($config['installedpackages']['openbgpdgroups']['config'])) {
		return(false);
	}

	foreach ($config['installedpackages']['openbgpdgroups']['config'] as $$group_cfg) {
		if (isset($group_cfg['name']) &&
		    $group_cfg['name'] == $group_name) {
			return(true);
		}
	}

	return(false);
}

function is_bgp_neighbor_configured($neighbor_addr) {

	global $config;

	/* if there's no neighbor configuration, initialize it and return */
	if (!is_array($config['installedpackages']) ||
	    !is_array($config['installedpackages']['openbgpdneighbors']) ||
	    !is_array($config['installedpackages']['openbgpdneighbors']['config'])) {
		return(false);
	}

	/* check existing neighbors for the one we are about to add */
	foreach ($config['installedpackages']['openbgpdneighbors']['config'] as $neighbor_cfg) {
		if (isset($neighbor_cfg['neighbor']) &&
		    $neighbor_cfg['neighbor'] == $neighbor_addr) {
			return(true);
		}
	}

	return(false);
}
	

function configureBgpDaemon($vpc_id, $loc_cidrs, $tunnel_cfgs) {

	/*
	 * install the OpenBGPd package if it's not already installed.
	 * If it's already installed, don't touch the AS settings, just add
	 * a group and neighbors in the group.
	 */

	global $config, $savemsg;

	$pkg_name = "OpenBGPD";
	$install_status = 0;

	/* Quagga OSPF package being installed causes conflict with OpenBGPD.
	 * Don't install OpenBGPD if Quagga OSPF is already installed. 
	 */
	$conflicting_pkg = "Quagga OSPF";
	if (is_package_installed($conflicting_pkg)) {
		$install_status = -1;
		$savemsg .= gettext("<br>The conflicting package $conflicting_pkg is installed. Installing OpenBGPD will cause instability. Please remove $conflicting_pkg or delete this VPN Connection and add a new one using static routing.");
	} elseif (!is_package_installed($pkg_name)) {
		conf_mount_rw();
		$install_status = install_package($pkg_name);
		conf_mount_ro();
	}

	/* if package couldn't be installed, generate configs anyway. In case
	 * manual installation fixed any problems, correct config will be
	 * in place already.
	 */
	if ($install_status == -1) {
		$savemsg .= gettext("<br>Installation of $pkg_name failed. Manual installation and configuration is required.");
	} else {
		$savemsg .= gettext("<br>$pkg_name successfully installed.");
	}

	/* 
	 * get BGP parameters from config data returned by AWS
	 */
	$remote_as = '';
	if (is_array($tunnel_cfgs) && isset($tunnel_cfgs[0]) &&
		isset($tunnel_cfgs[0]['vpn_gateway']) &&
		isset($tunnel_cfgs[0]['vpn_gateway']['bgp'])) {
		$remote_as = $tunnel_cfgs[0]['vpn_gateway']['bgp']['asn'];
	}

	/* check AWS config data for local AS also. */
	$local_as = '';
	if (isset($tunnel_cfgs[0]['customer_gateway']) &&
	    isset($tunnel_cfgs[0]['customer_gateway']['bgp'])) {
		$local_as = $tunnel_cfgs[0]['customer_gateway']['bgp']['asn'];
		$local_hold_time = $tunnel_cfgs[0]['customer_gateway']['bgp']['hold_time'];
	}

	$bgp_cfg = &$config['installedpackages']['openbgpd']['config'][0];

	/* Set system-wide settings if there isn't already an AS 
	 * configured
	 */
	if (!isset($bgp_cfg['asnum']) || !$bgp_cfg['asnum']) {
		$bgp_cfg['asnum'] = $local_as;
		$bgp_cfg['holdtime'] = $local_hold_time;
		$bgp_cfg['fibupdate'] = 'no';
		$bgp_cfg['listenip'] = '';
		$bgp_cfg['routerid'] = '';

		$row_arr = array();
		foreach ($loc_cidrs as $cidr) {
			$row_arr[] = array( 'networks' => $cidr );
		}
		$bgp_cfg['row'] = $row_arr;
	} else {
		$savemsg .= gettext("<br>An existing AS is configured for $pkg_name. Manual verification of configurations required.");
	}



	/* initialize BGP groups config if it's not already there */
	if (!is_array($config['installedpackages']['openbgpdgroups'])) {
		$config['installedpackages']['openbgpdgroups'] = array();
	}
	if (!is_array($config['installedpackages']['openbgpdgroups']['config'])) {
		$config['installedpackages']['openbgpdgroups']['config'] = array();
	}

	/* Add our new group if it is not already configured */
	$bgp_groups = &$config['installedpackages']['openbgpdgroups']['config'];
	if (!is_bgp_group_configured($vpc_id)) {
		$vpc_group = array(
			'name' => $vpc_id,
			'remoteas' => $remote_as,
			'descr' => $vpc_id
		);
		$bgp_groups[] = $vpc_group;
	} else {
		$savemsg .= gettext("<br>BGP group $vpc_id already exists. Manual verification of configuration is required.");
	}

	if (!is_array($config['installedpackages']['openbgpdneighbors'])) {
		$config['installedpackages']['openbgpdneighbors'] = array();
	}
	if (!is_array($config['installedpackages']['openbgpdneighbors']['config'])) {
		$config['installedpackages']['openbgpdneighbors']['config'] = array();
	}

	/* iterate through the tunnel peers and set them up as BGP neighbors */
	foreach ($tunnel_cfgs as $tunnel_cfg) {
		if (!is_array($tunnel_cfg['vpn_gateway']) ||
		    !is_array($tunnel_cfg['vpn_gateway']['tunnel_inside_address']) ||
		    !is_array($tunnel_cfg['customer_gateway']) ||
		    !is_array($tunnel_cfg['customer_gateway']['tunnel_inside_address'])) {
			continue;
		}
		$neighbor_addr = 
		    $tunnel_cfg['vpn_gateway']['tunnel_inside_address']['ip_address'];
		$local_addr =
		    $tunnel_cfg['customer_gateway']['tunnel_inside_address']['ip_address'];
		$neighbor_desc = "$vpc_id $neighbor_addr";
		$bgp_neighbor_arr = array(
			'descr' => $neighbor_desc,
			'neighbor' => $neighbor_addr,
			'md5sigkey' => '',
			'md5sigpass' => '',
			'groupname' => $vpc_id,
			'row' => array(
				array(
					'parameters' => 'announce self'
				),
				array(
					'parameters' => 'local-address',
					'parmvalue' => $local_addr
				),
				array(
					'parameters' => 'holdtime',
					'parmvalue' => '30'
				)
			)
		);

		$neighbor_cfgs = &$config['installedpackages']['openbgpdneighbors']['config'];
		if (!is_bgp_neighbor_configured($neighbor_addr)) {
			$neighbor_cfgs[] = $bgp_neighbor_arr;
		} else {
			$savemsg .= gettext("<br>Neighbor $neighbor_addr already exists. Manual verification of configuration is required.");
		}
	}
	
	write_config();
	return($install_status);
}



function refreshDirtyConfigs() {

	/*
	 * Restart ipsec, refresh firewall rules, re-read aliases, etc.
	 * Any changes that need to be applied after being created/modified
	 * by earlier steps.
	 */

	vpn_ipsec_configure();
	filter_configure();
	if (function_exists("openbgpd_install_conf")) {
		openbgpd_install_conf();
	}

}

function step1_submitphpaction() {
	global $stepid, $savemsg, $config;

	if (!isset($_POST['awskey'])) {
		$savemsg = gettext("You need to enter an access key ID.");
		$stepid--;
		return;
	}

	if (!isset($_POST['secret'])) {
		$savemsg = gettext("You need to enter a secret access key.");
		$stepid--;
		return;
	}

	/* To avoid saving the cleartext AWS API secret password to the
	 * config file, save it as a session variable. */
	session_start();
	$_SESSION['awssecret'] = $_POST['secret'];
	session_write_close();
}

function step2_stepbeforeformdisplay() {
	global $stepid, $savemsg, $config, $pkg;

	session_start();
	if (!isset($_SESSION['awssecret'])) {
		$savemsg .= gettext("<br>AWS Secret Key not found in session data.");
                $stepid=1;
                return;
	}

	$aws_creds = makeCredsArray($config['vpc']['step1']['awskey'],
	    $_SESSION['awssecret'],
	    $config['vpc']['step1']['region']);
	session_write_close();

	try {
		$vpcs = retrieveVPCs($aws_creds);
	} catch (\Aws\Common\Exception\RuntimeException $e) {
		$savemsg = gettext($e->getMessage());
		$stepid--;
		return;
	}

	if (!is_array($vpcs) || (count($vpcs) == 0)) {
		$savemsg = gettext("Did not find any VPCs in the selected region");
		$stepid--;
		return;
	}

	$fields = &$pkg['step'][1]['fields']['field'];
	$fields = array();

	/* select the VPC to connect to */
	$field = array(
		'name' => 'vpcId',
		'type' => 'select',
		'displayname' => 'VPC',
		'description' => 'Select the VPC that you wish to connect to',
		'bindstofield' => 'vpc->step2->vpcid',
		'options' => array( 'option' => array())
	);

	foreach($vpcs as $vpc) {
		$opts = array();
		$opts['name'] = $vpc['VpcId'] . " (" . $vpc['CidrBlock'] . ")";
		$opts['value'] = $vpc['VpcId'];
		$field['options']['option'][] = $opts;
	}

	$fields[] = $field;

	/* add the submit button */
	$field = array();
	$field['name'] = "Next";
	$field['type'] = "submit";
	$fields[] = $field;
}

function step2_submitphpaction() {
	global $config, $savemsg, $stepid;

	$vpc_id = $_POST['vpcid'];
	$config['vpc']['step2']['vpcid'] = $vpc_id;
	$savemsg = gettext("Using VPC {$_POST['vpcid']}.");

	session_start();
	$aws_creds = makeCredsArray(
		$config['vpc']['step1']['awskey'],
		$_SESSION['awssecret'],
		$config['vpc']['step1']['region']
	);
	session_write_close();

	try {
		$vp_gw_id = getVpnGateway($aws_creds, $vpc_id);
		$vpc_cidr = getVpcCidr($aws_creds, $vpc_id);
	} catch (\Aws\Common\Exception\RuntimeException $e) {
		$savemsg = gettext($e->getMessage());
		$stepid--;
		return;
	}
	
	$config['vpc']['step2']['vpgwid'] = $vp_gw_id;
	$config['vpc']['step2']['vpccidr'] = $vpc_cidr;
}

function step3_submitphpaction() {
	global $config, $savemsg, $stepid;
	$savemsg = '';

	$loc_pub_ip = $_POST['locpubip'];
	$loc_cidrs = $_POST['loccidrs'];
	$routing = $_POST['routing'];
	$bgp_asn = $_POST['bgpasn'];

	if (!$bgp_asn) {
		$bgp_asn = '65000';
	}

	$config['vpc']['step3']['locpubip'] = $loc_pub_ip;
	$config['vpc']['step3']['loccidrs'] = $loc_cidrs;
	$config['vpc']['step3']['routing'] = $routing;
	$config['vpc']['step3']['bgpasn'] = $bgp_asn;

	$vpc_id = $config['vpc']['step2']['vpcid'];
	$vp_gw_id = $config['vpc']['step2']['vpgwid'];

	session_start();
	if (!isset($_SESSION['awssecret'])) {
		$savemsg .= gettext("<br>AWS Secret Key not found in session data.");
		$stepid--;
		return;
	}

	$aws_creds = makeCredsArray(
		$config['vpc']['step1']['awskey'],
		$_SESSION['awssecret'],
		$config['vpc']['step1']['region']
	);
	session_write_close();

	try {
		$cust_gw_id = getCustomerGateway($aws_creds, $loc_pub_ip, $bgp_asn);
		$vpn_conn = getVpnConnection(
			$aws_creds,
			$vp_gw_id,
			$cust_gw_id,
			($routing == "static") ? true : false
		);
		$vpn_conn_id = $vpn_conn['VpnConnectionId'];
		$config['vpc']['step3']['custgwcfg'] = $vpn_conn['CustomerGatewayConfiguration'];
		$config['vpc']['step3']['vpnconnid'] = $vpn_conn_id;
		updateSecurityGroups($aws_creds, $vpc_id, $loc_cidrs);

		if ($routing == 'static') {
			addVpnConnectionRoutes($aws_creds, $vpn_conn_id, $loc_cidrs);
		}
		propagateVpnRoutes($aws_creds, $vpc_id, $vp_gw_id);
	} catch (\Aws\Common\Exception\RuntimeException $e) {
		$savemsg = gettext($e->getMessage());
		$stepid--;
		return;
	}
}

function step4_submitphpaction() {
	global $config, $savemsg;
	$savemsg = '';

	$loc_intf = $_POST['interface'];
	$config['vpc']['step4']['interface'] = $loc_intf;

	$aws_creds = makeCredsArray(
		$config['vpc']['step1']['awskey'],
		$_SESSION['awssecret'],
		$config['vpc']['step1']['region']
	);
	$vpc_id = $config['vpc']['step2']['vpcid'];
	$vpc_cidr = $config['vpc']['step2']['vpccidr'];
	$loc_cidrs = $config['vpc']['step3']['loccidrs'];
	$routing = $config['vpc']['step3']['routing'];

	$tunnel_cfgs = xml2array($config['vpc']['step3']['custgwcfg']);

	addVpcAliases($tunnel_cfgs, $vpc_id, $vpc_cidr, $loc_cidrs);
	addTunnelInsideAddresses($tunnel_cfgs);
	addTunnelFirewallRules($tunnel_cfgs, $vpc_id);
	addLocalIpsecCfg($vpc_id, $tunnel_cfgs, $loc_intf, $vpc_cidr, $loc_cidrs);

	if ($routing == "BGP") {
		configureBgpDaemon($vpc_id, cidrsToArray($loc_cidrs), $tunnel_cfgs['vpn_connection']['ipsec_tunnel']);
	}
}

function step5_submitphpaction() {

	refreshDirtyConfigs();
	header("Location: status_ipsec.php");
	exit;

}

?>
