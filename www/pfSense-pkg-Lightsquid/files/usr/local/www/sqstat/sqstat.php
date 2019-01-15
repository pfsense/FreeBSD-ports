<?php
/*
 * sqstat.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2006 Alex Samorukov <samm@os2.kiev.ua>
 * Copyright (c) 2011 Sergey Dvoriancev <dv_serg@mail.ru>
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

/* Squid Proxy Server realtime stats */
require_once('guiconfig.inc');
require_once('sqstat.class.php');

// init
$squidclass = new squidstat();

/*
 * Requests
 */

/* AJAX response */
if ($_REQUEST['getactivity']) {
	header("Content-type: text/javascript");
	echo sqstat_AJAX_response( $_REQUEST );
	exit;
}

/*
 * Functions
 */

function sqstat_AJAX_response( $request ) {
	global $squidclass, $data;
	$res = '';

	if (sqstat_loadconfig() != 0) {
		return sqstat_AJAX_error(sqstat_errorHTML());
	}

	// Actions
	$data = $squidclass->query_exec();

	$ver  = sqstat_serverInfoHTML();
	$res .= "$('#sqstat_serverver').html('{$ver}');";

	$time = date("h:i:s d/m/Y");
	$res .= "$('#sqstat_updtime').html('{$time}');";

	$data = sqstat_resultHTML( $data );
	if ($squidclass->errno == 0) {
		$data = sqstat_AJAX_prep($data);
		$res .= "$('#sqstat_result').html('{$data}');";
	} else {
		// error
		$res .= sqstat_AJAX_error(sqstat_errorHTML());
	}

	return $res;
}

function sqstat_AJAX_prep($text) {
	$text = str_replace("'", "\'", $text);
	$text = str_replace("\n", "\\r\\n", $text);
	return $text;
}

function sqstat_AJAX_error($err) {
	$err = sqstat_AJAX_prep($err);
	$t .= "$('#sqstat_result').html('{$err}');";
	return $t;
}

/*
 *Reports
 */

function sqstat_headerHTML() {
	global $squidclass;

	$date = date("h:i:s d/m/Y");
	$squidinfo = sqstat_serverInfoHTML();

	if (empty($squidclass->autorefresh)) {
		$squidclass->autorefresh = 0;
	}

	return <<< EOD
 	<form method="get" action="{$_SERVER["PHP_SELF"]}">
		<input id="counter" name="counter" type="hidden" value=0 />
		Squid RealTime stat {$squidclass->sqstat_version} for the {$servers} proxy server <a id='sqstat_serverver'>{$squidinfo}</a>.<br/>
		Auto refresh:
		<input id="refresh" name="refresh" type="text" size="4" value="{$squidclass->autorefresh}"/> sec.
		<input type="button" value="Update" onclick="update_start();" />
		<input type="button" value="Stop" onclick="update_stop();" /> Created at: <tt id='sqstat_updtime'>{$date}</tt><br/>
	</form>
EOD;
}

function sqstat_serverInfoHTML() {
	global $squidclass;
	return $squidclass->server_version . " ({$squidclass->squidhost}:{$squidclass->squidport})";
}

function sqstat_resultHTML($data) {
	global $squidclass;

	$group_by_name = $squidclass->group_by_name;
	$use_js = true;

	$t = array();

	// table header
	$t[] = "<table class='result' align='center' width='100%' border='0'>";
	$t[] = "<tr>";
	$t[] = "<th>{$group_by_name}</th><th>URI</th>";
	if ($squidclass->use_sessions) {
		$t[] = "<th>Curr. Speed</th><th>Avg. Speed</th>";
	}
	$t[] = "<th>Size</th><th>Time</th>";
	$t[] = "</tr>";

	// table body
	if (is_array($data['users'])) {
		$tbl = array();

		$con_color = 0;
		foreach($data['users'] as $key => $v) {
			// skip total info
			if ($key == 'total') {
				continue;
			}
			// group row
			$tbl[] = "<tr>";
			$tbl[] = "<td style='border-right:0;' colspan='2'><b>" . (is_int($key) ? long2ip($key) : $key) . "</b></td>";
			$tbl[] = "<td style='border-left:0;'  colspan='5'>&nbsp;</td>";
			$tbl[] = "</tr>";

			// connections row
			foreach ($v['con'] as $con) {
				if ($use_js) {
					$js = "onMouseout='hideddrivetip()' onMouseover='ddrivetip(\"" . $squidclass->implode_with_keys($con,"<br/>") . "\")'";
				} else {
					$js='';
				}

				// begin new row
				$class = (++$con_color % 2 == 0) ? " class='odd'" : "";
				$tbl[] = "<tr ($class)>";

				// URL
				$uri   = "<a target='_blank' href='" . htmlspecialchars($con["uri"]) ."'>{$con['uritext']}</a>";
				$tbl[] = "<td id='white'></td>";
				$tbl[] = "<td nowrap {$js} width='80%'>{$uri}</td>";

				// speed
				if ($squidclass->use_sessions) {
					$cur_s = round($con['cur_speed'], 2) > 0 ? sprintf("%01.2f KB/s", $con['cur_speed']) : '';
					$avg_s = round($con['avg_speed'], 2) > 0 ? sprintf("%01.2f KB/s", $con['avg_speed']) : '';
					$tbl[] = "<td nowrap align='right'>{$cur_s}</td>";
					$tbl[] = "<td nowrap align='right'>{$avg_s}</td>";
				}

				// file size
				$filesize = $squidclass->filesize_format($con["bytes"]);
				$duration = $squidclass->duration($con["seconds"], "short");
				$tbl[] = "<td nowrap align='right'>{$filesize}</td>";
				$tbl[] = "<td nowrap align='right'>{$duration}</td>";

				// end row
				$tbl[] = "</tr>";
			}

			// total user speed
			if ($squidclass->use_sessions) {
				$user_curr = sprintf("%01.2f KB/s", $v['user_curr']);
				$user_avg  = sprintf("%01.2f KB/s", $v['user_avg']);
				$tbl[] ="<tr>";
				$tbl[] ="<td colspan='2'></td>";
				$tbl[] ="<td align='right' id='highlight'>{$user_curr}</td>";
				$tbl[] ="<td align='right' id='highlight'>{$user_avg}</td>";
				$tbl[] ="<td colspan='2'></td>";
			}
		}


		// status row
		$stat = array();
		$ausers = sprintf("%d", $data['ausers']);
		$acon   = sprintf("%d", $data['acon']);
		$stat[] = "<tr class='total'><td><b>Total:</b></td>";
		if ($squidclass->use_sessions) {
			$total_curr = sprintf("%01.2f", $data['total_curr']);
			$total_avg  = sprintf("%01.2f", $data['total_avg']);
			$stat[] = "<td align='right' colspan='5'><b>{$ausers}</b> users and <b>{$acon}</b> connections @ <b>{$total_curr}/{$total_avg}</b> KB/s (CURR/AVG)</td>";
		} else {
			$stat[] = "<td align='right' colspan='5'><b>{$ausers}</b> users and <b>{$acon}</b> connections</td>";
		}
		$t[] = "</tr>";
	} // ENDIF (is_array($data['users']))

	if ($ausers == 0) {
		$t[] = "<tr><td colspan=6><b>No active connections</b></td></tr>";
	} else {
		$stat = implode("\n", $stat);
		$tbl  = implode("\n", $tbl);
		$t[]  = $stat . $tbl . $stat;
	}

	$t[] = "</table>";
	$t[] = "<p class='copyleft'>Report based on SQStat &copy; <a href='mailto:samm@os2.kiev.ua?subject=SqStat '" . SQSTAT_VERSION . "'>Alex Samorukov</a>, 2006</p>";

	return implode("\n", $t);
}

function sqstat_errorHTML() {
	global $squidclass;
	$t = array();

	// table header
	$t[] = "<table class='result' align='center' width='100%' border='0'>";
	$t[] = "<tr><th align='left'>SqStat error</th></tr>";
	$t[] = "<tr><td>";
	$t[] = '<p style="color:red">Error (' . $squidclass->errno . '): ' . $squidclass->errstr . '</p>';
	$t[] = "</td></tr>";
	$t[] = "</table>";

	return implode ("\n", $t);
}

function sqstat_loadconfig() {
	global $squidclass, $config;

	$squidclass->errno = 0;
	$squidclass->errstr = '';

	$squidclass->sqstat_version = SQSTAT_VERSION;

	$iface = '127.0.0.1';
	/* Load config from pfSense and find proxy port */
	$iport = 3128;
	if (is_array($config['installedpackages']['squid']['config'][0])) {
		$squid_settings = $config['installedpackages']['squid']['config'][0];
	} else {
		$squid_settings = array();
	}
	$iport = $squid_settings['proxy_port'] ? $squid_settings['proxy_port'] : 3128;

	$squidclass->squidhost = $iface;
	$squidclass->squidport = $iport;

	$squidclass->group_by = "host";
	$squidclass->resolveip = true;
	$squidclass->hosts_file = ''; // hosts file not used
	$squidclass->autorefresh = 3; // refresh 3 secs by default
	$squidclass->cachemgr_passwd = '';

	// Load hosts file if defined
	if (!empty($squidclass->hosts_file)) {
		$squidclass->load_hosts();
	}

	return $squidclass->errno;
}
/*
 * HTML Page
 */

$pgtitle = array(gettext("Package"), gettext("Squid"), gettext("Realtime Stats (SQStat)"));
require_once("head.inc");
$csrf_token = csrf_get_tokens();
?>

<link href="sqstat.css" rel="stylesheet" type="text/css"/>
<script type="text/javascript" src="zhabascript.js"></script>

<!-- HTML start -->
<?php
	// Prepare page data
	$data = '';
	sqstat_loadconfig();
	if (sqstat_loadconfig() == 0) {
		$data = $squidclass->query_exec();
	}

	if ($squidclass->errno == 0) {
		$data = sqstat_resultHTML($data);
	} else {
		// error
		$data = sqstat_errorHTML();
	}
?>

<!-- Form -->
<div id="sqstat_header" class="header">
	<?=( sqstat_headerHTML() ); ?>
</div>

<!-- Result table -->
<div id="sqstat_result" class="result">
	<?=($data); ?>
</div>

<script type="text/javascript">
//<![CDATA[
var intervalID = 0;

function getactivity(action) {
	var url = "<?=($_SERVER["PHP_SELF"]); ?>";
	var pars = "getactivity=yes" + "<? echo '&__csrf_magic='.$csrf_token ?>";

	jQuery.ajax(url,
		{
		type: 'post',
		data: pars,
		success: activitycallback
		}
		);
}

function activitycallback(html) {
	eval(html);
}

function update_start() {
	var cmax = parseInt($('#refresh').val());

	update_stop();

	if (cmax > 0) {
		intervalID = window.setInterval('getactivity();', cmax * 1000);
	}
}

function update_stop() {
	window.clearInterval(intervalID);
	intervalID = 0;
}

// pre-call
events.push(function() {
	setTimeout('update_start()', 150);
});

//]]>
</script>

<?php include("foot.inc"); ?>
