<?php
/*
 * suricata_libhtp_policy_engine.php
 *
 * Portions of this code are based on original work done for the
 * Snort package for pfSense from the following contributors:
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

/**************************************************************************************
	This file contains code for adding/editing an existing Libhtp Policy Engine.
	It is included and injected inline as needed into the suricata_app_parsers.php
	page to provide the edit functionality for Host OS Policy Engines.

	The following variables are assumed to exist and must be initialized
	as necessary in order to utilize this page.

	$g --> system global variables array
	$config --> global variable pointing to configuration information
	$pengcfg --> array containing current Libhtp Policy engine configuration

	Information is returned from this page via the following form fields:

	policy_name --> Unique Name for the Libhtp Policy Engine
	policy_bind_to --> Alias name representing "bind_to" IP address for engine
	personality --> Operating system chosen for engine policy
	select_alias --> Submit button for select alias operation
	req_body_limit --> Request Body Limit size
	resp_body_limit --> Response Body Limit size
	enable_double_decode_path --> double-decode path part of URI
	enable_double_decode_query --> double-decode query string part of URI
	enable_uri_include_all --> inspect all of URI
	save_libhtp_policy --> Submit button for save operation and exit
	cancel_libhtp_policy --> Submit button to cancel operation and exit
 **************************************************************************************/
?>

<table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
	<tbody>
	<tr>
		<td colspan="2" valign="middle" class="listtopic"><?php echo gettext("Suricata Target-Based HTTP Server Policy Configuration"); ?></td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("Engine Name"); ?></td>
		<td class="vtable">
			<input name="policy_name" type="text" class="formfld unknown" id="policy_name" size="25" maxlength="25" 
			value="<?=htmlspecialchars($pengcfg['name']);?>"<?php if (htmlspecialchars($pengcfg['name']) == " default") echo " readonly";?>>&nbsp;
			<?php if (htmlspecialchars($pengcfg['name']) <> "default") 
					echo gettext("Name or description for this engine.  (Max 25 characters)");
				else
					echo "<span class=\"red\">" . gettext("The name for the 'default' engine is read-only.") . "</span>";?><br/>
			<?php echo gettext("Unique name or description for this engine configuration.  Default value is ") . 
			"<strong>" . gettext("default") . "</strong>"; ?>.<br/>
		</td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("Bind-To IP Address Alias"); ?></td>
		<td class="vtable">
		<?php if ($pengcfg['name'] <> "default") : ?>
			<table width="95%" border="0" cellpadding="2" cellspacing="0">
				<tbody>
				<tr>
					<td class="vexpl"><input name="policy_bind_to" type="text" class="formfldalias" id="policy_bind_to" size="32" 
					value="<?=htmlspecialchars($pengcfg['bind_to']);?>" title="<?=trim(filter_expand_alias($pengcfg['bind_to']));?>" autocomplete="off">&nbsp;
					<?php echo gettext("IP List to bind this engine to. (Cannot be blank)"); ?></td>
					<td class="vexpl" align="right"><input type="submit" class="formbtns" name="select_alias" value="Aliases" 
					title="<?php echo gettext("Select an existing IP alias");?>"/></td>
				</tr>
				<tr>
					<td class="vexpl" colspan="2"><?php echo gettext("This policy will apply for packets with destination addresses contained within this IP List.");?></td>
				</tr>
				</tbody>
			</table>
			<br/><span class="red"><strong><?php echo gettext("Note: ") . "</strong></span>" . gettext("Supplied value must be a pre-configured Alias or the keyword 'all'.");?>
		<?php else : ?>
			<input name="policy_bind_to" type="text" class="formfldalias" id="policy_bind_to" size="32" 
			value="<?=htmlspecialchars($pengcfg['bind_to']);?>" autocomplete="off" readonly>&nbsp;
			<?php echo "<span class=\"red\">" . gettext("IP List for the default engine is read-only and must be 'all'.") . "</span>";?><br/>
			<?php echo gettext("The default engine is required and will apply for packets with destination addresses not matching other engine IP Lists.");?><br/>
		<?php endif ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Target Web Server Personality"); ?> </td>
		<td width="78%" class="vtable">
			<select name="personality" class="formselect" id="personality">
			<?php
			$profile = array( 'Apache_2', 'Generic', 'IDS', 'IIS_4_0', 'IIS_5_0', 'IIS_5_1', 'IIS_6_0', 'IIS_7_0', 'IIS_7_5', 'Minimal' );
			foreach ($profile as $val): ?>
			<option value="<?=$val;?>" 
			<?php if ($val == $pengcfg['personality']) echo "selected"; ?>>
				<?=gettext($val);?></option>
				<?php endforeach; ?>
			</select>&nbsp;&nbsp;<?php echo gettext("Choose the web server personality appropriate for the protected hosts.  The default is ") . 
			"<strong>" . gettext("IDS") . "</strong>"; ?>.<br/><br/>
			<?php echo gettext("Available web server personality targets are:  Apache 2, Generic, IDS (default), IIS_4_0, IIS_5_0, IIS_5_1, IIS_6_0, IIS_7_0, IIS_7_5 and Minimal."); ?><br/>
		</td>
	</tr>
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Inspection Limits"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Request Body Limit"); ?></td>
		<td width="78%" class="vtable">
			<input name="req_body_limit" type="text" class="formfld unknown" id="req_body_limit" size="9"
			value="<?=htmlspecialchars($pengcfg['request-body-limit']);?>">&nbsp;
			<?php echo gettext("Maximum number of HTTP request body bytes to inspect.  Default is ") . 
			"<strong>" . gettext("4,096") . "</strong>" . gettext(" bytes."); ?><br/><br/>
			<?php echo gettext("HTTP request bodies are often big, so they take a lot of time to process which has a significant impact ") . 
			gettext("on performance. This sets the limit (in bytes) of the client-body that will be inspected.") . "<br/><br/><span class=\"red\"><strong>" . 
			gettext("Note: ") . "</strong></span>" . gettext("Setting this parameter to 0 will inspect all of the client-body."); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Response Body Limit"); ?></td>
		<td width="78%" class="vtable">
			<input name="resp_body_limit" type="text" class="formfld unknown" id="resp_body_limit" size="9"
			value="<?=htmlspecialchars($pengcfg['response-body-limit']);?>">&nbsp;
			<?php echo gettext("Maximum number of HTTP response body bytes to inspect.  Default is ") . 
			"<strong>" . gettext("4,096") . "</strong>" . gettext(" bytes."); ?><br/><br/>
			<?php echo gettext("HTTP response bodies are often big, so they take a lot of time to process which has a significant impact ") . 
			gettext("on performance. This sets the limit (in bytes) of the server-body that will be inspected.") . "<br/><br/><span class=\"red\"><strong>" . 
			gettext("Note: ") . "</strong></span>" . gettext("Setting this parameter to 0 will inspect all of the server-body."); ?>
		</td>
	</tr>
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Decode Settings"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Double-Decode Path"); ?></td>
		<td width="78%" class="vtable"><input name="enable_double_decode_path" type="checkbox" value="yes" <?php if ($pengcfg['double-decode-path'] == "yes") echo " checked"; ?>>
			<?php echo gettext("Suricata will double-decode path section of the URI.  Default is ") . "<strong>" . gettext("Not Checked") . "</strong>."; ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Double-Decode Query"); ?></td>
		<td width="78%" class="vtable"><input name="enable_double_decode_query" type="checkbox" value="yes" <?php if ($pengcfg['double-decode-query'] == "yes") echo " checked"; ?>>
			<?php echo gettext("Suricata will double-decode query string section of the URI.  Default is ") . "<strong>" . gettext("Not Checked") . "</strong>."; ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("URI Include-All"); ?></td>
		<td width="78%" class="vtable"><input name="enable_uri_include_all" type="checkbox" value="yes" <?php if ($pengcfg['uri-include-all'] == "yes") echo " checked"; ?>>
			<?php echo gettext("Include all parts of the URI.  Default is ") . "<strong>" . gettext("Not Checked") . "</strong>."; ?><br/><br/>
			<?php echo gettext("By default the 'scheme', username/password, hostname and port are excluded from inspection.  Enabling this option " . 
			"adds all of them to the normalized uri.  This was the default in Suricata versions prior to 2.0."); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="bottom">&nbsp;</td>
		<td width="78%" valign="bottom">
			<input name="save_libhtp_policy" id="save_libhtp_policy" type="submit" class="formbtn" value=" Save " title="<?php echo 
			gettext("Save web server policy engine settings and return to App Parsers tab"); ?>">
			&nbsp;&nbsp;&nbsp;&nbsp;
			<input name="cancel_libhtp_policy" id="cancel_libhtp_policy" type="submit" class="formbtn" value="Cancel" title="<?php echo 
			gettext("Cancel changes and return to App Parsers tab"); ?>"></td>
	</tr>
	</tbody>
</table>

<script type="text/javascript" src="/javascript/autosuggest.js">
</script>
<script type="text/javascript" src="/javascript/suggestions.js">
</script>
<script type="text/javascript">
//<![CDATA[
var addressarray = <?= json_encode(get_alias_list(array("host", "network"))) ?>;

function createAutoSuggest() {
<?php
	echo "\tvar objAlias = new AutoSuggestControl(document.getElementById('policy_bind_to'), new StateSuggestions(addressarray));\n";
?>
}

setTimeout("createAutoSuggest();", 500);

</script>

