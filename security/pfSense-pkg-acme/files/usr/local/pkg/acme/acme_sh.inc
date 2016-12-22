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

	function logexec($command, &$out = null) {
		echo "<br/>\n".$command;
		// add to cron environment path: /usr/local/bin/
		$env = array();
		$env['path'] = "/etc:/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin/";
		$descriptorspec = array(
		   0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
		   1 => array("pipe", "w")  // stdout is a pipe that the child will write to
		);
		$process = proc_open($command, $descriptorspec, $pipes, null, $env);
		if (is_resource($process)) {
			echo stream_get_contents($pipes[1]);
			fclose($pipes[0]);
			fclose($pipes[1]);
			$return_value = proc_close($process);
		}
		return $return_value;
	}
	
class acme_sh {

	private $accountconfig;
	private $path_account;
	private $name;
	
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
		exec("/usr/local/pkg/acme/acme.sh --home {$this->acmeconf} --createAccountKey --accountkeylength 4096 --accountconf {$this->accountconfig}");
		$privateKey = file_get_contents("{$this->path_account}/account.key");
		return $privateKey;
	}
	
	function registeraccount($key) {
		file_put_contents("{$this->path_account}/account.key", $key);
		exec("/usr/local/pkg/acme/acme.sh --home {$this->acmeconf} --registeraccount --accountconf {$this->accountconfig} 2>&1", $output, $err);
		return $err == 0;
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
		logexec("/usr/local/pkg/acme/acme.sh --home {$this->acmeconf} --accountconf {$this->accountconfig} --createDomainKey -d $domain --keylength $keylength");
		$privateKey = file_get_contents("{$certpath}/{$domain}.key");
		return $privateKey;
	}
	
	function signCertificate($accountkey, $certificatepsk, $domainstosign) {
		$Le_Domain = $domainstosign[0];
		$certpath = "{$this->acmeconf}{$Le_Domain}/";
		$CERT_KEY_PATH = "{$certpath}{$Le_Domain}.key";
		$CERT_PATH = "{$certpath}{$Le_Domain}.cer";
		$CA_CERT_PATH = "{$certpath}ca.cer";
		$CERT_FULLCHAIN_PATH = "{$certpath}fullchain.cer";
		$reloadcmd = "/usr/local/pkg/acme/acme_command.sh \"importcert\" \"{$this->name}\" \"$Le_Domain\" \"$CERT_KEY_PATH\" \"$CERT_PATH\" \"$CA_CERT_PATH\" \"$CERT_FULLCHAIN_PATH\"";
		$reloadfile = "{$this->acmeconf}reloadcmd.sh";
		file_put_contents($reloadfile, $reloadcmd);
		chmod($reloadfile, 755);

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
		chmod($hookfile_httpapi, 755);
		
		$certpath = "{$this->acmeconf}{$domainstosign[0]}";
		safe_mkdir($certpath);
		file_put_contents("{$certpath}/{$domainstosign[0]}.key", $certificatepsk);
		$domainstr = "";
		foreach($domainstosign as $domain) {
			$domainstr .= " -d {$domain}";
		}
		logexec("/usr/local/pkg/acme/acme.sh"
			. " --issue {$domainstr}"
			. " --home {$this->acmeconf}"
			. " --accountconf {$this->accountconfig}"
			. " --webroot pfSenseacme"
			. " --force"
			. " --reloadCmd {$this->acmeconf}reloadcmd.sh"
			. " --log-level 1"
			. " --log {$this->acmeconf}acme_issuecert.log"
			. " > {$this->acmeconf}issue.log 2>&1", $output, $err);
		$cer = "{$certpath}/{$domainstosign[0]}.cer";
		if (file_exists($cer)) {
			return $cer;
		}
		return false;
	}
}
