<?php
/*
 * acme_sh.inc
 * 
 * part of pfSense (https://www.pfsense.org/)
 * Copyright (c) 2016 PiBa-NL
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

namespace pfsense_pkg\acme;
	
class acme_sh {

	private $accountconfig;
	private $path_account;
	private $name;
	private $debug = true;
	public $dnssleep;
	
	private function execacmesh($command, $envvariables = null) {
		$command = "/usr/local/pkg/acme/acme.sh " . $command;
		if ($this->debug) {
			echo "<br/>\n".$command."<br/>\n";
		}
		// add to cron environment path: /usr/local/bin/
		$env = array();
		$env['path'] = "/etc:/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin/";
		$env['PATH'] = "/etc:/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin/";
		if (is_array($envvariables)) {
			$env = array_merge($env, $envvariables);
		}
		if ($this->debug) {
			print_r($env);
		}
		$descriptorspec = array(
		   0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
		   1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
		   2 => array("pipe", "w")   // stderr
		);
		$process = proc_open($command, $descriptorspec, $pipes, null, $env);
		if (is_resource($process)) {
			if ($this->debug) {
				echo stream_get_contents($pipes[1]);
				echo stream_get_contents($pipes[2]);
			}
			fclose($pipes[0]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			$return_value = proc_close($process);
		}
		return $return_value;
	}
	
	function __construct($name, $ca) {
		$this->name = $name;
		$this->init($ca, $name);
	}
	
	function init($ca, $name) {
		$conf = "API='{$ca}'\n";
		$cahost = parse_url($ca, PHP_URL_HOST);
		$acmeconf = "/tmp/acme/{$name}/";
		$this->acmeconf = $acmeconf;
		$this->path_account = "$acmeconf/ca/$cahost";
		safe_mkdir($this->path_account);
		$this->accountconfig = "{$this->acmeconf}accountconf.conf";
		file_put_contents("{$this->accountconfig}", $conf);
	}
	
	function generateAccountKey() {
		unlink_if_exists("{$this->path_account}/account.key");
		$this->debug = false;
		$this->execacmesh(""
				. " --home " . escapeshellarg($this->acmeconf)
				. " --createAccountKey"
				. " --accountkeylength 4096"
				. " --accountconf " . escapeshellarg($this->accountconfig)
				);
		$privateKey = file_get_contents("{$this->path_account}/account.key");
		return $privateKey;
	}
	
	function registeraccount($key) {
		file_put_contents("{$this->path_account}/account.key", $key);
		$result = $this->execacmesh(""
				. " --home " . escapeshellarg($this->acmeconf)
				. " --registeraccount"
				. " --accountconf " . escapeshellarg($this->accountconfig)
				. " --log-level 3"
				. " --log " . escapeshellarg($this->acmeconf."acme_issuecert.log"));
		return $result == 0;
	}
	
	function generateDomainKey($domain, $keylength) {
		global $a_keylength;
		$pathadd = "";
		if ($a_keylength[$keylength]['ecc']) {
			$pathadd = "_ecc";
		}
		$certpath = "{$this->acmeconf}{$domain}{$pathadd}";
		safe_mkdir($certpath);
		
		unlink_if_exists("{$certpath}/{$domain}.key");
		$this->execacmesh(""
				. " --home " . escapeshellarg($this->acmeconf)
				. " --accountconf " . escapeshellarg($this->accountconfig)
				. " --createDomainKey -d " . escapeshellarg($domain)
				. " --keylength " . escapeshellarg($keylength)
				. " --log-level 3"
				. " --log " . escapeshellarg($this->acmeconf."acme_createdomainkey.log")
				);
		$privateKey = file_get_contents("{$certpath}/{$domain}.key");
		return $privateKey;
	}
	
	function signCertificate($action, $accountkey, $certificatepsk, $domainstosign, $api = null, $envvariables = null, $extras = null) {
		// $action = issue / renew
		file_put_contents("{$this->path_account}/account.key", base64_decode($accountkey));
		$cmdparameters = "";
		if ($api && substr($api, 0, 4 ) === "dns_") {
			if ($api == "dns_manual") {
				$cmdapi = "";
			} else {
				$cmdapi = escapeshellarg($api);
			}
			$cmdparameters = " --dns {$cmdapi} ";
		} else {
			$cmdparameters = " --webroot pfSenseacme";
		}
		if (is_numericint($this->dnssleep)) {
			$cmdparameters .= " --dnssleep " . escapeshellarg($this->dnssleep);
		}
		
		$Le_Domain = $domainstosign[0];
		$certpath = "{$this->acmeconf}{$Le_Domain}/";
		$CERT_KEY_PATH = "{$certpath}{$Le_Domain}.key";
		$CERT_PATH = "{$certpath}{$Le_Domain}.cer";
		$CA_CERT_PATH = "{$certpath}ca.cer";
		$CERT_FULLCHAIN_PATH = "{$certpath}fullchain.cer";
		$reloadcmd = "/usr/local/pkg/acme/acme_command.sh"
				. " importcert \"{$this->name}\""
				. " \"$Le_Domain\""
				. " \"$CERT_KEY_PATH\""
				. " \"$CERT_PATH\""
				. " \"$CA_CERT_PATH\""
				. " \"$CERT_FULLCHAIN_PATH\"";
		$reloadfile = "{$this->acmeconf}reloadcmd.sh";
		file_put_contents($reloadfile, $reloadcmd);
		chmod($reloadfile, 0755);

		if ($api == "dns_nsupdate") {
			/* Prefix to use as a base for server/key/private files */
			$nsupdatefileprefix = "{$certpath}nsupdate";
			foreach (array_keys($extras) as $nsupdatedomain) {
				file_put_contents("{$nsupdatefileprefix}_acme-challenge.{$nsupdatedomain}.server", $extras[$nsupdatedomain]['NSUPDATE_SERVER']);
				$envvariables['NSUPDATE_SERVER'] = $nsupdatefileprefix;
				$nsupdatekey = base64_decode($extras[$nsupdatedomain]['NSUPDATE_KEY']);

				/* Use the same .key/.private format as RFC2136 Dynamic DNS (See /etc/inc/services.inc (services_dnsupdate_process() */
				$key_type = $extras[$nsupdatedomain]['NSUPDATE_KEYTYPE'];
				switch ($extras[$nsupdatedomain]['NSUPDATE_KEYTYPE']) {
					case "zone":
						$flags = 257;
						$proto = 3;
						break;
					case "user":
						$flags = 0;
						$proto = 2;
						break;
					case "host":
					default:
						$flags = 513;
						$proto = 3;
				}
				$key_algo = empty($extras[$nsupdatedomain]['NSUPDATE_KEYALGO']) ? '157' : $extras[$nsupdatedomain]['NSUPDATE_KEYALGO'];
				file_put_contents("{$nsupdatefileprefix}_acme-challenge.{$nsupdatedomain}.key", "_acme-challenge.{$nsupdatedomain} IN KEY {$flags} {$proto} {$key_algo} {$nsupdatekey}\n");
				$privkey = <<<EOD
Private-key-format: v1.3
Algorithm: {$key_algo}
Key: {$nsupdatekey}

EOD;
				file_put_contents("{$nsupdatefileprefix}_acme-challenge.{$nsupdatedomain}.private", $privkey);
			}
			$envvariables['NSUPDATE_KEY'] = $nsupdatefileprefix;
		}

		$hookcontent_httpapi  = <<<EOF
pfSenseacme_add() {
  /usr/local/pkg/acme/acme_command.sh "deploykey" "{$this->name}" "\$1" "\$2" "\$3"
}

pfSenseacme_rm() {
  /usr/local/pkg/acme/acme_command.sh "removekey" "{$this->name}" "\$1" "\$2"
}
EOF;
		safe_mkdir("{$this->acmeconf}httpapi");
		$hookfile_httpapi = "{$this->acmeconf}httpapi/pfSenseacme.sh";
		file_put_contents($hookfile_httpapi, $hookcontent_httpapi);
		chmod($hookfile_httpapi, 0755);
		
		$certpath = "{$this->acmeconf}{$domainstosign[0]}";
		safe_mkdir($certpath);
		file_put_contents("{$certpath}/{$domainstosign[0]}.key", $certificatepsk);
		$domainstr = "";
		foreach($domainstosign as $domain) {
			$domainstr .= " -d " . escapeshellarg($domain);
		}
		$this->execacmesh(""
			. " --{$action} {$domainstr}"
			. " --home " . escapeshellarg($this->acmeconf)
			. " --accountconf " . escapeshellarg($this->accountconfig)
			. " --force"
			. " --reloadCmd " . escapeshellarg("{$this->acmeconf}reloadcmd.sh")
			. $cmdparameters
			. " --log-level 3"
			. " --log " . escapeshellarg("{$this->acmeconf}acme_issuecert.log")
			//. " > {$this->acmeconf}issue.log 2>&1"
			, $envvariables);
		$cer = "{$certpath}/{$domainstosign[0]}.cer";
		if (file_exists($cer)) {
			return $cer;
		}
		return false;
	}
}
