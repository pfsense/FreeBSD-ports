<?php
/*
 * haproxy_global.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2009 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013 PiBa-NL
 * Copyright (C) 2008 Remco Hoef <remcoverhoef@pfsense.com>
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

$shortcut_section = "haproxy";
include_once("guiconfig.inc");
include_once("globals.inc");
require_once("haproxy/haproxy.inc");
require_once("haproxy/haproxy_utils.inc");
require_once("haproxy/haproxy_htmllist.inc");
require_once("haproxy/pkg_haproxy_tabs.inc");

$simplefields = array('localstats_refreshtime', 'localstats_sticktable_refreshtime', 'log-send-hostname', 'ssldefaultdhparam',
  'email_level', 'email_myhostname', 'email_from', 'email_to',
  'resolver_retries', 'resolver_timeoutretry', 'resolver_holdvalid');

$none = array();
$none['']['name'] = "Dont log";
$a_sysloglevel = $a_sysloglevel;

$fields_mailers = array();
$fields_mailers[0]['name'] = "name";
$fields_mailers[0]['columnheader'] = "Name";
$fields_mailers[0]['colwidth'] = "30%";
$fields_mailers[0]['type'] = "textbox";
$fields_mailers[0]['size'] = "20";
$fields_mailers[1]['name'] = "mailserver";
$fields_mailers[1]['columnheader'] = "Mailserver";
$fields_mailers[1]['colwidth'] = "60%";
$fields_mailers[1]['type'] = "textbox";
$fields_mailers[1]['size'] = "60";
$fields_mailers[2]['name'] = "mailserverport";
$fields_mailers[2]['columnheader'] = "Mailserverport";
$fields_mailers[2]['colwidth'] = "10%";
$fields_mailers[2]['type'] = "textbox";
$fields_mailers[2]['size'] = "10";

$fields_resolvers = array();
$fields_resolvers[0]['name'] = "name";
$fields_resolvers[0]['columnheader'] = "Name";
$fields_resolvers[0]['colwidth'] = "30%";
$fields_resolvers[0]['type'] = "textbox";
$fields_resolvers[0]['size'] = "20";
$fields_resolvers[1]['name'] = "server";
$fields_resolvers[1]['columnheader'] = "DNSserver";
$fields_resolvers[1]['colwidth'] = "60%";
$fields_resolvers[1]['type'] = "textbox";
$fields_resolvers[1]['size'] = "60";
$fields_resolvers[2]['name'] = "port";
$fields_resolvers[2]['columnheader'] = "DNSport";
$fields_resolvers[2]['colwidth'] = "10%";
$fields_resolvers[2]['type'] = "textbox";
$fields_resolvers[2]['size'] = "10";

$mailerslist = new HaproxyHtmlList("table_mailers", $fields_mailers);
$mailerslist->keyfield = "name";
$resolverslist = new HaproxyHtmlList("table_resolvers", $fields_resolvers);
$resolverslist->keyfield = "name";

if (!is_array($config['installedpackages']['haproxy'])) 
	$config['installedpackages']['haproxy'] = array();

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;
	
	if ($_POST['calculate_certificate_chain']) {
		$changed = haproxy_recalculate_certifcate_chain();
		if ($changed > 0)
			touch($d_haproxyconfdirty_path);
	} else
	if ($_POST['apply']) {
		$result = haproxy_check_and_run($savemsg, true);
		if ($result)
			unlink_if_exists($d_haproxyconfdirty_path);
	} else {
		$a_mailers = $mailerslist->haproxy_htmllist_get_values();
		$a_resolvers = $resolverslist->haproxy_htmllist_get_values();

		if ($_POST['carpdev'] == "disabled")
			unset($_POST['carpdev']);

		if ($_POST['maxconn'] && (!is_numeric($_POST['maxconn']))) 
			$input_errors[] = "The maximum number of connections should be numeric.";
			
		if ($_POST['localstatsport'] && (!is_numeric($_POST['localstatsport']))) 
			$input_errors[] = "The local stats port should be numeric or empty.";
			
		if ($_POST['localstats_refreshtime'] && (!is_numeric($_POST['localstats_refreshtime']))) 
			$input_errors[] = "The local stats refresh time should be numeric or empty.";

		if ($_POST['localstats_sticktable_refreshtime'] && (!is_numeric($_POST['localstats_sticktable_refreshtime']))) 
			$input_errors[] = "The local stats sticktable refresh time should be numeric or empty.";

		if (!$input_errors) {
			$config['installedpackages']['haproxy']['email_mailers']['item'] = $a_mailers;
			$config['installedpackages']['haproxy']['dns_resolvers']['item'] = $a_resolvers;
		
			$config['installedpackages']['haproxy']['enable'] = $_POST['enable'] ? true : false;
			$config['installedpackages']['haproxy']['terminate_on_reload'] = $_POST['terminate_on_reload'] ? true : false;
			$config['installedpackages']['haproxy']['maxconn'] = $_POST['maxconn'] ? $_POST['maxconn'] : false;
			$config['installedpackages']['haproxy']['enablesync'] = $_POST['enablesync'] ? true : false;
			$config['installedpackages']['haproxy']['remotesyslog'] = $_POST['remotesyslog'] ? $_POST['remotesyslog'] : false;
			$config['installedpackages']['haproxy']['logfacility'] = $_POST['logfacility'] ? $_POST['logfacility'] : false;
			$config['installedpackages']['haproxy']['loglevel'] = $_POST['loglevel'] ? $_POST['loglevel'] : false;
			$config['installedpackages']['haproxy']['carpdev'] = $_POST['carpdev'] ? $_POST['carpdev'] : false;
			$config['installedpackages']['haproxy']['localstatsport'] = $_POST['localstatsport'] ? $_POST['localstatsport'] : false;
			$config['installedpackages']['haproxy']['advanced'] = $_POST['advanced'] ? base64_encode($_POST['advanced']) : false;
			$config['installedpackages']['haproxy']['nbproc'] = $_POST['nbproc'] ? $_POST['nbproc'] : false;			
			foreach($simplefields as $stat)
				$config['installedpackages']['haproxy'][$stat] = $_POST[$stat];
			touch($d_haproxyconfdirty_path);
			write_config();
		}
	}
}

$a_mailers = $config['installedpackages']['haproxy']['email_mailers']['item'];
if (!is_array($a_mailers)) {
	$a_mailers = array();
}
$a_resolvers = $config['installedpackages']['haproxy']['dns_resolvers']['item'];
if (!is_array($a_resolvers)) {
	$a_resolvers = array();
}

$pconfig['enable'] = isset($config['installedpackages']['haproxy']['enable']);
$pconfig['terminate_on_reload'] = isset($config['installedpackages']['haproxy']['terminate_on_reload']);
$pconfig['maxconn'] = $config['installedpackages']['haproxy']['maxconn'];
$pconfig['enablesync'] = isset($config['installedpackages']['haproxy']['enablesync']);
$pconfig['remotesyslog'] = $config['installedpackages']['haproxy']['remotesyslog'];
$pconfig['logfacility'] = $config['installedpackages']['haproxy']['logfacility'];
$pconfig['loglevel'] = $config['installedpackages']['haproxy']['loglevel'];
$pconfig['carpdev'] = $config['installedpackages']['haproxy']['carpdev'];
$pconfig['localstatsport'] = $config['installedpackages']['haproxy']['localstatsport'];
$pconfig['advanced'] = base64_decode($config['installedpackages']['haproxy']['advanced']);
$pconfig['nbproc'] = $config['installedpackages']['haproxy']['nbproc'];
foreach($simplefields as $stat) {
	$pconfig[$stat] = $config['installedpackages']['haproxy'][$stat];
}

// defaults
if (!$pconfig['logfacility'])
	$pconfig['logfacility'] = 'local0';
if (!$pconfig['loglevel'])
	$pconfig['loglevel'] = 'info';

$pgtitle = array(gettext("Services"), gettext("HAProxy"), gettext("Settings"));
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg);
}
if (file_exists($d_haproxyconfdirty_path)) {
	print_apply_box(sprintf(gettext("The haproxy configuration has been changed.%sYou must apply the changes in order for them to take effect."), "<br/>"));
}
haproxy_display_top_tabs_active($haproxy_tab_array['haproxy'], "settings");

$counter = 0; // used by htmllist Draw() function.

$form = new Form;

$section = new Form_Section("General settings");

$section->addInput(new Form_Checkbox(
	'enable',
	'',
	'Enable HAProxy',
	$pconfig['enable']
));

$haproxy_version = haproxy_version();

$section->addInput(new Form_StaticText(
	'Installed version',
	$haproxy_version
))->setHelp("");


$maxfiles = `sysctl kern.maxfiles | awk '{ print $2 }'`;
$maxfilesperproc = `sysctl kern.maxfilesperproc | awk '{ print $2 }'`;
$memusage = trim(`ps auxw | grep "haproxy -f" | grep -v grep | awk '{ print $5 }'`);
if ($memusage) {
	$memusage = "Current memory usage: <b>{$memusage} kB.</b><br/>";
}
	$group = new Form_Group("Maximum connections");
	$group->add(new Form_Input(
		'maxconn',
		'',
		'text',
		$pconfig['maxconn'],
		array()
	))->setIsRequired()->setWidth(5)->setHelp(<<<EOD
Sets the maximum per-process number of concurrent connections to X.<br/>
					<strong>NOTE:</strong> setting this value too high will result in HAProxy not being able to allocate enough memory.<br/>
				{$memusage}
					Current <a href='/system_advanced_sysctl.php'>'System Tunables'</a> settings.<br/>
					&nbsp;&nbsp;'kern.maxfiles': <b>{$maxfiles}</b><br/> 
					&nbsp;&nbsp;'kern.maxfilesperproc': <b>{$maxfilesperproc}</b><br/>
					
					Full memory usage will only show after all connections have actually been used.
EOD
);
	$group->add(new Form_StaticText("","per process."));

	$group->add(new Form_StaticText(
		'', <<<EOD
<table style="border: 1px solid #000;">
	<tr>
		<td><small>Connections</small></td>
		<td>&nbsp;</td>
		<td><small>Memory usage</small></td>
	</tr>
	<tr>
		<td colspan="3">
			<hr style="border: 1px solid #000;" />
		</td>
	</tr>
	<tr>
		<td style="text-align: right;"><small>1</small></td>
		<td>&nbsp;</td>
		<td><small>50 kB</small></td>
	</tr>
	<tr>
		<td style="text-align: right;"><small>1.000</small></td>
		<td>&nbsp;</td>
		<td><small>48 MB</small></td>
	</tr>
	<tr>
		<td style="text-align: right;"><small>10.000</small></td>
		<td>&nbsp;</td>
		<td><small>488 MB</small></td>
	</tr>
	<tr>
		<td style="text-align: right;"><small>100.000</small></td>
		<td>&nbsp;</td>
		<td><small>4,8 GB</small></td>
	</tr>
	<tr>
		<td colspan="3" style="white-space: nowrap"><small><small>Calculated for plain HTTP connections,<br/>using ssl offloading will increase this.</small></small></td>
	</tr>
</table>
EOD
	));
$group->setHelp(<<<EOD
When setting a high amount of allowed simultaneous connections you will need to add and or increase the following two 
<b><a href='/system_advanced_sysctl.php'>'System Tunables'</a></b> kern.maxfiles and kern.maxfilesperproc.
For HAProxy alone set these to at least the number of allowed connections * 2 + 31. So for 100.000 connections these need
to be 200.031 or more to avoid trouble, take into account that handles are also used by other processes when setting kern.maxfiles.
EOD
);

$section->add($group);

$cpucores = trim(`/sbin/sysctl kern.smp.cpus | cut -d" " -f2`);

$section->addInput(new Form_Input('nbproc', 'Number of processes to start', 'text', $pconfig['nbproc']
))->setHelp(<<<EOD
	Defaults to 1 if left blank ({$cpucores} CPU core(s) detected).<br/>
	Note : Consider leaving this value empty or 1  because in multi-process mode (nbproc > 1) memory is not shared between the processes, which could result in random behaviours for several options like ACL's, sticky connections, stats pages, admin maintenance options and some others.<br/>
	For more information about the <b>"nbproc"</b> option please see <b><a href='http://cbonte.github.io/haproxy-dconv/1.7/configuration.html#nbproc' target='_blank'>HAProxy Documentation</a></b>
EOD
);


$section->addInput(new Form_Checkbox(
	'terminate_on_reload',
	'Reload behaviour',
	'Force immediate stop of old process on reload. (closes existing connections)',
	$pconfig['terminate_on_reload']
))->setHelp(<<<EOD
	Note: when this option is selected connections will be closed when haproxy is restarted.
	Otherwise the existing connections will be served by the old haproxy process untill they are closed.
	Checking this option will interupt existing connections on a restart. (which happens when the configuration is applied,
	but possibly also when pfSense detects an interface comming up or changing its ip-address)
EOD
);


$vipinterfaces = array();
$vipinterfaces[] = array('ip' => '', 'name' => 'Disabled');
$vipinterfaces += haproxy_get_bindable_interfaces($ipv="ipv4,ipv6", $interfacetype="carp");

$section->addInput(new Form_Select(
	'carpdev',
	'Carp monitor',
	$pconfig['carpdev'],
	haproxy_keyvalue_array($vipinterfaces)
))->setHelp("Monitor carp interface and only run haproxy on the firewall which is MASTER.");

$form->add($section);

$section = new Form_Section("Stats tab, 'internal' stats port");
$section->add(group_input_with_text('localstatsport', 'Internal stats port', 'number', $pconfig['localstatsport'], array(), "EXAMPLE: 2200"
))->setHelp(<<<EOD
Sets the internal port to be used for the stats tab.
This is bound to 127.0.0.1 so will not be directly exposed on any LAN/WAN/other interface. It is used to internally pass through the stats page.
Leave this setting empty to remove the "HAProxyLocalStats" item from the stats page and save a little on recources.
EOD
);
$section->add(group_input_with_text('localstats_refreshtime', 'Internal stats refresh rate', 'text', $pconfig['localstats_refreshtime'], array(), "Seconds, Leave this setting empty to not refresh the page automatically. EXAMPLE: 10"
))->setHelp("");
$section->add(group_input_with_text('localstats_sticktable_refreshtime', 'Sticktable page refresh rate', 'text', $pconfig['localstats_sticktable_refreshtime'], array(), "Seconds, Leave this setting empty to not refresh the page automatically. EXAMPLE: 10"
))->setHelp("");
$form->add($section);

$section = new Form_Section('Logging');
$section->addInput(new Form_Input('remotesyslog', 'Remote syslog host', 'text', $pconfig['remotesyslog']
))->setHelp('To log to the local pfSense systemlog fill the host with the value <b>/var/run/log</b>, however if a lot of messages are generated logging is likely to be incomplete. (Also currently no informational logging gets shown in the systemlog.)');
$section->addInput(new Form_Select(
	'logfacility',
	'Syslog facility',
	$pconfig['logfacility'],
	haproxy_keyvalue_array($a_facilities))
);
$section->addInput(new Form_Select(
	'loglevel',
	'Syslog level',
	$pconfig['loglevel'],
	haproxy_keyvalue_array($a_sysloglevel))
);
$section->add(group_input_with_text('log-send-hostname', 'Log hostname', 'text', $pconfig['log-send-hostname'], array(), "EXAMPLE: HaproxyMasterNode"
))->setHelp('Sets the hostname field in the syslog header. If empty defaults to the system hostname.');
$form->add($section);


$section = new Form_Section('Global DNS resolvers for haproxy');
$section->addInput(new Form_StaticText(
	'DNS servers',
	"Configuring DNS servers will allow haproxy to detect when a servers IP changes to a different one in 'elastic' environments without needing to be restarted.<br/>".
	$resolverslist->Draw($a_resolvers)
));
$section->addInput(new Form_Input('resolver_retries', 'Retries', 'text', $pconfig['resolver_retries']
))->setHelp('Defines the number of queries to send to resolve a server name before giving up. Default value: 3');
$section->addInput(new Form_Input('resolver_timeoutretry', 'Retry timeout', 'text', $pconfig['resolver_timeoutretry']
))->setHelp('Time between two DNS queries, when no response have been received. Default value: 1s');
$section->addInput(new Form_Input('resolver_holdvalid', 'Interval', 'text', $pconfig['resolver_holdvalid']
))->setHelp('Interval between two successive name resolution when the last answer was valid. Default value: 10s');
$form->add($section);

$section = new Form_Section('Global email notifications');
$section->addInput(new Form_StaticText(
	'Mailer servers',
	"It is possible to send email alerts when the state of servers changes. If configured email alerts are sent to each mailer that is configured in a mailers section. Email is sent to mailers using SMTP.<br/>".
	$mailerslist->Draw($a_mailers)
));
$section->addInput(new Form_Select(
	'email_level',
	'Mail level',
	$pconfig['email_level'],
	haproxy_keyvalue_array($none + $a_sysloglevel)))
->setHelp(<<<EOD
	Define the maximum loglevel to send emails for.
EOD
);

$section->addInput(new Form_Input('email_myhostname', 'Mail myhostname', 'text', $pconfig['email_myhostname']
))->setHelp('Define hostname to use as sending the emails.');
$section->addInput(new Form_Input('email_from', 'Mail from', 'text', $pconfig['email_from']
))->setHelp('Email address to be used as the sender of the emails.');
$section->addInput(new Form_Input('email_to', 'Mail to', 'text', $pconfig['email_to']
))->setHelp('Email address to send emails to.');
$form->add($section);

$section = new Form_Section('Tuning');

$section->add(group_input_with_text(
	'ssldefaultdhparam',
	'Max SSL Diffie-Hellman size',
	'number',
	$pconfig['ssldefaultdhparam'],
	['min' => 256, 'max' => 102400],
	"EXAMPLE: 2048"
))->setHelp(<<<EOD
	Sets the maximum size of the Diffie-Hellman parameters used for generating
	the ephemeral/temporary Diffie-Hellman key in case of DHE key exchange.
	Minimum and default value is: 1024, bigger values might increase CPU usage.<br/>
	For more information about the <b>"tune.ssl.default-dh-param"</b> option please see <b><a href='http://cbonte.github.io/haproxy-dconv/1.7/configuration.html#tune.ssl.default-dh-param' target='_blank'>HAProxy Documentation</a></b><br/>
	NOTE: HAProxy will emit a warning when starting when this setting is used but not configured.
EOD
);
$form->add($section);

$textrowcount = max(substr_count($pconfig['advanced'],"\n"), 2) + 2;

$section = new Form_Section('Global Advanced pass thru');
$section->addInput(new Form_Textarea (
	'advanced',
	'Custom options',
	$pconfig['advanced']
))->setRows($textrowcount)->addClass('advanced')->setNoWrap()
->setHelp('NOTE: paste text into this box that you would like to pass thru in the global settings area.');
$form->add($section);
/*
Not needed anymore in 2.3 ? When it is add exact testcase/reproduction of the originating issue..
$section = new Form_Section('Recalculate certificate chain.');
$btnclear = new Form_Button(
	'calculate_certificate_chain',
	'Recalculate certificate chains',
	null,
	'calculate_certificate_chain'
);
$btnclear->removeClass('btn-primary')->addClass('btn-danger')->addClass('btn-sm');
$section->addInput(new Form_StaticText(
	'',
	$btnclear . " (Other changes on this page will be lost)"
))->setHelp(<<<EOD
	This can be required after certificates have been created or imported. As pfSense 2.1.0 currently does not
	always keep track of these dependencies which might be required to create a proper certificate chain when using SSLoffloading.
EOD
);
$form->add($section);
*/
$section = new Form_Section('Configuration synchronization');
$section->addInput(new Form_Checkbox(
	'enablesync',
	'HAProxy Sync',
	'Sync HAProxy configuration to backup CARP members via XMLRPC.',
	$pconfig['enablesync']
))->setHelp(<<<EOD
	Note: remember to also turn on HAProxy Sync on the backup nodes.<br/>
	The synchronisation host and password are those configured in pfSense main <a href="/system_hasync.php">"System: High Availability Sync"</a> settings.
EOD
);
$form->add($section);

print $form;

function group_input_with_text($name, $title, $type = 'text', $value = null, array $attributes = array(), $righttext = "")
{
	$group = new Form_Group($title);
	$group->add(new Form_Input(
		$name,
		'',
		$type,
		$value,
		$attributes
	))->setWidth(2);

	$group->add(new Form_StaticText(
		'',
		$righttext
	));
	return $group;
}

if(file_exists("/var/etc/haproxy_test/haproxy.cfg")) {
$btnadv = new Form_Button('btnshowconfigtest', 'Show');
$btnadv->removeClass('btn-primary')->addClass('btn-default btn-sm');
$btn = new Form_StaticText(
	'Additional BOOTP/DHCP Options',
	$btnadv . " automatically generated test configuration."
);
print $btn . "<br/>";

$section = new Form_Section('/var/etc/haproxy_test/haproxy.cfg file contents');
$section->addClass('haproxytestcfg');
$form->add($section);
$section->addInput(new Form_StaticText(
	'Content',
	"<pre>" . htmlspecialchars(trim(file_get_contents("/var/etc/haproxy_test/haproxy.cfg"))) . "</pre>"
));
print $section;
}

if(file_exists("/var/etc/haproxy/haproxy.cfg")) {
$btnadv = new Form_Button('btnadvopts', 'Show');
$btnadv->removeClass('btn-primary')->addClass('btn-default btn-sm');
$btn = new Form_StaticText(
	'Additional BOOTP/DHCP Options',
	$btnadv . " automatically generated configuration."
);
print $btn;

$section = new Form_Section('/var/etc/haproxy/haproxy.cfg file contents');
$section->addClass('haproxycfg');
$form->add($section);
$section->addInput(new Form_StaticText(
	'Content',
	"<pre>" . htmlspecialchars(trim(file_get_contents("/var/etc/haproxy/haproxy.cfg"))) . "</pre>"
));
print $section;
}

?>
<script type="text/javascript">

//<![CDATA[
events.push(function() {
	hideClass('haproxytestcfg', true);

	$('#btnshowconfigtest').prop('type', 'button');
	$('#btnshowconfigtest').click(function(event) {
		hideClass('haproxytestcfg', false);
	});
	hideClass('haproxycfg', true);

	$('#btnadvopts').prop('type', 'button');
	$('#btnadvopts').click(function(event) {
		hideClass('haproxycfg', false);
	});
});
</script>
<?php
haproxy_htmllist_js();
?>
<script type="text/javascript">
	totalrows =  <?php echo $counter; ?>;
<?php
	$mailerslist->outputjavascript();
	$resolverslist->outputjavascript();
?>
</script>
<?php include("foot.inc");
