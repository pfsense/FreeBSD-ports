<?php
/*
 * suricata_define_vars.php
 * part of pfSense
 *
 * Significant portions of this code are based on original work done
 * for the Snort package for pfSense from the following contributors:
 * 
 * Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (C) 2006 Scott Ullrich
 * Copyright (C) 2009 Robert Zelaya Sr. Developer
 * Copyright (C) 2012 Ermal Luci
 * All rights reserved.
 *
 * Adapted for Suricata by:
 * Copyright (C) 2014 Bill Meeks
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:

 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

//require_once("globals.inc");
require_once("guiconfig.inc");
require_once("/usr/local/pkg/suricata/suricata.inc");

global $g, $rebuild_rules;

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);
if (is_null($id)) {
        header("Location: /suricata/suricata_interfaces.php");
        exit;
}

if (!is_array($config['installedpackages']['suricata']['rule'])) {
	$config['installedpackages']['suricata']['rule'] = array();
}
$a_nat = &$config['installedpackages']['suricata']['rule'];

/* define servers and ports */
$suricata_servers = array (
	"dns_servers" => "\$HOME_NET", "smtp_servers" => "\$HOME_NET", "http_servers" => "\$HOME_NET",
	"sql_servers" => "\$HOME_NET", "telnet_servers" => "\$HOME_NET", "dnp3_server" => "\$HOME_NET",
	"dnp3_client" => "\$HOME_NET", "modbus_server" => "\$HOME_NET", "modbus_client" => "\$HOME_NET",
	"enip_server" => "\$HOME_NET", "enip_client" => "\$HOME_NET", "ftp_servers" => "\$HOME_NET", "ssh_servers" => "\$HOME_NET", 
	"aim_servers" => "64.12.24.0/23,64.12.28.0/23,64.12.161.0/24,64.12.163.0/24,64.12.200.0/24,205.188.3.0/24,205.188.5.0/24,205.188.7.0/24,205.188.9.0/24,205.188.153.0/24,205.188.179.0/24,205.188.248.0/24"
);

/* if user has defined a custom ssh port, use it */
if(is_array($config['system']['ssh']) && isset($config['system']['ssh']['port']))
        $ssh_port = $config['system']['ssh']['port'];
else
        $ssh_port = "22";
$suricata_ports = array(
	"ftp_ports" => "21", 
	"http_ports" => "80", 
	"oracle_ports" => "1521", 
	"ssh_ports" => $ssh_port, 
	"shellcode_ports" => "!80", 
	"DNP3_PORTS" => "20000", "file_data_ports" => "\$HTTP_PORTS,110,143"
);

// Sort our SERVERS and PORTS arrays to make values
// easier to locate by the the user.
ksort($suricata_servers);
ksort($suricata_ports);

$pconfig = $a_nat[$id];

/* convert fake interfaces to real */
$if_real = get_real_interface($pconfig['interface']);
$suricata_uuid = $config['installedpackages']['suricata']['rule'][$id]['uuid'];

if ($_POST) {

	$natent = array();
	$natent = $pconfig;

	foreach ($suricata_servers as $key => $server) {
		if ($_POST["def_{$key}"] && !is_alias($_POST["def_{$key}"]))
			$input_errors[] = "Only aliases are allowed";
		if ($_POST["def_{$key}"] && is_alias($_POST["def_{$key}"]) && trim(filter_expand_alias($_POST["def_{$key}"])) == "")
			$input_errors[] = "FQDN aliases are not allowed for IP variables in Suricata.";
	}
	foreach ($suricata_ports as $key => $server) {
		if ($_POST["def_{$key}"] && !is_alias($_POST["def_{$key}"]))
			$input_errors[] = "Only aliases are allowed";
		if ($_POST["def_{$key}"] && is_alias($_POST["def_{$key}"]) && trim(filter_expand_alias($_POST["def_{$key}"])) == "")
			$input_errors[] = "FQDN aliases are not allowed for port variables in Suricata.";
	}
	/* if no errors write to suricata.yaml */
	if (!$input_errors) {
		/* post new options */
		foreach ($suricata_servers as $key => $server) {
			if ($_POST["def_{$key}"])
				$natent["def_{$key}"] = $_POST["def_{$key}"];
			else
				unset($natent["def_{$key}"]);
		}
		foreach ($suricata_ports as $key => $server) {
			if ($_POST["def_{$key}"])
				$natent["def_{$key}"] = $_POST["def_{$key}"];
			else
				unset($natent["def_{$key}"]);
		}

		$a_nat[$id] = $natent;

		write_config();

		/* Update the suricata.yaml file for this interface. */
		$rebuild_rules = false;
		conf_mount_rw();
		suricata_generate_yaml($a_nat[$id]);
		conf_mount_ro();

		/* Soft-restart Suricaa to live-load new variables. */
		suricata_reload_config($a_nat[$id]);

		/* Sync to configured CARP slaves if any are enabled */
		suricata_sync_on_changes();

		/* after click go to this page */
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: suricata_define_vars.php?id=$id");
		exit;
	}
}

$if_friendly = convert_friendly_interface_to_friendly_descr($pconfig['interface']);
$pgtitle = gettext("Suricata: Interface {$if_friendly} Variables - Servers and Ports");
include_once("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php 
include("fbegin.inc");
?>

<script type="text/javascript" src="/javascript/autosuggest.js">
</script>
<script type="text/javascript" src="/javascript/suggestions.js">
</script>
<form action="suricata_define_vars.php" method="post" name="iform" id="iform">

<?php
/* Display Alert message */
if ($input_errors)
	print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);
?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tbody>
<tr><td>
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("Interfaces"), true, "/suricata/suricata_interfaces.php");
	$tab_array[] = array(gettext("Global Settings"), false, "/suricata/suricata_global.php");
	$tab_array[] = array(gettext("Updates"), false, "/suricata/suricata_download_updates.php");
	$tab_array[] = array(gettext("Alerts"), false, "/suricata/suricata_alerts.php?instance={$id}");
	$tab_array[] = array(gettext("Blocks"), false, "/suricata/suricata_blocked.php");
	$tab_array[] = array(gettext("Pass Lists"), false, "/suricata/suricata_passlist.php");
	$tab_array[] = array(gettext("Suppress"), false, "/suricata/suricata_suppress.php");
	$tab_array[] = array(gettext("Logs View"), false, "/suricata/suricata_logs_browser.php?instance={$id}");
	$tab_array[] = array(gettext("Logs Mgmt"), false, "/suricata/suricata_logs_mgmt.php");
	$tab_array[] = array(gettext("SID Mgmt"), false, "/suricata/suricata_sid_mgmt.php");
	$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
	$tab_array[] = array(gettext("IP Lists"), false, "/suricata/suricata_ip_list_mgmt.php");
	display_top_tabs($tab_array, true);
	echo '</td></tr>';
	echo '<tr><td class="tabnavtbl">';
	$tab_array = array();
	$menu_iface=($if_friendly?substr($if_friendly,0,5)." ":"Iface ");
	$tab_array[] = array($menu_iface . gettext("Settings"), false, "/suricata/suricata_interfaces_edit.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Categories"), false, "/suricata/suricata_rulesets.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Rules"), false, "/suricata/suricata_rules.php?id={$id}");
        $tab_array[] = array($menu_iface . gettext("Flow/Stream"), false, "/suricata/suricata_flow_stream.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("App Parsers"), false, "/suricata/suricata_app_parsers.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Variables"), true, "/suricata/suricata_define_vars.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("Barnyard2"), false, "/suricata/suricata_barnyard.php?id={$id}");
	$tab_array[] = array($menu_iface . gettext("IP Rep"), false, "/suricata/suricata_ip_reputation.php?id={$id}");
        display_top_tabs($tab_array, true);
?>
</td></tr>
<tr>
		<td><div id="mainarea">
		<table id="maintable" class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
		<tbody>
		<tr>
			<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Define Servers (IP variables)"); ?></td>
		</tr>
<?php
		foreach ($suricata_servers as $key => $server):
			if (strlen($server) > 40)
				$server = substr($server, 0, 40) . "...";
			$label = strtoupper($key);
			$value = "";
			$title = "";
			if (!empty($pconfig["def_{$key}"])) {
				$value = htmlspecialchars($pconfig["def_{$key}"]);
				$title = trim(filter_expand_alias($pconfig["def_{$key}"]));
			}
?>
			<tr>
				<td width='30%' valign='top' class='vncell'><?php echo gettext("Define"); ?> <?=$label;?></td>
				<td width="70%" class="vtable">
					<input name="def_<?=$key;?>" size="40"
					type="text" autocomplete="off" class="formfldalias" id="def_<?=$key;?>"
					value="<?=$value;?>" title="<?=$title;?>"> <br/>
				<span class="vexpl"><?php echo gettext("Default value:"); ?> "<?=$server;?>" <br/><?php echo gettext("Leave " .
				"blank for default value."); ?></span>
				</td>
			</tr>
<?php		endforeach; ?>
		<tr>
			<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Define Ports (port variables)"); ?></td>
		</tr>
<?php
		foreach ($suricata_ports as $key => $server):
			if (strlen($server) > 40)
				$server = substr($server, 0, 40) . "...";
			$label = strtoupper($key);
			$value = "";
			$title = "";
			if (!empty($pconfig["def_{$key}"])) {
				$value = htmlspecialchars($pconfig["def_{$key}"]);
				$title = trim(filter_expand_alias($pconfig["def_{$key}"]));
			}
?>
			<tr>
				<td width='30%' valign='top' class='vncell'><?php echo gettext("Define"); ?> <?=$label;?></td>
				<td width="70%" class="vtable">
					<input name="def_<?=$key;?>" type="text" size="40" autocomplete="off" class="formfldalias" id="def_<?=$key;?>"
					value="<?=$value;?>" title="<?=$title;?>"> <br/>
				<span class="vexpl"><?php echo gettext("Default value:"); ?> "<?=$server;?>" <br/> <?php echo gettext("Leave " .
				"blank for default value."); ?></span>
				</td>
			</tr>
<?php		endforeach; ?>
		<tr>
			<td width="30%" valign="top">&nbsp;</td>
			<td width="70%">
				<input name="Submit" type="submit" class="formbtn" value="Save">
				<input name="id" type="hidden" value="<?=$id;?>">
			</td>
		</tr>
		</tbody>
	</table>
</div>
</td></tr></tbody>
</table>
</form>
<script type="text/javascript">
//<![CDATA[
	var addressarray = <?= json_encode(get_alias_list(array("host", "network"))) ?>;
	var portsarray  = <?= json_encode(get_alias_list("port")) ?>;

	function createAutoSuggest() {
	<?php
		foreach ($suricata_servers as $key => $server)
			echo " var objAlias{$key} = new AutoSuggestControl(document.getElementById('def_{$key}'), new StateSuggestions(addressarray));\n";
		foreach ($suricata_ports as $key => $server)
			echo "var pobjAlias{$key} = new AutoSuggestControl(document.getElementById('def_{$key}'), new StateSuggestions(portsarray));\n";
	?>
	}

setTimeout("createAutoSuggest();", 500);

//]]>
</script>

<?php include("fend.inc"); ?>
</body>
</html>
