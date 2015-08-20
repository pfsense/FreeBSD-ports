<?php
/*
 * suricata_os_policy_engine.php
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
	This file contains code for adding/editing an existing Host OS Policy Engine.
	It is included and injected inline as needed into the suricata_stream_flow.php
	page to provide the edit functionality for Host OS Policy Engines.

	The following variables are assumed to exist and must be initialized
	as necessary in order to utilize this page.

	$g --> system global variables array
	$config --> global variable pointing to configuration information
	$pengcfg --> array containing current Host OS Policy engine configuration

	Information is returned from this page via the following form fields:

	policy_name --> Unique Name for the Host OS Policy Engine
	policy_bind_to --> Alias name representing "bind_to" IP address for engine
	policy --> Operating system chosen for engine policy
	select_alias --> Submit button for select alias operation
	save_os_policy --> Submit button for save operation and exit
	cancel_os_policy --> Submit button to cancel operation and exit
 **************************************************************************************/
?>

<table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
	<tbody>
	<tr>
		<td colspan="2" align="center" class="listtopic"><?php echo gettext("Suricata Target-Based Host OS Policy Engine Configuration"); ?></td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("Policy Name"); ?></td>
		<td class="vtable">
			<input name="policy_name" type="text" class="formfld unknown" id="policy_name" size="25" maxlength="25" 
			value="<?=htmlspecialchars($pengcfg['name']);?>"<?php if (htmlspecialchars($pengcfg['name']) == " default") echo " readonly";?>/>&nbsp;
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
					value="<?=htmlspecialchars($pengcfg['bind_to']);?>" title="<?=trim(filter_expand_alias($pengcfg['bind_to']));?>" autocomplete="off"/>&nbsp;
					<?php echo gettext("IP List to bind this engine to. (Cannot be blank)"); ?></td>
					<td class="vexpl" align="right"><input type="submit" class="formbtns" name="select_alias" value="Aliases" 
					title="<?php echo gettext("Select an existing IP alias");?>"/></td>
				</tr>
				<tr>
					<td class="vexpl" colspan="2"><?php echo gettext("This policy will apply for packets with destination addresses contained within this IP List.");?></td>
				</tr>
				</tbody>
			</table>
			<span class="red"><strong><?php echo gettext("Note: ") . "</strong></span>" . gettext("Supplied value must be a pre-configured Alias or the keyword 'all'.");?>
			&nbsp;&nbsp;&nbsp;&nbsp;
		<?php else : ?>
			<input name="policy_bind_to" type="text" class="formfldalias" id="policy_bind_to" size="32" 
			value="<?=htmlspecialchars($pengcfg['bind_to']);?>" autocomplete="off" readonly>&nbsp;
			<?php echo "<span class=\"red\">" . gettext("IP List for the default engine is read-only and must be 'all'.") . "</span>";?><br/>
			<?php echo gettext("The default engine is required and will apply for packets with destination addresses not matching other engine IP Lists.");?><br/>
		<?php endif ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Target Policy"); ?> </td>
		<td width="78%" class="vtable">
			<select name="policy" class="formselect" id="policy">
			<?php
			$profile = array( 'BSD', 'BSD-Right', 'HPUX10', 'HPUX11', 'Irix', 'Linux', 'Mac-OS', 'Old-Linux', 'Old-Solaris', 'Solaris', 'Vista', 'Windows', 'Windows2k3' );
			foreach ($profile as $val): ?>
			<option value="<?=strtolower($val);?>" 
			<?php if (strtolower($val) == $pengcfg['policy']) echo "selected"; ?>>
				<?=gettext($val);?></option>
				<?php endforeach; ?>
			</select>&nbsp;&nbsp;<?php echo gettext("Choose the OS target policy appropriate for the protected hosts.  The default is ") . 
			"<strong>" . gettext("BSD") . "</strong>"; ?>.<br/><br/>
			<?php echo gettext("Available OS targets are BSD, BSD-Right, HPUX10, HPUX11, Irix, Linux, Mac-OS, Old-Linux, Old-Solaris, Solaris, Vista, 'Windows' and Windows2k3."); ?><br/>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="bottom">&nbsp;</td>
		<td width="78%" valign="bottom">
			<input name="save_os_policy" id="save_os_policy" type="submit" class="formbtn" value=" Save " title="<?php echo 
			gettext("Save OS policy engine settings and return to Flow/Stream tab"); ?>">
			&nbsp;&nbsp;&nbsp;&nbsp;
			<input name="cancel_os_policy" id="cancel_os_policy" type="submit" class="formbtn" value="Cancel" title="<?php echo 
			gettext("Cancel changes and return to Flow/Stream tab"); ?>"></td>
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
//]]>

</script>

