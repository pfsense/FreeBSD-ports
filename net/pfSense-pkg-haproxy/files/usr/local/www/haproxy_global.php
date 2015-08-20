<?php
/* $Id: load_balancer_pool.php,v 1.5.2.6 2007/03/02 23:48:32 smos Exp $ */
/*
	haproxy_global.php
	part of pfSense (https://www.pfsense.org/)
	Copyright (C) 2013 PiBa-NL
	Copyright (C) 2009 Scott Ullrich <sullrich@pfsense.com>
	Copyright (C) 2008 Remco Hoef <remcoverhoef@pfsense.com>
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
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
$shortcut_section = "haproxy";
require_once("guiconfig.inc");
require_once("haproxy.inc");
require_once("haproxy_utils.inc");
require_once("globals.inc");
require_once("pkg_haproxy_tabs.inc");

$simplefields = array('localstats_refreshtime','localstats_sticktable_refreshtime','log-send-hostname','ssldefaultdhparam');

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
		//if ($_POST['enable']) {
		//	$reqdfields = explode(" ", "maxconn");
		//	$reqdfieldsn = explode(",", "Maximum connections");
		//}

		if ($_POST['carpdev'] == "disabled")
			unset($_POST['carpdev']);

		//do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

		if ($_POST['maxconn'] && (!is_numeric($_POST['maxconn']))) 
			$input_errors[] = "The maximum number of connections should be numeric.";
			
		if ($_POST['localstatsport'] && (!is_numeric($_POST['localstatsport']))) 
			$input_errors[] = "The local stats port should be numeric or empty.";
			
		if ($_POST['localstats_refreshtime'] && (!is_numeric($_POST['localstats_refreshtime']))) 
			$input_errors[] = "The local stats refresh time should be numeric or empty.";

		if ($_POST['localstats_sticktable_refreshtime'] && (!is_numeric($_POST['localstats_sticktable_refreshtime']))) 
			$input_errors[] = "The local stats sticktable refresh time should be numeric or empty.";

		/*if($_POST['synchost1'] && !is_ipaddr($_POST['synchost1']))
			$input_errors[] = "Synchost1 needs to be an IPAddress.";
		if($_POST['synchost2'] && !is_ipaddr($_POST['synchost2']))
			$input_errors[] = "Synchost2 needs to be an IPAddress.";
		if($_POST['synchost3'] && !is_ipaddr($_POST['synchost3']))
			$input_errors[] = "Synchost3 needs to be an IPAddress.";*/

		if (!$input_errors) {
			$config['installedpackages']['haproxy']['enable'] = $_POST['enable'] ? true : false;
			$config['installedpackages']['haproxy']['terminate_on_reload'] = $_POST['terminate_on_reload'] ? true : false;
			$config['installedpackages']['haproxy']['maxconn'] = $_POST['maxconn'] ? $_POST['maxconn'] : false;
			$config['installedpackages']['haproxy']['enablesync'] = $_POST['enablesync'] ? true : false;
			//$config['installedpackages']['haproxy']['synchost1'] = $_POST['synchost1'] ? $_POST['synchost1'] : false;
			//$config['installedpackages']['haproxy']['synchost2'] = $_POST['synchost2'] ? $_POST['synchost2'] : false;
			//$config['installedpackages']['haproxy']['synchost2'] = $_POST['synchost3'] ? $_POST['synchost3'] : false;
			$config['installedpackages']['haproxy']['remotesyslog'] = $_POST['remotesyslog'] ? $_POST['remotesyslog'] : false;
			$config['installedpackages']['haproxy']['logfacility'] = $_POST['logfacility'] ? $_POST['logfacility'] : false;
			$config['installedpackages']['haproxy']['loglevel'] = $_POST['loglevel'] ? $_POST['loglevel'] : false;
			$config['installedpackages']['haproxy']['carpdev'] = $_POST['carpdev'] ? $_POST['carpdev'] : false;
			//$config['installedpackages']['haproxy']['syncpassword'] = $_POST['syncpassword'] ? $_POST['syncpassword'] : false;
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

$pconfig['enable'] = isset($config['installedpackages']['haproxy']['enable']);
$pconfig['terminate_on_reload'] = isset($config['installedpackages']['haproxy']['terminate_on_reload']);
$pconfig['maxconn'] = $config['installedpackages']['haproxy']['maxconn'];
$pconfig['enablesync'] = isset($config['installedpackages']['haproxy']['enablesync']);
//$pconfig['syncpassword'] = $config['installedpackages']['haproxy']['syncpassword'];
//$pconfig['synchost1'] = $config['installedpackages']['haproxy']['synchost1'];
//$pconfig['synchost2'] = $config['installedpackages']['haproxy']['synchost2'];
//$pconfig['synchost3'] = $config['installedpackages']['haproxy']['synchost3'];
$pconfig['remotesyslog'] = $config['installedpackages']['haproxy']['remotesyslog'];
$pconfig['logfacility'] = $config['installedpackages']['haproxy']['logfacility'];
$pconfig['loglevel'] = $config['installedpackages']['haproxy']['loglevel'];
$pconfig['carpdev'] = $config['installedpackages']['haproxy']['carpdev'];
$pconfig['localstatsport'] = $config['installedpackages']['haproxy']['localstatsport'];
$pconfig['advanced'] = base64_decode($config['installedpackages']['haproxy']['advanced']);
$pconfig['nbproc'] = $config['installedpackages']['haproxy']['nbproc'];
foreach($simplefields as $stat)
	$pconfig[$stat] = $config['installedpackages']['haproxy'][$stat];

// defaults
if (!$pconfig['logfacility'])
	$pconfig['logfacility'] = 'local0';
if (!$pconfig['loglevel'])
	$pconfig['loglevel'] = 'info';

$pf_version=substr(trim(file_get_contents("/etc/version")),0,3);
if ($pf_version < 2.0)
	$one_two = true;

$pgtitle = "Services: HAProxy: Settings";
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<script type="text/javascript" src="javascript/scriptaculous/prototype.js"></script>
<script type="text/javascript" src="javascript/scriptaculous/scriptaculous.js"></script>
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
<!--
function enable_change(enable_change) {
	var endis;
	endis = !(document.iform.enable.checked || enable_change);
	document.iform.maxconn.disabled = endis;
}
//-->
</script>
<?php if($one_two): ?>
<p class="pgtitle"><?=$pgtitle?></p>
<?php endif; ?>
<form action="haproxy_global.php" method="post" name="iform">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (file_exists($d_haproxyconfdirty_path)): ?>
<?php print_info_box_np("The haproxy configuration has been changed.<br/>You must apply the changes in order for them to take effect.");?><br/>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr><td class="tabnavtbl">
	<?php
	haproxy_display_top_tabs_active($haproxy_tab_array['haproxy'], "settings");
	?>
	</td></tr>
	<tr>
	<td>
	<div id="mainarea">
		<table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
			<tr>
				<td colspan="2" valign="top" class="listtopic">General settings</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell">&nbsp;</td>
				<td width="78%" class="vtable">
				<input name="enable" type="checkbox" value="yes" <?php if ($pconfig['enable']) echo "checked"; ?> onClick="enable_change(false)" />
				<strong>Enable HAProxy</strong></td>
			</tr>
			<tr>
				<td valign="top" class="vncell">
					Maximum connections
				</td>
				<td class="vtable">
					<table><tr><td>
					<table cellpadding="0" cellspacing="0">
						<tr>
							<td>
								<input name="maxconn" type="text" class="formfld" id="maxconn" size="5" <?if ($pconfig['enable']!='yes') echo "enabled=\"false\"";?>  value="<?=htmlspecialchars($pconfig['maxconn']);?>" /> per process.
							</td>
						</tr>
					</table>
					Sets the maximum per-process number of concurrent connections to X.<br/>
					<strong>NOTE:</strong> setting this value too high will result in HAProxy not being able to allocate enough memory.<br/>
					<p>
				<?php
					$memusage = trim(`ps auxw | grep haproxy | grep -v grep | awk '{ print $5 }'`);
					if($memusage)
						echo "Current memory usage: <b>{$memusage} kB.</b><br/>";
				?>
					Current <a href='/system_advanced_sysctl.php'>'System Tunables'</a> settings.<br/>
					&nbsp;&nbsp;'kern.maxfiles': <b><?=`sysctl kern.maxfiles | awk '{ print $2 }'`?></b><br/> 
					&nbsp;&nbsp;'kern.maxfilesperproc': <b><?=`sysctl kern.maxfilesperproc | awk '{ print $2 }'`?></b><br/>
					</p>
					Full memory usage will only show after all connections have actually been used.
					</td><td>
					<table style="border: 1px solid #000;">
						<tr>
							<td><font size=-1>Connections</font></td>
							<td><font size=-1>Memory usage</font></td>
						</tr>
						<tr>
							<td colspan="2">
								<hr noshade style="border: 1px solid #000;"></hr>
							</td>
						</tr>
						<tr>
							<td align="right"><font size=-1>1</font></td>
							<td><font size=-1>50 kB</font></td>
						</tr>
						<tr>
							<td align="right"><font size=-1>1.000</font></td>
							<td><font size=-1>48 MB</font></td>
						</tr>
						<tr>
							<td align="right"><font size=-1>10.000</font></td>
							<td><font size=-1>488 MB</font></td>
						</tr>
						<tr>
							<td align="right"><font size=-1>100.000</font></td>
							<td><font size=-1>4,8 GB</font></td>
						</tr>
						<tr>
							<td colspan="2" style="white-space: nowrap"><font size=-2>Calculated for plain HTTP connections,<br/>using ssl offloading will increase this.</font></td>
						</tr>
					</table>
					</td></tr></table>
					When setting a high amount of allowed simultaneous connections you will need to add and or increase the following two <b><a href='/system_advanced_sysctl.php'>'System Tunables'</a></b> kern.maxfiles and kern.maxfilesperproc.
					For HAProxy alone set these to at least the number of allowed connections * 2 + 31. So for 100.000 connections these need to be 200.031 or more to avoid trouble, take into account that handles are also used by other processes when setting kern.maxfiles.
					<br/>
				</td>
			</tr>
			<tr>
				<td valign="top" class="vncell">
					Number of processes to start
				</td>
				<td class="vtable">
					<input name="nbproc" type="text" class="formfld" id="nbproc" size="18" value="<?=htmlspecialchars($pconfig['nbproc']);?>" />
					<br/>
					Defaults to 1 if left blank (<?php echo trim(`/sbin/sysctl kern.smp.cpus | cut -d" " -f2`); ?> CPU core(s) detected).<br/>
					Note : Consider leaving this value empty or 1  because in multi-process mode (nbproc > 1) memory is not shared between the processes, which could result in random behaviours for several options like ACL's, sticky connections, stats pages, admin maintenance options and some others.<br/>
					For more information about the <b>"nbproc"</b> option please see <b><a href='http://cbonte.github.io/haproxy-dconv/configuration-1.5.html#nbproc' target='_blank'>HAProxy Documentation</a> </b>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell">Reload behaviour</td>
				<td width="78%" class="vtable">
				<input name="terminate_on_reload" type="checkbox" value="yes" <?php if ($pconfig['terminate_on_reload']) echo "checked"; ?> />
				Force immediate stop of old process on reload. (closes existing connections)<br/><br/>Note: when this option is selected connections will be closed when haproxy is restarted.
				Otherwise the existing connections will be served by the old haproxy process untill they are closed.
				Checking this option will interupt existing connections on a restart. (which happens when the configuration is applied,
				but possibly also when pfSense detects an interface comming up or changing its ip-address)</td>
			</tr>
			<tr>
				<td valign="top" class="vncell">
					Carp monitor
				</td>
				<td class="vtable">
					<?php
					$vipinterfaces = array();
					$vipinterfaces[] = array('ip' => '', 'name' => 'Disabled');
					$vipinterfaces += haproxy_get_bindable_interfaces($ipv="ipv4,ipv6", $interfacetype="carp");
					echo_html_select('carpdev',$vipinterfaces, $pconfig['carpdev'],"No carp interfaces pressent");
					?>				
					<br/>
					Monitor carp interface and only run haproxy on the firewall which is MASTER.
				</td>
			</tr>
			<tr>
				<td>
					&nbsp;
				</td>
			</tr>
			<tr>
				<td colspan="2" valign="top" class="listtopic">Stats tab, 'internal' stats port</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell">Internal stats port</td>
				<td class="vtable">
					<input name="localstatsport" type="text" <?if(isset($pconfig['localstatsport'])) echo "value=\"{$pconfig['localstatsport']}\"";?> size="10" maxlength="5" /> EXAMPLE: 2200<br/>
					Sets the internal port to be used for the stats tab.
					This is bound to 127.0.0.1 so will not be directly exposed on any LAN/WAN/other interface. It is used to internally pass through the stats page.
					Leave this setting empty to remove the "HAProxyLocalStats" item from the stats page and save a little on recources.
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell">Internal stats refresh rate</td>
				<td class="vtable">
					<input name="localstats_refreshtime" type="text" <?if(isset($pconfig['localstats_refreshtime'])) echo "value=\"{$pconfig['localstats_refreshtime']}\"";?> size="10" maxlength="5" /> Seconds, Leave this setting empty to not refresh the page automatically. EXAMPLE: 10
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell">Sticktable page refresh rate</td>
				<td class="vtable">
					<input name="localstats_sticktable_refreshtime" type="text" <?if(isset($pconfig['localstats_sticktable_refreshtime'])) echo "value=\"{$pconfig['localstats_sticktable_refreshtime']}\"";?> size="10" maxlength="5" /> Seconds, Leave this setting empty to not refresh the page automatically. EXAMPLE: 10
				</td>
			</tr>
			<tr>
				<td colspan="2" valign="top" class="listtopic">Logging</td>
			</tr>
			<tr>
				<td valign="top" class="vncell">
					Remote syslog host
				</td>
				<td class="vtable">
					<input name="remotesyslog" type="text" class="formfld" id="remotesyslog" size="18" value="<?=htmlspecialchars($pconfig['remotesyslog']);?>" /><br/>
					To log to the local pfSense systemlog fill the host with the value <b>/var/run/log</b>, however if a lot of messages are generated logging is likely to be incomplete. (Also currently no informational logging gets shown in the systemlog.)
				</td>
			</tr>
			<tr>
				<td valign="top" class="vncell">
					Syslog facility
				</td>
				<td class="vtable">
					<select name="logfacility" class="formfld">
				<?php
					$facilities = array("kern", "user", "mail", "daemon", "auth", "syslog", "lpr",
						"news", "uucp", "cron", "auth2", "ftp", "ntp", "audit", "alert", "cron2",
					       	"local0", "local1", "local2", "local3", "local4", "local5", "local6", "local7");
					foreach ($facilities as $f): 
				?>
					<option value="<?=$f;?>" <?php if ($f == $pconfig['logfacility']) echo "selected"; ?>>
						<?=$f;?>
					</option>
				<?php
					endforeach;
				?>
					</select>
				</td>
			</tr>
			<tr>
				<td valign="top" class="vncell">
					Syslog level
				</td>
				<td class="vtable">
					<select name="loglevel" class="formfld">
				<?php
					$levels = array("emerg", "alert", "crit", "err", "warning", "notice", "info", "debug");
					foreach ($levels as $l): 
				?>
					<option value="<?=$l;?>" <?php if ($l == $pconfig['loglevel']) echo "selected"; ?>>
						<?=$l;?>
					</option>
				<?php
					endforeach;
				?>
					</select>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell">Log hostname</td>
				<td width="78%" class="vtable">
					<input name="log-send-hostname" type="text" <?if(isset($pconfig['log-send-hostname'])) echo "value=\"{$pconfig['log-send-hostname']}\"";?> size="18" maxlength="50" /> EXAMPLE: HaproxyMasterNode<br/>Sets the hostname field in the syslog header. If empty defaults to the system hostname.
				</td>
			</tr>
			<tr>
				<td colspan="2" valign="top" class="listtopic">Tuning</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell">Max SSL Diffie-Hellman size</td>
				<td width="78%" class="vtable">
					<input name="ssldefaultdhparam" type="text" <?if(isset($pconfig['ssldefaultdhparam'])) echo "value=\"{$pconfig['ssldefaultdhparam']}\"";?> size="10" maxlength="5" /> EXAMPLE: 2048<br/>Sets the maximum size of the Diffie-Hellman parameters used for generating
the ephemeral/temporary Diffie-Hellman key in case of DHE key exchange.
Minimum and default value is: 1024, bigger values might increase CPU usage.<br/>
					For more information about the <b>"tune.ssl.default-dh-param"</b> option please see <b><a href='http://cbonte.github.io/haproxy-dconv/configuration-1.5.html#3.2-tune.ssl.default-dh-param' target='_blank'>HAProxy Documentation</a></b><br/>
					NOTE: HAProxy will emit a warning when starting when this setting is used but not configured.
				</td>
			</tr>
			<tr>
				<td colspan="2" valign="top" class="listtopic">Global Advanced pass thru</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell">&nbsp;</td>
				<td width="78%" class="vtable">
					<? $textrowcount = max(substr_count($pconfig['advanced'],"\n"), 2) + 2; ?>
					<textarea name='advanced' rows="<?=$textrowcount;?>" cols="70" id='advanced'><?php echo $pconfig['advanced']; ?></textarea>
					<br/>
					NOTE: paste text into this box that you would like to pass thru in the global settings area.
				</td>
			</tr>
			<tr>
				<td>
					&nbsp;
				</td>
			</tr>
			<tr>
				<td colspan="2" valign="top" class="listtopic">Recalculate certificate chain.</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell">&nbsp;</td>
				<td width="78%" class="vtable">
					<input type="hidden" name="calculate_certificate_chain" id="calculate_certificate_chain" />
					<input type="button" class="formbtn" value="Recalculate certificate chains" onclick="$('calculate_certificate_chain').value='true';document.iform.submit();" />(Other changes on this page will be lost)
					<br/>
					This can be required after certificates have been created or imported. As pfSense 2.1.0 currently does not
					always keep track of these dependencies which might be required to create a proper certificate chain when using SSLoffloading.
				</td>
			</tr>
			<tr>
				<td colspan="2" valign="top" class="listtopic">Configuration synchronization</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell">HAProxy Sync</td>
				<td width="78%" class="vtable">
					<input name="enablesync" type="checkbox" value="yes" <?php if ($pconfig['enablesync']) echo "checked"; ?> />
					<strong>Sync HAProxy configuration to backup CARP members via XMLRPC.</strong><br/>
					Note: remember to also turn on HAProxy Sync on the backup nodes.<br/>
					The synchronisation host and password are those configured in pfSense main <a href="/system_hasync.php">"System: High Availability Sync"</a> settings.
				</td>
			</tr>
<!--
			<tr>
				<td width="22%" valign="top" class="vncell">Synchronization password</td>
				<td width="78%" class="vtable">
					<input name="syncpassword" type="password" autocomplete="off" value="<?=$pconfig['syncpassword'];?>">
					<br/>
					<strong>Enter the password that will be used during configuration synchronization.  This is generally the remote webConfigurator password.</strong>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell">Sync host #1</td>
				<td width="78%" class="vtable">
					<input name="synchost1" value="<?=$pconfig['synchost1'];?>">
					<br/>
					<strong>Synchronize settings to this hosts IP address.</strong>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell">Sync host #2</td>
				<td width="78%" class="vtable">
					<input name="synchost2" value="<?=$pconfig['synchost2'];?>">
					<br/>
					<strong>Synchronize settings to this hosts IP address.</strong>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell">Sync host #3</td>
				<td width="78%" class="vtable">
					<input name="synchost3" value="<?=$pconfig['synchost3'];?>">
					<br/>
					<strong>Synchronize settings to this hosts IP address.</strong>
				</td>
			</tr>
-->
			<tr>
				<td>
					&nbsp;
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top">&nbsp;</td>
				<td width="78%">
					<input name="Submit" type="submit" class="formbtn" value="Save" onClick="enable_change(true)" />
				</td>
			</tr>
		</table>
	</div>
</table>

<?php if(file_exists("/var/etc/haproxy/haproxy.cfg")): ?>
	<div id="configuration" style="display:none; border-style:dashed; padding: 8px;">
		<b><i>/var/etc/haproxy.cfg file contents:</i></b>
		<?php
			if(file_exists("/var/etc/haproxy/haproxy.cfg")) {
				echo "<pre>" . trim(file_get_contents("/var/etc/haproxy/haproxy.cfg")) . "</pre>";
			}
		?>
	</div>
	<div id="showconfiguration">
		<a onClick="new Effect.Fade('showconfiguration'); new Effect.Appear('configuration');  setTimeout('scroll_after_fade();', 250); return false;" href="#">Show</a> automatically generated configuration.
	</div>
<?php endif; ?>

</form>
<script type="text/javascript">
	function scroll_after_fade() {
		scrollTo(0,99999999999);
	}
<!--
enable_change(false);
//-->
</script>
<?php include("fend.inc"); ?>
</body>
</html>
