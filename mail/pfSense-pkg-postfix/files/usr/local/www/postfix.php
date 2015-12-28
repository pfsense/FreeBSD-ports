<?php
/*
	postfix.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2011-2015 Marcello Coutinho <marcellocoutinho@gmail.com>
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
require_once("/etc/inc/util.inc");
require_once("/etc/inc/functions.inc");
require_once("/etc/inc/pkg-utils.inc");
require_once("/etc/inc/globals.inc");
require_once("xmlrpc.inc");
require_once("xmlrpc_client.inc");
require_once("/usr/local/pkg/postfix.inc");

$uname = posix_uname();
if ($uname['machine'] == 'amd64') {
        ini_set('memory_limit', '250M');
}

function get_remote_log() {
	global $config, $g, $postfix_dir;
	$curr_time = time();
	$log_time = date('YmdHis', $curr_time);

	if (is_array($config['installedpackages']['postfixsync'])) {
		$synctimeout = $config['installedpackages']['postfixsync']['config'][0]['synctimeout'] ?: '250';
		foreach ($config['installedpackages']['postfixsync']['config'][0]['row'] as $sh) {
			// Get remote data for enabled fetch hosts
			if ($sh['enabless'] && $sh['sync_type'] == 'fetch') {
				$sync_to_ip = $sh['ipaddress'];
				$port = $sh['syncport'];
				$username = $sh['username'] ?: 'admin';
				$password = $sh['password'];
				$protocol = $sh['syncprotocol'];
				$file = '/var/db/postfix/' . $server . '.sql';

				$error = '';
				$valid = TRUE;

				if ($password == "") {
					$error = "Password parameter is empty. ";
					$valid = FALSE;
				}
				if ($protocol == "") {
					$error = "Protocol parameter is empty. ";
					$valid = FALSE;
				}
				if (!is_ipaddr($sync_to_ip) && !is_hostname($sync_to_ip) && !is_domain($sync_to_ip)) {
					$error .= "Misconfigured Replication Target IP Address or Hostname. ";
					$valid = FALSE;
				}
				if (!is_port($port)) {
					$error .= "Misconfigured Replication Target Port. ";
					$valid = FALSE;
				}
				if ($valid) {
					// Take care of IPv6 literal address
					if (is_ipaddrv6($sync_to_ip)) {
						$sync_to_ip = "[{$sync_to_ip}]";
					}
					$url = "{$protocol}://{$sync_to_ip}";

					print "{$sync_to_ip} {$url}, {$port}\n";
					$method = 'pfsense.exec_php';
					$execcmd  = "require_once('/usr/local/www/postfix.php');\n";
					$execcmd .= '$toreturn = get_sql('.$log_time.');';

					/* Assemble XMLRPC payload. */
					$params = array(XML_RPC_encode($password), XML_RPC_encode($execcmd));
					log_error("[postfix] Fetching sql data from {$sync_to_ip}.");
					$msg = new XML_RPC_Message($method, $params);
					$cli = new XML_RPC_Client('/xmlrpc.php', $url, $port);
					$cli->setCredentials($username, $password);
					//$cli->setDebug(1);
					$resp = $cli->send($msg, $synctimeout);
					$a = $resp->value();
					$errors = 0;
					//var_dump($sql);
					foreach($a as $b) {
						foreach ($b as $c) {
							foreach ($c as $d) {
								foreach ($d as $e) {
									$update = unserialize($e['string']);
									print $update['day'] . "\n";
									if ($update['day'] != "") {
										create_db($update['day'] . ".db");
										if ($debug) {
											print $update['day'] . " writing from remote system to db...";
										}
										$dbhandle = sqlite_open($postfix_dir . '/' . $update['day'] . ".db", 0666, $error);
										//file_put_contents("/tmp/" . $key . '-' . $update['day'] . ".sql", gzuncompress(base64_decode($update['sql'])), LOCK_EX);
										$ok = sqlite_exec($dbhandle, gzuncompress(base64_decode($update['sql'])), $error);
										if (!$ok) {
											$errors++;
											die ("Cannot execute query. $error\n".$update['sql']."\n");
										} elseif ($debug) {
											print "ok\n";
										}
										sqlite_close($dbhandle);
									}
								}
							}
						}
					}
					if ($errors == 0) {
						$method = 'pfsense.exec_php';
						$execcmd  = "require_once('/usr/local/www/postfix.php');\n";
						$execcmd .= 'flush_sql('.$log_time.');';
						/* Assemble XMLRPC payload. */
						$params = array(XML_RPC_encode($password), XML_RPC_encode($execcmd));
						log_error("[postfix] Flushing sql buffer file from {$sync_to_ip}.");
						$msg = new XML_RPC_Message($method, $params);
						$cli = new XML_RPC_Client('/xmlrpc.php', $url, $port);
						$cli->setCredentials($username, $password);
						//$cli->setDebug(1);
						$resp = $cli->send($msg, $synctimeout);
					}
				} else {
					log_error("[postfix] Fetch sql database from '{$sync_to_ip}' aborted due to the following error(s): {$error}");
				}
			}
		}
		log_error("[postfix] Fetch sql database completed.");
	}
}

function get_sql($log_time) {
	global $config, $xmlrpc_g;
	$server = $_SERVER['REMOTE_ADDR'];

	if (is_array($config['installedpackages']['postfixsync'])) {
		foreach($config['installedpackages']['postfixsync']['config'][0]['row'] as $sh) {
			$sync_to_ip = $sh['ipaddress'];
			$sync_type = $sh['sync_type'];
			$file = '/var/db/postfix/' . $server . '.sql';
			if ($sync_to_ip == "{$server}" && $sync_type == "share" && file_exists($file)) {
				rename($file, $file . ".$log_time");
				return (file($file . ".$log_time"));
			}
		}
		return "";
	}
}

function flush_sql($log_time) {
	if (preg_match("/\d+\.\d+\.\d+\.\d+/", $_SERVER['REMOTE_ADDR'])) {
		unlink_if_exists('/var/db/postfix/' . $_SERVER['REMOTE_ADDR'] . ".sql.{$log_time}");
	}
}

function grep_log(){
	global $postfix_dir,$postfix_arg,$config,$g;

	$total_lines=0;
	$days=array();
	$grep="\(MailScanner\|postfix.cleanup\|postfix.smtp\|postfix.error\|postfix.qmgr\)";
	$curr_time = time();
	$log_time=strtotime($postfix_arg['time'],$curr_time);
	$m=date('M',strtotime($postfix_arg['time'],$curr_time));
	$j=substr("  ".date('j',strtotime($postfix_arg['time'],$curr_time)),-3);
	# file grep loop
	$maillog_filename = "/var/log/maillog";
	foreach ($postfix_arg['grep'] as $hour){
		if (!file_exists($maillog_filename) || !is_readable($maillog_filename))
			continue;
	  print "/usr/bin/grep '^".$m.$j." ".$hour.".*".$grep."' {$maillog_filename}\n";
	  $lists=array();
	  exec("/usr/bin/grep " . escapeshellarg('^'.$m.$j." ".$hour.".*".$grep)." {$maillog_filename}", $lists);
	  foreach ($lists as $line){
	  	#check where is first mail record
	  	if (preg_match("/ delay=(\d+)/",$line,$delay)){
	  		$day=date("Y-m-d",strtotime("-".$delay[1]." second",$log_time));
	  		if (! in_array($day,$days)){
	  			$days[]=$day;
	  			create_db($day.".db");
	  			print "Found logs to $day.db\n";
	  			$stm_queue[$day]="BEGIN;\n";
	  			$stm_noqueue[$day]="BEGIN;\n";
	  			}
	  		}
	  	else{
		  	$day=date("Y-m-d",strtotime($postfix_arg['time'],$curr_time));
		  	if (! in_array($day,$days)){
	  			$days[]=$day;
	  			create_db($day.".db");
	  			print "Found logs to $day.db\n";
	  			$stm_queue[$day]="BEGIN;\n";
	  			$stm_noqueue[$day]="BEGIN;\n";
		  		}
	  		}
		$status=array();
		$total_lines++;
		#Nov  8 09:31:50 srvch011 postfix/smtpd[43585]: 19C281F59C8: client=pm03-974.auinmem.br[177.70.0.3]
		if(preg_match("/(\w+\s+\d+\s+[0-9,:]+) (\w+) postfix.smtpd\W\d+\W+(\w+): client=(.*)/",$line,$email)){
			$values="'".$email[3]."','".$email[1]."','".$email[2]."','".$email[4]."'";
			if(${$email[3]}!=$email[3])
				$stm_queue[$day].='insert or ignore into mail_from(sid,date,server,client) values ('.$values.');'."\n";
			${$email[3]}=$email[3];
		}
		#Dec  2 22:21:18 pfsense MailScanner[60670]: Requeue: 8DC3BBDEAF.A29D3 to 5AD9ABDEB5
		else if (preg_match("/(\w+\s+\d+\s+[0-9,:]+) (\w+) MailScanner.*Requeue: (\w+)\W\w+ to (\w+)/",$line,$email)){
			$stm_queue[$day].= "update or ignore mail_from set sid='".$email[4]."' where sid='".$email[3]."';\n";
		}
		#Dec  5 14:06:10 srvchunk01 MailScanner[19589]: Message 775201F44B1.AED2C from 209.185.111.50 (marcellocoutinho@mailtest.com) to sede.mail.test.com is spam, SpamAssassin (not cached, escore=99.202, requerido 6, autolearn=spam, DKIM_SIGNED 0.10, DKIM_VALID -0.10, DKIM_VALID_AU -0.10, FREEMAIL_FROM 0.00, HTML_MESSAGE 0.00, RCVD_IN_DNSWL_LOW -0.70, WORM_TEST2 100.00)
		else if (preg_match("/(\w+\s+\d+\s+[0-9,:]+) (\w+) MailScanner\W\d+\W+\w+\s+(\w+).* is spam, (.*)/",$line,$email)){
			$stm_queue[$day].= "insert or ignore into mail_status (info) values ('spam');\n";
			print "\n#######################################\nSPAM:".$email[4].$email[3].$email[2]."\n#######################################\n";
			$stm_queue[$day].= "update or ignore mail_to set status=(select id from mail_status where info='spam'), status_info='".preg_replace("/(\<|\>|\s+|\'|\")/"," ",$email[4])."' where from_id in (select id from mail_from where sid='".$email[3]."' and server='".$email[2]."');\n";
		}
		#Nov 14 09:29:32 srvch011 postfix/error[58443]: 2B8EB1F5A5A: to=<hildae.sva@pi.email.com>, relay=none, delay=0.66, delays=0.63/0/0/0.02, dsn=4.4.3, status=deferred (delivery temporarily suspended: Host or domain name not found. Name service error for name=mail.pi.test.com type=A: Host not found, try again)
		#Nov  3 21:45:32 srvch011 postfix/smtp[18041]: 4CE321F4887: to=<viinil@vitive.com.br>, relay=smtpe1.eom[81.00.20.9]:25, delay=1.9, delays=0.06/0.01/0.68/1.2, dsn=2.0.0, status=sent (250 2.0.0 Ok: queued as 2C33E2382C8)
		#Nov 16 00:00:14 srvch011 postfix/smtp[7363]: 7AEB91F797D: to=<alessandra.bueno@mg.test.com>, relay=mail.mg.test.com[172.25.3.5]:25, delay=39, delays=35/1.1/0.04/2.7, dsn=5.7.1, status=bounced (host mail.mg.test.com[172.25.3.5] said: 550 5.7.1 Unable to relay for alessandra.bueno@mg.test.com (in reply to RCPT TO command))
		else if(preg_match("/(\w+\s+\d+\s+[0-9,:]+) (\w+) postfix.\w+\W\d+\W+(\w+): to=\<(.*)\>, relay=(.*), delay=([0-9,.]+), .* dsn=([0-9,.]+), status=(\w+) (.*)/",$line,$email)){
			$stm_queue[$day].= "insert or ignore into mail_status (info) values ('".$email[8]."');\n";
			$stm_queue[$day].= "insert or ignore into mail_to (from_id,too,status,status_info,relay,delay,dsn) values ((select id from mail_from where sid='".$email[3]."' and server='".$email[2]."'),'".strtolower($email[4])."',(select id from mail_status where info='".$email[8]."'),'".preg_replace("/(\<|\>|\s+|\'|\")/"," ",$email[9])."','".$email[5]."','".$email[6]."','".$email[7]."');\n";
			$stm_queue[$day].= "update or ignore mail_to set status=(select id from mail_status where info='".$email[8]."'), status_info='".preg_replace("/(\<|\>|\s+|\'|\")/"," ",$email[9])."', dsn='".$email[7]."', delay='".$email[6]."', relay='".$email[5]."', too='".strtolower($email[4])."' where from_id in (select id from mail_from where sid='".$email[3]."' and server='".$email[2]."');\n";
		}
		#Nov 13 01:48:44 srvch011 postfix/cleanup[16914]: D995B1F570B: message-id=<61.40.11745.10E3FBE4@ofertas6>
		else if(preg_match("/(\w+\s+\d+\s+[0-9,:]+) (\w+) postfix.cleanup\W\d+\W+(\w+): message-id=\<(.*)\>/",$line,$email)){
			$stm_queue[$day].="update mail_from set msgid='".$email[4]."' where sid='".$email[3]."';\n";
		}
		#Nov 14 02:40:05 srvch011 postfix/qmgr[46834]: BC5931F4F13: from=<ceag@mx.crmcom.br>, size=32727, nrcpt=1 (queue active)
		else if(preg_match("/(\w+\s+\d+\s+[0-9,:]+) (\w+) postfix.qmgr\W\d+\W+(\w+): from=\<(.*)\>\W+size=(\d+)/",$line,$email)){
			$stm_queue[$day].= "update mail_from set fromm='".strtolower($email[4])."', size='".$email[5]."' where sid='".$email[3]."';\n";
		}
		#Nov 13 00:09:07 srvch011 postfix/bounce[56376]: 9145C1F67F7: sender non-delivery notification: D5BD31F6865
		#else if(preg_match("/(\w+\s+\d+\s+[0-9,:]+) (\w+) postfix.bounce\W\d+\W+(\w+): sender non-delivery notification: (\w+)/",$line,$email)){
		#	$stm_queue[$day].= "update mail_queue set bounce='".$email[4]."' where sid='".$email[3]."';\n";
		#}
		#Nov 14 01:41:44 srvch011 postfix/smtpd[15259]: warning: 1EF3F1F573A: queue file size limit exceeded
	  else if(preg_match("/(\w+\s+\d+\s+[0-9,:]+) (\w+) postfix.smtpd\W\d+\W+warning: (\w+): queue file size limit exceeded/",$line,$email)){
	  		$stm_queue[$day].= "insert or ignore into mail_status (info) values ('".$email[8]."');\n";
			$stm_queue[$day].= "update mail_to set status=(select id from mail_status where info='reject'), status_info='queue file size limit exceeded' where from_id in (select id from mail_from where sid='".$email[3]."' and server='".$email[2]."');\n";
		}

		#Nov  9 02:14:57 srvch011 postfix/cleanup[6856]: 617A51F5AC5: warning: header Subject: Mapeamento de Processos from lxalpha.12b.com.br[66.109.29.225]; from=<apache@lxalpha.12b.com.br> to=<ritiele.faria@mail.test.com> proto=ESMTP helo=<lxalpha.12b.com.br>
		#Nov  8 09:31:50 srvch011 postfix/cleanup[11471]: 19C281F59C8: reject: header From: "Giuliana Flores - Parceiro do Grupo Virtual" <publicidade@parceiro-grupovirtual.com.br> from pm03-974.auinmeio.com.br[177.70.232.225]; from=<publicidade@parceiro-grupovirtual.com.br> to=<jorge.lustosa@mail.test.com> proto=ESMTP helo=<pm03-974.auinmeio.com.br>: 5.7.1 [SN007]
		#Nov 13 00:03:24 srvch011 postfix/cleanup[4192]: 8A5B31F52D2: reject: body http://platform.roastcrack.info/mj0ie6p-48qtiyq from move2.igloojack.info[173.239.63.16]; from=<ljmd6u8lrxke4@move2.igloojack.info> to=<edileva@aasdf..br> proto=SMTP helo=<move2.igloojack.info>: 5.7.1 [BD040]
		#Nov 14 01:41:35 srvch011 postfix/cleanup[58446]: 1EF3F1F573A: warning: header Subject: =?windows-1252?Q?IMOVEL_Voc=EA_=E9_um_Cliente_especial_da_=93CENTURY21=22?=??=?windows-1252?Q?Veja_o_que_tenho_para_voc=EA?= from mail-yw0-f51.google.com[209.85.213.51]; from=<sergioalexandre6308@gmail.com> to=<sinza@tr.br> proto=ESMTP helo=<mail-yw0-f51.google.com>
		else if(preg_match("/(\w+\s+\d+\s+[0-9,:]+) (\w+) postfix.cleanup\W\d+\W+(\w+): (\w+): (.*) from ([a-z,A-Z,0-9,.,-]+)\W([0-9,.]+)\W+from=\<(.*)\> to=\<(.*)\>.*helo=\W([a-z,A-Z,0-9,.,-]+)(.*)/",$line,$email)){
			$status['date']=$email[1];
			$status['server']=$email[2];
			$status['sid']=$email[3];
			$status['remote_hostname']=$email[6];
			$status['remote_ip']=$email[7];
			$status['from']=$email[8];
			$status['to']=$email[9];
			$status['helo']=$email[10];
			$status['status']=$email[4];
			$stm_queue[$day].= "insert or ignore into mail_status (info) values ('".$email[4]."');\n";
			if ($email[4] =="warning"){
				if (${$status['sid']}=='hold'){
					$status['status']='hold';
					}
				else{
					$status['status']='incoming';
					$stm_queue[$day].= "insert or ignore into mail_status (info) values ('".$status['status']."');\n";
					}
				#print "$line\n";
				$status['status_info']=preg_replace("/(\<|\>|\s+|\'|\")/"," ",$email[11]);
				$status['subject']=preg_replace("/header Subject: /","",$email[5]);
				$status['subject']=preg_replace("/(\<|\>|\s+|\'|\")/"," ",$status['subject']);
				$stm_queue[$day].="update mail_from set subject='".$status['subject']."', fromm='".strtolower($status['from'])."',helo='".$status['helo']."' where sid='".$status['sid']."';\n";
				$stm_queue[$day].="insert or ignore into mail_to (from_id,too,status,status_info) VALUES ((select id from mail_from where sid='".$email[3]."' and server='".$email[2]."'),'".strtolower($status['to'])."',(select id from mail_status where info='".$status['status']."'),'".$status['status_info']."');\n";
				$stm_queue[$day].="update or ignore mail_to set status=(select id from mail_status where info='".$status['status']."'), status_info='".$status['status_info']."', too='".strtolower($status['to'])."' where from_id in (select id from mail_from where sid='".$status['sid']."' and server='".$email[2]."');\n";
				}
			else{
				${$status['sid']}=$status['status'];
				$stm_queue[$day].="update mail_from set fromm='".strtolower($status['from'])."',helo='".$status['helo']."' where sid='".$status['sid']."';\n";
				$status['status_info']=preg_replace("/(\<|\>|\s+|\'|\")/"," ",$email[5].$email[11]);
				$stm_queue[$day].="insert or ignore into mail_to (from_id,too,status,status_info) VALUES ((select id from mail_from where sid='".$email[3]."' and server='".$email[2]."'),'".strtolower($status['to'])."',(select id from mail_status where info='".$email[4]."'),'".$status['status_info']."');\n";
				$stm_queue[$day].="update or ignore mail_to set status=(select id from mail_status where info='".$email[4]."'), status_info='".$status['status_info']."', too='".strtolower($status['to'])."' where from_id in (select id from mail_from where sid='".$status['sid']."' and server='".$email[2]."');\n";
								}
			}
		#Nov  9 02:14:34 srvch011 postfix/smtpd[38129]: NOQUEUE: reject: RCPT from unknown[201.36.0.7]: 450 4.7.1 Client host rejected: cannot find your hostname, [201.36.98.7]; from=<maladireta@esadcos.com.br> to=<sexec.09vara@go.domain.test.com> proto=ESMTP helo=<capri0.wb.com.br>
		else if(preg_match("/(\w+\s+\d+\s+[0-9,:]+) (\w+) postfix.smtpd\W\d+\W+NOQUEUE:\s+(\w+): (.*); from=\<(.*)\> to=\<(.*)\>.*helo=\<(.*)\>/",$line,$email)){
			$status['date']=$email[1];
			$status['server']=$email[2];
			$status['status']=$email[3];
			$status['status_info']=$email[4];
			$status['from']=$email[5];
			$status['to']=$email[6];
			$status['helo']=$email[7];
			$values="'".$status['date']."','".$status['status']."','".$status['status_info']."','".strtolower($status['from'])."','".strtolower($status['to'])."','".$status['helo']."','".$status['server']."'";
			$stm_noqueue[$day].='insert or ignore into mail_noqueue(date,status,status_info,fromm,too,helo,server) values ('.$values.');'."\n";
		}
		if ($total_lines%1500 == 0){
			#save log in database
			write_db($stm_noqueue,"noqueue",$days);
			write_db($stm_queue,"from",$days);
			foreach ($days as $d){
				$stm_noqueue[$d]="BEGIN;\n";
				$stm_queue[$d]="BEGIN;\n";
				}
			}
	if ($total_lines%1500 == 0)
		print "$line\n";
		}
	#save log in database
	write_db($stm_noqueue,"noqueue",$days);
	write_db($stm_queue,"from",$days);
	foreach ($days as $d){
		$stm_noqueue[$d]="BEGIN;\n";
		$stm_queue[$d]="BEGIN;\n";
		}
	}

}

function write_db($stm, $table, $days) {
	global $postfix_dir, $config, $g;
	conf_mount_rw();
	$do_sync = array();
	print "writing to database...";
	foreach ($days as $day) {
		if ((strlen($stm[$day]) > 10) && (is_array($config['installedpackages']['postfixsync']['config']))) {
			foreach ($config['installedpackages']['postfixsync']['config'] as $rs) {
				foreach($rs['row'] as $sh) {
					$sync_to_ip = $sh['ipaddress'];
					$sync_type = $sh['sync_type'];
					$password = $sh['password'];
					$sql_file = '/var/db/postfix/' . $sync_to_ip . '.sql';
					${$sync_to_ip} = "";
					if (file_exists($sql_file)) {
						${$sync_to_ip} = file_get_contents($sql_file);
					}
					if ($sync_to_ip && $sync_type == "share") {
						${$sync_to_ip} .= serialize(array('day' => $day, 'sql' => base64_encode(gzcompress($stm[$day] . "COMMIT;", 9)))) . "\n";
						if (!in_array($sync_to_ip, $do_sync)) {
							$do_sync[] = $sync_to_ip;
						}
					}
				}
			}
			/* Write local db file */
			create_db($day . ".db");
			if ($debug) {
				print "writing to local db $day...";
			}
			$dbhandle = sqlite_open($postfix_dir.$day.".db", 0666, $error);
			if (!$dbhandle) {
				die ($error);
			}
			//file_put_contents("/tmp/" . $key . '-' . $update['day'] . ".sql", gzuncompress(base64_decode($update['sql'])), LOCK_EX);
			$ok = sqlite_exec($dbhandle, $stm[$day] . "COMMIT;", $error);
			if (!$ok) {
				print ("Cannot execute query. $error\n" . $stm[$day] . "COMMIT;\n");
			} elseif ($debug) {
				print "ok\n";
			}
			sqlite_close($dbhandle);
		}
	}
	/* Write updated sql files */
	if (count($do_sync) > 0 ) {
		foreach ($do_sync as $ip) {
			file_put_contents('/var/db/postfix/' . $ip . '.sql', ${$ip}, LOCK_EX);
		}
	}
	conf_mount_ro();
	/* Write local file */
}

function create_db($postfix_db){
	global $postfix_dir,$postfix_arg;
	if (! is_dir($postfix_dir))
		mkdir($postfix_dir,0775);
	$new_db=(file_exists($postfix_dir.$postfix_db)?1:0);
$stm = <<<EOF
	CREATE TABLE "mail_from"(
	"id" INTEGER PRIMARY KEY,
	"sid" VARCHAR(11) NOT NULL,
    "client" TEXT NOT NULL,
    "msgid" TEXT,
	"fromm" TEXT,
    "size" INTEGER,
    "subject" TEXT,
    "date" TEXT NOT NULL,
    "server" TEXT,
    "helo" TEXT
);
	CREATE TABLE "mail_to"(
	"id" INTEGER PRIMARY KEY,
	"from_id" INTEGER NOT NULL,
    "too" TEXT,
    "status" INTEGER,
    "status_info" TEXT,
    "smtp" TEXT,
    "delay" TEXT,
    "relay" TEXT,
    "dsn" TEXT,
    "server" TEXT,
    "bounce" TEXT,
    FOREIGN KEY (status) REFERENCES mail_status(id),
    FOREIGN KEY (from_id) REFERENCES mail_from(id)
);


CREATE TABLE "mail_status"(
	"id" INTEGER PRIMARY KEY,
    "info" varchar(35) NOT NULL
);

CREATE TABLE "mail_noqueue"(
	"id" INTEGER PRIMARY KEY,
   	"date" TEXT NOT NULL,
   	"server" TEXT NOT NULL,
   	"status" TEXT NOT NULL,
   	"status_info" INTEGER NOT NULL,
   	"fromm" TEXT NOT NULL,
   	"too" TEXT NOT NULL,
   	"helo" TEXT NOT NULL
);

CREATE TABLE "db_version"(
	"value" varchar(10),
	"info" TEXT
);

insert or ignore into db_version ('value') VALUES ('2.3.1');

CREATE INDEX "noqueue_unique" on mail_noqueue (date ASC, fromm ASC, too ASC);
CREATE INDEX "noqueue_helo" on mail_noqueue (helo ASC);
CREATE INDEX "noqueue_too" on mail_noqueue (too ASC);
CREATE INDEX "noqueue_fromm" on mail_noqueue (fromm ASC);
CREATE INDEX "noqueue_info" on mail_noqueue (status_info ASC);
CREATE INDEX "noqueue_status" on mail_noqueue (status ASC);
CREATE INDEX "noqueue_server" on mail_noqueue (server ASC);
CREATE INDEX "noqueue_date" on mail_noqueue (date ASC);

CREATE UNIQUE INDEX "status_info" on mail_status (info ASC);

CREATE UNIQUE INDEX "from_sid_server" on mail_from (sid ASC,server ASC);
CREATE INDEX "from_client" on mail_from (client ASC);
CREATE INDEX "from_helo" on mail_from (helo ASC);
CREATE INDEX "from_server" on mail_from (server ASC);
CREATE INDEX "from_subject" on mail_from (subject ASC);
CREATE INDEX "from_msgid" on mail_from (msgid ASC);
CREATE INDEX "from_fromm" on mail_from (fromm ASC);
CREATE INDEX "from_date" on mail_from (date ASC);

CREATE UNIQUE INDEX "mail_to_unique" on mail_to (from_id ASC, too ASC);
CREATE INDEX "to_bounce" on mail_to (bounce ASC);
CREATE INDEX "to_relay" on mail_to (relay ASC);
CREATE INDEX "to_smtp" on mail_to (smtp ASC);
CREATE INDEX "to_info" on mail_to (status_info ASC);
CREATE INDEX "to_status" on mail_to (status ASC);
CREATE INDEX "to_too" on mail_to (too ASC);

EOF;
#test file version
print "checking". $postfix_dir.$postfix_db."\n";
$dbhandle = sqlite_open($postfix_dir.$postfix_db, 0666, $error);
if (!$dbhandle) die ($error);
$ok = sqlite_exec($dbhandle,"select value from db_version", $error);
sqlite_close($dbhandle);
if (!$ok){
	print "delete previous table version\n";
	if (file_exists($postfix_dir.$postfix_db))
		unlink($postfix_dir.$postfix_db);
	$new_db=0;
}
if ($new_db==0){
	$dbhandle = sqlite_open($postfix_dir.$postfix_db, 0666, $error);
	$ok = sqlite_exec($dbhandle, $stm, $error);
	if (!$ok)
		print ("Cannot execute query. $error\n");
	$ok = sqlite_exec($dbhandle, $stm2, $error);
	if (!$ok)
		print ("Cannot execute query. $error\n");
	sqlite_close($dbhandle);
	}
}

$postfix_dir="/var/db/postfix/";
$curr_time = time();
#console script call
if ($argv[1]!=""){
switch ($argv[1]){
	case "01min":
		$postfix_arg=array(	'grep' => array(date("H:i",strtotime('-1 min',$curr_time))),
							'time' => '-1 min');
		break;
	case "10min":
		$postfix_arg=array(	'grep' => array(substr(date("H:i",strtotime('-10 min',$curr_time)),0,-1)),
							'time' => '-10 min');
		break;
	case "01hour":
		$postfix_arg=array(	'grep' => array(date("H:",strtotime('-01 hour',$curr_time))),
							'time' => '-01 hour');
		break;
	case "04hour":
		$postfix_arg=array(	'grep' => array(date("H:",strtotime('-04 hour',$curr_time)),date("H:",strtotime('-03 hour',$curr_time)),
											date("H:",strtotime('-02 hour',$curr_time)),date("H:",strtotime('-01 hour',$curr_time))),
							'time' => '-04 hour');
		break;
	case "24hours":
		$postfix_arg=array(	'grep' => array('00:','01:','02:','03:','04:','05:','06:','07:','08:','09:','10:','11:',
											'12:','13:','14:','15:','16:','17:','18:','19:','20:','21:','22:','23:'),
							'time' => '-01 day');
		break;
	case "02days":
		$postfix_arg=array(	'grep' => array('00:','01:','02:','03:','04:','05:','06:','07:','08:','09:','10:','11:',
											'12:','13:','14:','15:','16:','17:','18:','19:','20:','21:','22:','23:'),
							'time' => '-02 day');
		break;
	case "03days":
		$postfix_arg=array(	'grep' => array('00:','01:','02:','03:','04:','05:','06:','07:','08:','09:','10:','11:',
											'12:','13:','14:','15:','16:','17:','18:','19:','20:','21:','22:','23:'),
							'time' => '-03 day');
		break;

		default:
		die ("invalid parameters\n");
}
# get remote log from remote server
get_remote_log();
# get local log from logfile
grep_log();
}

#http client call
if ($_REQUEST['files']!= ""){
	#do search
	if($_REQUEST['queue']=="QUEUE"){
		$stm="select * from mail_from, mail_to ,mail_status where mail_from.id=mail_to.from_id and mail_to.status=mail_status.id ";
		$last_next=" and ";
	}
	else{
		$stm="select * from mail_noqueue";
		$last_next=" where ";
	}
	$limit_prefix=(preg_match("/\d+/",$_REQUEST['limit'])?"limit ":"");
	$limit=(preg_match("/\d+/",$_REQUEST['limit'])?$_REQUEST['limit']:"");
	$files= explode(",", $_REQUEST['files']);
	$stm_fetch=array();
	$total_result=0;
	foreach ($files as $postfix_db)
		if (file_exists($postfix_dir.'/'.$postfix_db)){
			$dbhandle = sqlite_open($postfix_dir.'/'.$postfix_db, 0666, $error);
			if ($_REQUEST['from']!= ""){
				$next=($last_next==" and "?" and ":" where ");
				$last_next=" and ";
				if (preg_match('/\*/',$_REQUEST['from']))
					$stm .=$next."fromm like '".preg_replace('/\*/','%',$_REQUEST['from'])."'";
				else
					$stm .=$next."fromm in('".$_REQUEST['from']."')";
				}
			if ($_REQUEST['to']!= ""){
				$next=($last_next==" and "?" and ":" where ");
				$last_next=" and ";
				if (preg_match('/\*/',$_REQUEST['to']))
					$stm .=$next."too like '".preg_replace('/\*/','%',$_REQUEST['to'])."'";
				else
					$stm .=$next."too in('".$_REQUEST['to']."')";
				}
			if ($_REQUEST['sid']!= "" && $_REQUEST['queue']=="QUEUE"){
				$next=($last_next==" and "?" and ":" where ");
				$last_next=" and ";
				$stm .=$next."sid in('".$_REQUEST['sid']."')";
				}
			if ($_REQUEST['relay']!= "" && $_REQUEST['queue']=="QUEUE"){
				$next=($last_next==" and "?" and ":" where ");
				$last_next=" and ";
				if (preg_match('/\*/',$_REQUEST['subject']))
					$stm .=$next."relay like '".preg_replace('/\*/','%',$_REQUEST['relay'])."'";
				else
					$stm .=$next."relay = '".$_REQUEST['relay']."'";
				}
			if ($_REQUEST['subject']!= "" && $_REQUEST['queue']=="QUEUE"){
				$next=($last_next==" and "?" and ":" where ");
				$last_next=" and ";
				if (preg_match('/\*/',$_REQUEST['subject']))
					$stm .=$next."subject like '".preg_replace('/\*/','%',$_REQUEST['subject'])."'";
				else
					$stm .=$next."subject = '".$_REQUEST['subject']."'";
				}
			if ($_REQUEST['msgid']!= "" && $_REQUEST['queue']=="QUEUE"){
				$next=($last_next==" and "?" and ":" where ");
				$last_next=" and ";
				if (preg_match('/\*/',$_REQUEST['msgid']))
					$stm .=$next."msgid like '".preg_replace('/\*/','%',$_REQUEST['msgid'])."'";
				else
					$stm .=$next."msgid = '".$_REQUEST['msgid']."'";
				}
			if ($_REQUEST['server']!= "" ){
				$next=($last_next==" and "?" and ":" where ");
				$last_next=" and ";
				if( $_REQUEST['queue']=="QUEUE")
					$stm .=$next."mail_from.server = '".$_REQUEST['server']."'";
				else
					$stm .=$next."server = '".$_REQUEST['server']."'";
				}

		if ($_REQUEST['status']!= ""){
				$next=($last_next==" and "?" and ":" where ");
				$last_next=" and ";
				$stm .=$next."mail_status.info = '".$_REQUEST['status']."'";
				}
					#print "<pre>".$stm;
				#$stm = "select * from mail_to,mail_status where mail_to.status=mail_status.id";
				$result = sqlite_query($dbhandle, $stm." order by date desc $limit_prefix $limit ");
				#$result = sqlite_query($dbhandle, $stm."  $limit_prefix $limit ");
			if (preg_match("/\d+/",$_REQUEST['limit'])){
				for ($i = 1; $i <= $limit; $i++) {
					$row = sqlite_fetch_array($result, SQLITE_ASSOC);
					 if (is_array($row))
						$stm_fetch[]=$row;
					}
			}
			else{
				$stm_fetch = sqlite_fetch_all($result, SQLITE_ASSOC);
			}
			sqlite_close($dbhandle);
	}
	$fields= explode(",", $_REQUEST['fields']);
	if ($_REQUEST['sbutton']=='export'){
		print '<table class="tabcont" width="100%" border="0" cellpadding="8" cellspacing="0">';
		print '<tr><td colspan="'.count($fields).'" valign="top" class="listtopic">'.gettext("Search Results").'</td></tr>';
		print '<tr>';
		$header="";
		foreach ($stm_fetch as $mail){
			foreach ($mail as $key => $data){
				if (!preg_match("/$key/",$header))
					$header .= $key.",";
				$export.=preg_replace('/,/',"",$mail[$key]).",";
				}
			$export.= "\n";
		}
		print '<td class="tabcont"><textarea id="varnishlogs" rows="50" cols="100%">';
		print "This export is in csv format, paste it without this line on any software that handles csv files.\n\n".$header."\n".$export;
		print "</textarea></td></tr></table>";
		}
	else{
	if ($_REQUEST['queue']=="NOQUEUE"){
		print '<table class="tabcont" width="100%" border="0" cellpadding="8" cellspacing="0">';
		print '<tr><td colspan="'.count($fields).'" valign="top" class="listtopic">'.gettext("Search Results").'</td></tr>';
		print '<tr>';
		if(in_array("date",$fields))
			print '<td class="listlr"><strong>date</strong></td>';
		if(in_array("server",$fields))
			print '<td class="listlr"><strong>server</strong></td>';
		if(in_array("from",$fields))
			print '<td class="listlr"><strong>From</strong></td>';
		if(in_array("to",$fields))
			print '<td class="listlr"><strong>to</strong></td>';
		if(in_array("helo",$fields))
			print '<td class="listlr"><strong>Helo</strong></td>';
		if(in_array("status",$fields))
			print '<td class="listlr"><strong>Status</strong></td>';
		if(in_array("status_info",$fields))
			print '<td class="listlr"><strong>Status Info</strong></td>';
		print '</tr>';
		foreach ($stm_fetch as $mail){
			print '<tr>';
		if(in_array("date",$fields))
			print '<td class="listlr">'.$mail['date'].'</td>';
		if(in_array("server",$fields))
			print '<td class="listlr">'.$mail['server'].'</td>';
		if(in_array("from",$fields))
			print '<td class="listlr">'.$mail['fromm'].'</td>';
		if(in_array("to",$fields))
			print '<td class="listlr">'.$mail['too'].'</td>';
		if(in_array("helo",$fields))
			print '<td class="listlr">'.$mail['helo'].'</td>';
		if(in_array("status",$fields))
			print '<td class="listlr">'.$mail['status'].'</td>';
		if(in_array("status_info",$fields))
			print '<td class="listlr">'.$mail['status_info'].'</td>';
			print '</tr>';
			$total_result++;
		}
	}
  else{
  		print '<table class="tabcont" width="100%" border="0" cellpadding="8" cellspacing="0">';
		print '<tr><td colspan="'.count($fields).'" valign="top" class="listtopic">'.gettext("Search Results").'</td></tr>';
		print '<tr>';
		if(in_array("date",$fields))
			print '<td class="listlr" ><strong>Date</strong></td>';
		if(in_array("server",$fields))
			print '<td class="listlr" ><strong>Server</strong></td>';
		if(in_array("from",$fields))
			print '<td class="listlr" ><strong>From</strong></td>';
		if(in_array("to",$fields))
			print '<td class="listlr" ><strong>to</strong></td>';
		if(in_array("subject",$fields))
			print '<td class="listlr" ><strong>Subject</strong></td>';
		if(in_array("delay",$fields))
			print '<td class="listlr" ><strong>Delay</strong></td>';
		if(in_array("status",$fields))
			print '<td class="listlr" ><strong>Status</strong></td>';
		if(in_array("status_info",$fields))
			print '<td class="listlr" ><strong>Status Info</strong></td>';
		if(in_array("size",$fields))
			print '<td class="listlr" ><strong>Size</strong></td>';
		if(in_array("helo",$fields))
			print '<td class="listlr" ><strong>Helo</strong></td>';
		if(in_array("sid",$fields))
			print '<td class="listlr" ><strong>SID</strong></td>';
		if(in_array("msgid",$fields))
			print '<td class="listlr" ><strong>MSGID</strong></td>';
		if(in_array("bounce",$fields))
			print '<td class="listlr" ><strong>Bounce</strong></td>';
		if(in_array("relay",$fields))
			print '<td class="listlr" ><strong>Relay</strong></td>';
		print '</tr>';
		foreach ($stm_fetch as $mail){
			if(in_array("date",$fields))
				print '<td class="listlr">'.$mail['mail_from.date'].'</td>';
			if(in_array("server",$fields))
				print '<td class="listlr">'.$mail['mail_from.server'].'</td>';
			if(in_array("from",$fields))
				print '<td class="listlr">'.$mail['mail_from.fromm'].'</td>';
			if(in_array("to",$fields))
				print '<td class="listlr">'.$mail['mail_to.too'].'</td>';
			if(in_array("subject",$fields))
				print '<td class="listlr">'.$mail['mail_from.subject'].'</td>';
			if(in_array("delay",$fields))
				print '<td class="listlr">'.$mail['mail_to.delay'].'</td>';
			if(in_array("status",$fields))
				print '<td class="listlr">'.$mail['mail_status.info'].'</td>';
			if(in_array("status_info",$fields))
				print '<td class="listlr">'.$mail['mail_to.status_info'].'</td>';
			if(in_array("size",$fields))
				print '<td class="listlr">'.$mail['mail_from.size'].'</td>';
			if(in_array("helo",$fields))
				print '<td class="listlr">'.$mail['mail_from.helo'].'</td>';
			if(in_array("sid",$fields))
				print '<td class="listlr">'.$mail['mail_from.sid'].'</td>';
			if(in_array("msgid",$fields))
				print '<td class="listlr">'.$mail['mail_from.msgid'].'</td>';
			if(in_array("bounce",$fields))
				print '<td class="listlr">'.$mail['mail_to.bounce'].'</td>';
			if(in_array("relay",$fields))
				print '<td class="listlr">'.$mail['mail_to.relay'].'</td>';
			print '</tr>';
			$total_result++;
		}
  }
	print '<tr>';
	print '<td ><strong>Total:</strong></td>';
	print '<td ><strong>'.$total_result.'</strong></td>';
	print '</tr>';
	print '</table>';
	}
}
?>
