<?php
/*
 * ocicarp_settings.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2023, Oracle and/or its affiliates.
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

##|+PRIV
##|*IDENT=page-services-ocicarp
##|*NAME=Services: Oracle Cloud Infrastructure CARP
##|*DESCR=Allow access the 'Services: Oracle Cloud Infrastructure CARP' page.
##|*MATCH=ocicarp_settings.php*
##|-PRIV

require('guiconfig.inc');
require('/usr/local/pkg/ocicarp/ocicarp.inc');

$shortcut_section = 'ocicarp';

$ocicarp_config = config_get_path('installedpackages/ocicarp/config/0', []);

if (!empty($ocicarp_config)) {
	if (isset($ocicarp_config['carpvips'])) {
		$pconfig = $ocicarp_config;
	}
} else {
	$pconfig['keep_conf'] = 'yes';
}


unset($input_errors);
unset($input_information);

if ($_POST) {
	$pconfig = $_POST;

	if (isset($pconfig['carpvips_a']) && is_array($pconfig['carpvips_a'])) {
		$pconfig['carpvips'] = implode(',', $pconfig['carpvips_a']);
	}

	$ocicarp = array();

	if ($pconfig['enable']) {
		$ocicarp['enable'] = $pconfig['enable'];
	} else {
		unset($ocicarp['enable']);
	}
	if ($pconfig['keep_conf']) {
		$ocicarp['keep_conf'] = $pconfig['keep_conf'];
	} else {
		unset($ocicarp['keep_conf']);
	}
	$ocicarp['carpvips'] = $pconfig['carpvips'];

	config_set_path('installedpackages/ocicarp/config/0', $ocicarp);
	write_config(gettext('Updated Oracle Cloud Infrastructure CARP settings'));
	ocicarp_sync_config();
	header("Location: ocicarp_settings.php");
	exit;
}


$available_carp_vips = ocicarp_get_oci_suitable_vips();
if (empty($available_carp_vips)) {
	if (is_null($available_carp_vips)) {
		$input_errors[] = gettext('No Oracle Cloud Infrastructure (OCI) metadata was retrievable; unable to match CARP VIPs and interfaces.');
		$input_errors[] = gettext('Please ensure that http://' . OCI_MDS_IP . ' is reachable from this pfSense instance.');
	}
	$input_information = gettext('Unable to find any suitable CARP VIPs. Please ensure unicast CARP VIPs are configured before continuing and that they have been created in OCI.');
}

if (!empty($pconfig['carpvips'])) {
	$pconfig['carpvips_a'] = explode(',', $pconfig['carpvips']);
}

$pgtitle = array(gettext("Services"), gettext("Oracle Cloud Infrastructure CARP"));
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}
if ($input_information) {
	print_info_box($input_information);
}

$form = new Form();
$section = new Form_Section('General Settings');

$section->addInput(new Form_Checkbox(
	'enable',
	gettext('Enable'),
	gettext('Oracle Cloud Infrastructure (OCI) CARP integration'),
	$pconfig['enable']
))->setHelp(
	'<span class="text-danger">'. gettext('Note') .':</span> ' . gettext('Integration will be automatically disabled if the package is reinstalled.')
);

$section->addInput(new Form_Checkbox(
	'keep_conf',
	gettext('Keep Configuration'),
	gettext('Enable'),
	$pconfig['keep_conf'] == 'yes'
))->setHelp(
	'<span class="text-danger">' . gettext('Note') . ':</span> ' . gettext('With \'Keep Configuration\' enabled (default), configuration will persist on install/deinstall.')
);

$section->addInput(new Form_Select(
	'carpvips_a',
	gettext('Suitable CARP VIPs'),
	$pconfig['carpvips_a'],
	is_null($available_carp_vips) ? array() : $available_carp_vips,
	true
))->setHelp(
	gettext('Select the CARP VIPs that OCI will be notified of when they move between nodes. Matched VIP aliases are shown in braces.') . '<br/><br/>' .
	'<span class="text-danger">' . gettext('Note') . ' 1:</span> ' . gettext('Ensure the same VIP are selected on the other node') . '.<br/>'.
	'<span class="text-danger">' . gettext('Note') . ' 2:</span> ' . gettext('Suitable CARP VIPs are unicast and matchable to OCI vNICs.')
);

$form->add($section);

print($form);
?>

<div class="infoblock">
	<?=print_info_box('
		<p>' . gettext('This package will only work when pfSense is running on Oracle Cloud Infrastructure (OCI). A number of prerequisites must also be met before this package will function correctly:') . '</p>
		<ol type="1">
			<li>' . gettext('OCI must be configured with a dynamic group and suitable policy to allow pfSense instances to use <strong><a href="https://docs.oracle.com/en-us/iaas/Content/Identity/Tasks/callingservicesfrominstances.htm">Instance Principals</a></strong> to manage IP addresses for their vNICs.') . '</li>
			<li>' . gettext('Ensure that pfSense can reach the OCI instance metadata service at http://' . OCI_MDS_IP . ' as this is used to gather details about the vNICs that pfSense is using.') . '</li>
			<li>' . gettext('pfSense must be able to reach OCI API endpoints either via an OCI service gateway, NAT gateway or internet gateway. Thus OCI subnet routing must be setup appropriately.') . '</li>
			<li>' . gettext('Ensure that for each <em>unicast</em> IPv4 and IPv6 CARP VIP, there exists an equivalent OCI secondary IP address that is assigned to a vNIC (ideally on the primary instance of the cluster).') . '</li>
		</ol>
		<p>' . gettext('For more information on primary and secondary IP addresses see the <strong><a href="https://docs.oracle.com/en-us/iaas/Content/Network/Tasks/managingIPaddresses.htm">Private IP Addresses</a></strong> documentation. For more information on instance primary and secondary vNICs see the <strong><a href="https://docs.oracle.com/en-us/iaas/Content/Network/Tasks/managingVNICs.htm">Virtual Network Interface Cards (VNICs)</a></strong> documentation.') . '</p>
		<p>' . gettext('As this package uses the OCI CLI for interacting with OCI, the OCI configuration for <strong><a href="https://docs.oracle.com/en-us/iaas/Content/Identity/Tasks/callingservicesfrominstances.htm">Instance Principals</a></strong> and API access can be checked from a shell on the pfSense instance. First, copy the OCID of a pfSense vNIC from the OCI console and run a command like the following substituting your OCID:<br/><pre>oci --auth instance_principal network private-ip list --vnic-id PLACE-OCID-HERE</pre>This should return JSON data for IP addresses of the associated vNIC and not an error. Something like:') . '</p>
<pre>
{
  "data": [
    {
      "availability-domain": "xyzzy",
      "compartment-id": "compartment-ocid-appears-here",
      "defined-tags": {
        "Oracle-Tags": {
          "CreatedBy": "someone@example.com",
          "CreatedOn": "1970-01-1T00:00:00.000Z",
        }
      },
      "display-name": "private",
      "freeform-tags": {},
      "hostname-label": null,
      "id": "private-ip-ocid-appears-here",
      "ip-address": "192.168.10.11",
      "is-primary": true,
      "subnet-id": "subnet-ocid-appears-here",
      "time-created": "1970-01-01T00:00:00.000000+00:00",
      "vlan-id": null,
      "vnic-id": "vnic-ocid-appears-here"
    }
  ]
}
</pre>', 'info')?>
</div>

<?php
include("foot.inc");
?>
