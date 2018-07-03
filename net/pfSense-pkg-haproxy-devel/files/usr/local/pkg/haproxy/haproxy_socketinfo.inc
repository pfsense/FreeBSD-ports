<?php
/*
 * haproxy_socketinfo.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013 PiBa-NL
 * Copyright (c) 2011 Thomas Schaefer - Tomschaefer.org
 * Copyright (c) 2011 Marcello Coutinho
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

/*
	Some mods made from pfBlocker widget to make this for HAProxy on Pfsense
	Copyleft 2012 by jvorhees
*/

//set variables
$refresh_rate = 5000; //miliseconds
$show_frontends = "YES";
$show_clients = "YES";
$show_clients_traffic = "YES";

function haproxy_socket_command($command){
	$result = array();
	if (file_exists("/tmp/haproxy.socket")) {
		$socket = @stream_socket_client('unix:///tmp/haproxy.socket', $errno, $errstr);
		if ($socket) {
			fwrite($socket, "$command\n");
			while (!feof($socket)) {
				$result[] = fgets($socket);
			}
			fclose($socket);
		} else {
			echo "cannot open command socket, haproxy not running? ({$errstr})";
		}
	} else {
		echo "haproxy command socket does not exist, haproxy not running?";
	}
	return $result;
}

function haproxy_set_server_enabled($backend, $server, $enable) {//"enable be/server ?"/"disable be/server ?"
	$enablecommand = $enable ? "enable" : "disable";
	return haproxy_socket_command("$enablecommand server $backend/$server");
}

function haproxy_get_tables(){// "show table"
	$result = array();
	$cmdresult = haproxy_socket_command("show table");
	foreach($cmdresult as $line) {
		if (trim($line) == "")
			continue;
		list($table,$type,$size,$used) = explode(",", $line);
		$table = explode(":", $table);
		$type = explode(":", $type);
		$size = explode(":", $size);
		$used = explode(":", $used);
		$newtable = array();
		$tablename = trim($table[1]);
		$newtable['type'] = trim($type[1]);
		$newtable['size'] = $size[1];
		$newtable['used'] = $used[1];
		$result[$tablename] = $newtable;
	}
	return $result;
}

function haproxy_get_statistics(){// "show stat"
	$result = array();
	$frontends=array();
	$backends=array();
	$servers=array();

	$cmdresult = haproxy_socket_command("show stat");
	
	foreach($cmdresult as $line) {
		list($pxname,$svname,$qcur,$qmax,$scur,$smax,$slim,$stot,$bin,$bout,$dreq,$dresp,$ereq,$econ,$eresp,$wretr,$wredis,$status,$weight,$act,$bck,$chkfail,$chkdown,$lastchg,$downtime,$qlimit,$pid,$iid,$sid,$throttle,$lbtot,$tracked,$type,$rate,$rate_lim,$rate_max,$check_status,$check_code,$check_duration,$hrsp_1xx,$hrsp_2xx,$hrsp_3xx,$hrsp_4xx,$hrsp_5xx,$hrsp_other,$hanafail,$req_rate,$req_rate_max,$req_tot,$cli_abrt,$srv_abrt,$comp_in,$comp_out,$comp_byp,$comp_rsp) = explode(",", $line);
		#Retrieve data
		switch ($svname) {
		case "FRONTEND":	
			$frontends[] = array(
				"pxname" => $pxname,
				"scur" => $scur,
				"slim" => $slim,
				"status" => $status);
		break;
		case "BACKEND":	
			$backends[] = array(
				"pxname" => $pxname,
				"scur" => $scur,
				"slim" => $slim,
				"status" => $status);
		break;
		default:	
			$servers[] = array(
				"pxname" => $pxname,
				"svname" => $svname,
				"scur" => $scur,
				"status" => $status);
		}
	}
	$result['frontends'] = $frontends;
	$result['backends'] = $backends;
	$result['servers'] = $servers;
	return $result;
}

function haproxy_get_clients($show_traffic = false){// "show sess"
	$clients=array();
	$sessions = haproxy_socket_command("show sess");
	foreach($sessions as $line) {
		if (trim($line) == "")
			continue;
		list($sessid,$proto,$src,$fe,$be,$srv,$ts,$age,$calls,$rq,$rp,$s0,$s1,$exp) = explode(" ", $line);
		#Retrieve data
		$sessid = explode(":", $sessid);
		$src = explode("=", $src);
		$srcip = explode(":", $src[1]);
		$srcport = explode(":", $src[1]);
		$be = explode("=", $be);
		$srv = explode("=", $srv);
		$age = explode("=", $age);
		$calls = explode("=", $calls);
		$exp = explode("=", $exp);
		$clients[] = array(
			"sessid" => $sessid[0],
			"src" => $src[1],
			"srcip" => $srcip[0],
			"srcport" => $srcport[1],
			"be" => $be[1],
			"srv" => $srv[1],
			"age" => $age[1],
			"calls" => $calls[1],
			"exp" => $exp[1]);
	}
	if ($show_traffic) {
		foreach($clients as &$client) {
			$session_data = haproxy_socket_command("show sess {$client['sessid']}");
			$client['session_data'] = $session_data;
			
			$req = explode(" ",$session_data[13]);
			$x = explode("=",$req[7]);
			$client['session_datareq'] = $x[1];
			$res = explode(" ",$session_data[16]);
			$x = explode("=",$res[7]);
			$client['session_datares'] = $x[1];
		}
	}
	return $clients;
}

?>
