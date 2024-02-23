<?php
/*
 * squidguard_log.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2024 Rubicon Communications, LLC (Netgate)
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

require_once('guiconfig.inc');
require_once('notices.inc');
if (file_exists("/usr/local/pkg/squidguard.inc")) {
	require_once("/usr/local/pkg/squidguard.inc");
}

# ------------------------------------------------------------------------------
# defines
# ------------------------------------------------------------------------------
$selfpath = "/squidGuard/squidguard_log.php";

# ------------------------------------------------------------------------------
# Requests
# ------------------------------------------------------------------------------
if ($_REQUEST['getactivity']) {
	header("Content-type: text/javascript");
	echo squidguard_log_AJAX_response( $_REQUEST );
	exit;
}

# ------------------------------------------------------------------------------
# Functions
# ------------------------------------------------------------------------------

function squidguard_log_AJAX_response( $request ) {
	$res = '';
	$offset   = $request['offset']  ? $request['offset'] : 0;
	$reverse  = $request['reverse'] == 'yes'? true : false;
	$pcaption = '&nbsp;';

	# Actions
	switch($request['rep']) {
		case 'filterconf':
			if (function_exists("squidguard_conflist"))
				$cont = squidguard_conflist( );
			else $cont = "Function 'squidguard_conflist' not found.";
			$res = squidguard_prep_textareacont($cont);
			break;
		case 'proxyconf':
			if (function_exists("squidguard_squid_conflist"))
				$cont = squidguard_squid_conflist( );
			else $cont = "Function 'squidguard_squid_conflist' not found.";
			$res = squidguard_prep_textareacont($cont);
			break;
		case 'guilog':
			$res = squidguard_logrep(squidguard_guidump( $offset, 50, true));
			break;
		case 'filterlog':
			$res = squidguard_logrep(squidguard_filterdump( $offset, 50, true));
			break;
		case "blocked":
		default:
			$res = squidguard_logrep(squidguard_blockdump( $offset, 50, true));
			break;
	}

	$res .= "$('#offset').val({$offset});";
	$res .= "$('#showoffset').html({$offset});";
	return $res;
}

function squidguard_logrep( &$dump ) {
	$res  = '';

	if (!empty($dump)) {
		if (is_array($dump)) {
			$acount = count($dump[0]) ? count($dump[0]) : 1;
			$res = "<table class=\'table table-hover table-condensed\'>";
			$res .= "<caption>Show 50 entries starting at&nbsp;" .
				"<span style=\'cursor: pointer;\' onclick=\'report_down();\'>&lt;&lt;</span>" .
				"&nbsp;<span id='showoffset' >0</span>&nbsp;" .
				"<span style=\'cursor: pointer;\' onclick=\'report_up();\'>&gt;&gt;</span>&nbsp;" .
				"</caption><thead></thead><tbody>";

			foreach($dump as $dm) {
				if (!$dm[0] || !$dm[1]) continue;
				# datetime
				$dm[0] = date("d.m.Y H:i:s", strtotime($dm[0]));
				$res  .= "<tr><td class=\'listlr\' nowrap>{$dm[0]}</td>";

				# col 1
				$dm[1] = htmlentities($dm[1]);
				$dm[1] = squidguard_html_autowrap($dm[1]);
				$res  .= "<td class=\'listr\'>{$dm[1]}</td>";

				# for blocked rep
				if (count($dm) > 2) {
					$dm[2] = htmlentities($dm[2]);
					$dm[2] = squidguard_html_autowrap($dm[2]);
					$res .= "<td class=\'listr\' width=\'*\'>{$dm[2]}</td>";
					$res .= "<td class=\'listr\'>{$dm[3]}</td>";
				}
				$res  .= "</tr>";
			}
			$res .= "</tbody></table>";
		}
		else $res = $dump;
	} else {
		$res = "No data.";
	}

	$res  = "$(\"#reportarea\").html(\"{$res}\");";
	return $res;
}

function squidguard_prepfor_JS($cont) {
	# replace for JS
	$cont = str_replace("\n", "\\n", $cont);
	$cont = str_replace("\r", "\\r", $cont);
	$cont = str_replace("\t", "\\t", $cont);
	$cont = str_replace("'", "\'",  $cont);
	$cont = str_replace("\"", "\'",  $cont);
	return $cont;
}

function squidguard_prep_textareacont($cont) {
	$cont = squidguard_prepfor_JS($cont);
	return "$('#reportarea').html(\"<br><pre id='pconf' name='pconf' wrap='hard' readonly></pre>\");" .
		"$('#pconf').html('$cont');";
}

function squidguard_html_autowrap($cont) {
	# split strings
	$p     = 0;
	$pstep = 25;
	$str   = $cont;
	$cont  = '';
	for ( $p = 0; $p < strlen($str); $p += $pstep ) {
		$s = substr( $str, $p, $pstep );
		if ( !$s ) break;
		$cont .= $s . "<wbr/>";
	}

	return $cont;
}

# ------------------------------------------------------------------------------
# HTML Page
# ------------------------------------------------------------------------------

$pgtitle = array(gettext("Package"), gettext("SquidGuard"), gettext("Logs"));
include("head.inc");
$tab_array = array();
$tab_array[] = array(gettext("General settings"), false, "/pkg_edit.php?xml=squidguard.xml&amp;id=0");
$tab_array[] = array(gettext("Common ACL"), false, "/pkg_edit.php?xml=squidguard_default.xml&amp;id=0");
$tab_array[] = array(gettext("Groups ACL"), false, "/pkg.php?xml=squidguard_acl.xml");
$tab_array[] = array(gettext("Target categories"), false, "/pkg.php?xml=squidguard_dest.xml");
$tab_array[] = array(gettext("Times"), false, "/pkg.php?xml=squidguard_time.xml");
$tab_array[] = array(gettext("Rewrites"), false, "/pkg.php?xml=squidguard_rewr.xml");
$tab_array[] = array(gettext("Blacklist"), false, "/squidGuard/squidguard_blacklist.php");
$tab_array[] = array(gettext("Log"), true,  $selfpath);
$tab_array[] = array(gettext("XMLRPC Sync"), false, "/pkg_edit.php?xml=squidguard_sync.xml&amp;id=0");
display_top_tabs($tab_array);
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Blacklist Update"); ?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<form action="sg_log.php" method="post">
			<input type="hidden" id="reptype" val="">
			<input type="hidden" id="offset"  val="0">
			<table class="table table-hover table-condensed">
				<thead>
				<tr>
					<th class="text-center">
						<div class="btn-group">
							<button type="button" class="btn btn-xs btn-default" id="hd_blocklog" name="hd_blocklog" onclick="getactivity('blocklog');">
								<i class="fa-regular fa-file-lines"></i>
								<?= gettext("Blocked") ?>
							</button>
							<button type="button" class="btn btn-xs btn-default" id="hd_guilog" name="hd_guilog" onclick="getactivity('guilog');">
								<i class="fa-regular fa-file-lines"></i>
								<?= gettext("Filter GUI log") ?>
							</button>
							<button type="button" class="btn btn-xs btn-default" id="hd_filterlog" name="hd_filterlog" onclick="getactivity('filterlog');">
								<i class="fa-regular fa-file-lines"></i>
								<?= gettext("Filter log") ?>
							</button>
							<button type="button" class="btn btn-xs btn-default" id="hd_proxyconf" name="hd_proxyconf" onclick="getactivity('proxyconf');">
								<i class="fa-regular fa-file-lines"></i>
								<?= gettext("Proxy config") ?>
							</button>
							<button type="button" class="btn btn-xs btn-default" id="hd_filterconf" name="hd_filterconf" onclick="getactivity('filterconf');">
								<i class="fa-regular fa-file-lines"></i>
								<?= gettext("Filter config") ?>
							</button>
						</div>
					</th>
				</tr>
				</thead>
				<tbody>
					<tr>
						<td colspan="5">
							<div id="reportarea" name="reportarea"><?= gettext("Select a log file above to view its contents."); ?></div>
						</td>
					</tr>
				</tbody>
			</table>
			</form>
		</div>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[

function getactivity(action) {
	var url  = "./squidguard_log.php";
	var pars = 'getactivity=yes';
	var act  = action;
	var offset  = 0;
	var reverse = 'yes';

	if (action == 'report_up') {
		act    = $('#reptype').val();
		offset = parseInt($('#offset').val());
		offset = offset + 50;
	} else if (action == 'report_down') {
		act      = $('#reptype').val();
		offset = parseInt($('#offset').val());
		offset = offset - 50;
		offset = offset >= 0 ? offset : 0;
	} else {
		$('#reptype').val(action ? action : 'blocklog');
		$('#offset').val(0);
		offset = 0;
	}

	pars = pars + '&rep=' + act + '&reverse=' + reverse + '&offset=' + offset;

	jQuery.ajax(url,
		{
		type: 'get',
		data: pars,
		success: activitycallback
		}
		);
}

function activitycallback(html) {
	eval(html);
	sethdtab_selected();
}

function report_up() {
	getactivity('report_up');
}

function report_down() {
	getactivity('report_down');
}

function sethdtab_selected() {
	var sel = "hd_" + $('#reptype').val();
	$('#hd_blocklog').toggleClass(   'btn-success', (sel == 'hd_blocklog')    );
	$('#hd_guilog').toggleClass(     'btn-success', (sel == 'hd_guilog')      );
	$('#hd_filterlog').toggleClass(  'btn-success', (sel == 'hd_filterlog')   );
	$('#hd_proxyconf').toggleClass(   'btn-success', (sel == 'hd_proxyconf')   );
	$('#hd_filterconf').toggleClass( 'btn-success', (sel == 'hd_filterconf')  );
}

events.push(function() {
	setTimeout('getactivity()', 150);
});
//]]>
</script>
<?php include("foot.inc"); ?>
