<?php
/*
	sqstat.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2006 Alex Samorukov <samm@os2.kiev.ua>
	Copyright (C) 2011 Sergey Dvoriancev <dv_serg@mail.ru>
	Copyright (C) 2015 ESF, LLC
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
 * HTML Page
 */

$pgtitle = "Squid Proxy Server: Realtime Stats (SQStat)";

require_once("head.inc");
$csrf_token = csrf_get_tokens();
?>

<link href="sqstat.css" rel="stylesheet" type="text/css"/>
<script type="text/javascript" src="/javascript/scriptaculous/prototype.js"></script>
<script type="text/javascript" src="zhabascript.js"></script>

<!-- AJAX script -->
<script type="text/javascript">
//<![CDATA[
var intervalID = 0;

function el(id) {
	return document.getElementById(id);
}

function getactivity(action) {
	var url = "<?php echo ($_SERVER["PHP_SELF"]); ?>";
	var pars = "getactivity=yes" + "<? echo '&__csrf_magic='.$csrf_token ?>";

	var myAjax = new Ajax.Request(url, {
		method: 'post',
		parameters: pars,
		onComplete: activitycallback
	});
}

function activitycallback(transport) {
	if (200 == transport.status) {
		result = transport.responseText;
	}
}

function update_start() {
	var cmax = parseInt(el('refresh').value);

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
window.setTimeout('update_start()', 150);

//]]>
</script>

<!-- HTML start -->
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>

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
	<?php echo ( sqstat_headerHTML() ); ?>
</div>

<!-- Result table -->
<div id="sqstat_result" class="result">
	<?php echo ($data); ?>
</div>

<!-- HTML end -->
<?php include("fend.inc"); ?>
</body>
</html>


<?php

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
	$res .= "el('sqstat_serverver').innerHTML = '$ver';";

	$time = date("h:i:s d/m/Y");
	$res .= "el('sqstat_updtime').innerHTML = '$time';";

	$data = sqstat_resultHTML( $data );
	if ($squidclass->errno == 0) {
		$data = sqstat_AJAX_prep($data);
		$res .= "el('sqstat_result').innerHTML = '$data';";
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
	$t .= "el('sqstat_result').innerHTML = '$err';";
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

?>
