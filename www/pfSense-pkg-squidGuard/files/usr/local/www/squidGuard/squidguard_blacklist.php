<?php
/*
 * squidguard_blacklist.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2006-2011 Serg Dvoriancev
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

require_once("guiconfig.inc");
require_once("notices.inc");
if (file_exists("/usr/local/pkg/squidguard.inc")) {
	require_once("/usr/local/pkg/squidguard.inc");
}

# ------------------------------------------------------------------------------
# defines
# ------------------------------------------------------------------------------
define("SGCURL_STATUS",  "/tmp/squidguard_download.log");
define("SGUPD_STATFILE", "/tmp/squidguard_download.stat");
define("SGBAR_SIZE",       "450");
define("DEBUG_AJAX",       "false");
# ------------------------------------------------------------------------------
# Requests
# ------------------------------------------------------------------------------
if ($_REQUEST['getactivity']) {
	header("Content-type: text/javascript");
	echo squidguard_blacklist_AJAX_response( $_REQUEST );
	exit;
}

# ------------------------------------------------------------------------------
# Functions
# ------------------------------------------------------------------------------

function squidguard_blacklist_AJAX_response( $request ) {
	$res = '';
	$sz  = 0;
	$pcaption = '&nbsp;';

	# Actions
	if ($request['blacklist_download_start']) {
		squidguard_blacklist_update_start( $request['blacklist_url'] ); # update start
	} elseif ($request['blacklist_download_cancel']) {
		squidguard_blacklist_update_cancel(); # update cancel
	} elseif ($request['blacklist_restore_default']) {
		squidguard_blacklist_restore_arcdb(); # restore default db
	} elseif ($request['blacklist_clear_log']) {
		squidguard_blacklist_update_clearlog(); # clear log
	}

	# Activity
	# Rebuild progress /check SG rebuild process/
	if (is_squidGuardProcess_rebuild_started()) {
		$pcaption = 'Blacklist DB rebuild progress';
		$sz = squidguar_blacklist_rebuild_progress();
	}
	elseif (squidguard_blacklist_update_IsStarted()) {
		$pcaption = 'Blacklist download progress';
		$sz = squidguard_blacklist_update_progress();
	}

	# progress status
	if ($sz < 0) {
		# nothing to show
		$sz = 0;
		$pcaption = '';
	}
	$res .= "\$('#progress_caption').html('{$pcaption}');\n";
	$res .= "\$('#progress_text').html('{$sz} %');\n";
	$res .= "setProgress('progressbar', ${sz}, true);\n";

	$status = '';
	if (file_exists(SGUPD_STATFILE)) {
		$status = file_get_contents(SGUPD_STATFILE);
		if ($sz && $sz != 100) {
			$status .= "Completed {$sz} %";
		}
	}
	if ($status) {
		$status = str_replace("\n", "\\r\\n", trim($status));
		$res .= "\$('#update_state').html('{$status}');\n";
		$res .= "\$('#update_state_cls').show();\n";
		$res .= "\$('#update_state_row').show();\n";
	} else {
		$res .= "\$('#update_state').html('');\n";
		$res .= "\$('#update_state_cls').hide();\n";
		$res .= "\$('#update_state_row').hide();\n";
	}

	return $res;
}

function squidguard_blacklist_update_progress() {
	$p = -1;

	if (file_exists(SGCURL_STATUS)) {
		$cn = file_get_contents(SGCURL_STATUS);
		if ($cn) {
			$cn = explode("\r", $cn);
			$cn = array_pop($cn);
			$cn = explode(" ", trim($cn));
			$p = intval( $cn[0] );
		}
	}

	return $p;
}

function squidguar_blacklist_rebuild_progress() {
	$arcdb   = "/tmp/squidGuard/arcdb";
	$blfiles = "{$arcdb}/blacklist.files";

	if (file_exists($arcdb) && file_exists($blfiles)) {
		$dirlist = explode("\n", file_get_contents($blfiles));
		for ($i = 0; $i < count($dirlist); $i++) {
			if (!file_exists("$arcdb/{$dirlist[$i]}/domains.db") &&
			    !file_exists("$arcdb/{$dirlist[$i]}/urls.db")) {
				return intval( $i * 100 / count($dirlist));
			}
		}
	}

	return 0;
}

function is_squidGuardProcess_rebuild_started() {
	# memo: 'ps -auxw' used 132 columns; 'ps -auxww' used 264 columns
	# if cmd more then 132 need use 'ww..' key
	return exec("ps -auxwwww | grep 'squidGuard -c .* -C all' | grep -v grep | awk '{print $2}' | wc -l | awk '{ print $1 }'");
}

# ------------------------------------------------------------------------------
# HTML Page
# ------------------------------------------------------------------------------

$selfpath	= "./squidguard_blacklist.php";
$blacklist_url	= '';

# get squidGuard config
if (function_exists('sg_init')) {
	sg_init(convert_pfxml_to_sgxml());
	$blacklist_url = $squidguard_config[F_BLACKLISTURL];
}

$pgtitle = array(gettext("Package"), gettext("SquidGuard"), gettext("Blacklists"));
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("General settings"), false, "/pkg_edit.php?xml=squidguard.xml&amp;id=0");
$tab_array[] = array(gettext("Common ACL"), false, "/pkg_edit.php?xml=squidguard_default.xml&amp;id=0");
$tab_array[] = array(gettext("Groups ACL"), false, "/pkg.php?xml=squidguard_acl.xml");
$tab_array[] = array(gettext("Target categories"), false, "/pkg.php?xml=squidguard_dest.xml");
$tab_array[] = array(gettext("Times"), false, "/pkg.php?xml=squidguard_time.xml");
$tab_array[] = array(gettext("Rewrites"), false, "/pkg.php?xml=squidguard_rewr.xml");
$tab_array[] = array(gettext("Blacklist"), true,  "/squidGuard/squidguard_blacklist.php");
$tab_array[] = array(gettext("Log"), false, "/squidGuard/squidguard_log.php");
$tab_array[] = array(gettext("XMLRPC Sync"), false, "/pkg_edit.php?xml=squidguard_sync.xml&amp;id=0");
display_top_tabs($tab_array);
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Blacklist Update"); ?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<form action="./squidguard_blacklist.php" method="post">
			<table class="table table-hover table-condensed">
				<tr>
					<td>
					<div class="progress" style="display: none;">
						<div id="progressbar" class="progress-bar progress-bar-striped" role="progressbar" aria-valuemin="0" aria-valuemax="100" style="width: 1%"></div>
					</div>
					<div id='progress_caption' name='progress_caption'>&nbsp;</div><br>
					<br><u id="progress_text" name="progress_text">0 %</u>
					<input class="formfld unknown" size="70" id="blacklist_url" name="blacklist_url" value= '<?="$blacklist_url"; ?>' > &nbsp
					<br><br>
					<button class="btn btn-success" id="blacklist_download_start"  name="blacklist_download_start"  onclick="getactivity('download');"><i class="fa fa-download icon-embed-btn"></i>Download</button>
					<button class="btn btn-warning" id="blacklist_download_cancel" name="blacklist_download_cancel" onclick="getactivity('cancel');"><i class="fa fa-times-circle icon-embed-btn"></i>Cancel</button>
					<button class="btn btn-info"    id="blacklist_restore_default" name="blacklist_restore_default" onclick="getactivity('restore_default');"><i class="fa fa-undo icon-embed-btn"></i>Restore Default</button>
					<br><br>
					Enter FTP or HTTP path to the blacklist archive here.
					<br><br>
					</td>
				</tr>
				<tr id='update_state_cls' name='update_state_cls' style='display:none;'>
					<td>
					<div class="panel panel-default">
						<div class="panel-heading">
							<h2 class="panel-title">
								<span  style="cursor: pointer;">
									<i class="fa fa-times-circle" onClick="getactivity('clear_log');" title='Clear Log and Close'></i>
								</span>
								Blacklist update Log
							</h2>
						</div>
					</div>
					</td>
				</tr>
				<tr id='update_state_row' name='update_state_row'>
					<td><textarea rows='15' cols='70' name='update_state' id='update_state' wrap='hard' readonly>&nbsp;</textarea></td>
				</tr>
<?php if (DEBUG_AJAX !== "false"): ?>
				<tr id='debug_row' name='debug_row'>
					<td>&nbsp;</td>
					<td>
					<textarea rows='15' cols='55' name='debug_textarea' id='debug_textarea' wrap='hard' readonly>&nbsp;</textarea>
					</td>
				</tr>
<?php endif; ?>
				<tr>
					<td>
<?php
#blacklist table
#echo squidguard_blacklist_list();
?>
					</td>
				</tr>
			</table>
			</form>
		</div>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[
function setProgress(barName, percent, transition) {
	$('.progress').show()
	if (!transition) {
		$('#' + barName).css('transition', 'width 0s ease-in-out');
	}

	$('#' + barName).css('width', percent + '%').attr('aria-valuenow', percent);
}

function getactivity(action) {
	var url  = "./squidguard_blacklist.php";
	var pars = 'getactivity=yes';

	if (action == 'download') {
		pars = pars + '&blacklist_download_start=yes&blacklist_url=' + encodeURIComponent($('#blacklist_url').val());
	}
	if (action == 'cancel') {
		pars = pars + '&blacklist_download_cancel=yes';
	}
	if (action == 'restore_default') {
		pars = pars + '&blacklist_restore_default=yes';
	}
	if (action == 'clear_log') {
		pars = pars + '&blacklist_clear_log=yes';
	}

	jQuery.ajax(url,
		{
		type: 'get',
		data: pars,
		success: activitycallback
		}
		);
}

function activitycallback(html) {

<?php if (DEBUG_AJAX == "true") echo "$('#debug_textarea').html(html);"; ?>
	eval(html);
	// refresh 3 sec
	setTimeout('getactivity()', 3100);
}

events.push(function() {
	setTimeout('getactivity()', 150);
});
//]]>
</script>
<?php include("foot.inc"); ?>
