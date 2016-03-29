<?php
/*
    acme.inc
	Copyright (C) 2016 PiBa-NL
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

namespace pfsense_pkg\acme;

/* include all configuration functions */
require_once("functions.inc");
require_once("pkg-utils.inc");
require_once("notices.inc");
require_once("util.inc");
require_once("acme_utils.inc");
require_once("lescript.inc");

$d_acmeconfdirty_path = $g['varrun_path'] . "/acme.conf.dirty";
#region global array item definitions..
// <editor-fold>

$a_enabledisable = array();
$a_enabledisable['enable'] = array('name' => 'Enabled');
$a_enabledisable['disable'] = array('name' => 'Disabled');

global $a_acmeserver;
$a_acmeserver = array();
$a_acmeserver['letsencrypt-staging'] = array('name' => "Let's Encrypt Staging (for TESTING purposes)",
    'url' => 'https://acme-staging.api.letsencrypt.org'
);
$a_acmeserver['letsencrypt-production'] = array('name' => "Let's Encrypt Production(Applies ratelimits to certificate requests)",
    'url' => 'https://acme-v01.api.letsencrypt.org'
);

global $acme_domain_validation_method;
$acme_domain_validation_method = array();
$acme_domain_validation_method['webroot'] = array(name => "local webroot folder",
	'fields' => array(
		'folder' => array('name'=>"folder",'columnheader'=>"RootFolder",'type'=>"textbox",'size'=>"50",
			'description' =>"Folder the acme challenge response is written to for example: /usr/local/www/.well-known/acme-challenge/"
		)
	));

/*
$acme_domain_validation_method['ftpwebroot'] = array(name => "ftpwebroot",
	'fields' => array(
		'server' => array('name'=>"ftpserver",'columnheader'=>"Server",'type'=>"textbox",'size'=>"50",
			'description' =>"Hostname of FTP server to connect to for example ftp://www.webserver.tld"
		),
		'method' => array('name'=>"method",'columnheader'=>"Method",'type'=>"textbox",'size'=>"50",
			'description' =>"Method (scp/sftp/ftp/other) to connect to the remote server"
		),
		'username' => array('name'=>"username",'columnheader'=>"Username",'type'=>"textbox",'size'=>"50",
			'description' =>"Username for the remote server"
		),
		'password' => array('name'=>"password",'columnheader'=>"Password",'type'=>"textbox",'size'=>"50",
			'description' =>"Password used to authenticate to the server"
		),
		'folder' => array('name'=>"folder",'columnheader'=>"Folder",'type'=>"textbox",'size'=>"50",
			'description' =>"Folder the acme challenge response is written to for default: /.well-known/acme-challenge/"
		)
	));
$acme_domain_validation_method['http-post'] = array(name => "http-post",
	'fields' => array(
		'url' => array('name'=>"url",'columnheader'=>"Url",'type'=>"textbox",'size'=>"50",
			'description' =>"Url the challenge file is posted to, the webserver there must store and reply to the request when the acme servers perform the request for the file from /.well-known/acme-challenge/"
		)
	));
*/

$acme_newcertificateactions = array();
$acme_newcertificateactions['shellcommand'] = array(name => "shell command");
//$acme_domain_validation_method['php command'] = array(name => "php command script");

// </editor-fold>
#end

function set_cronjob() {
	global $config;
	$a_global = &$config['installedpackages']['acme'];
	if (isset($a_global['enable'])) {
		install_cron_job("/etc/rc.acme_renew.sh", true, "16", "3");
	} else {
		install_cron_job("/etc/rc.acme_renew.sh", false);
	}
}

function acme_custom_php_deinstall_command() {
	global $static_output;
	$static_output .= "Acme, running acme_custom_php_deinstall_command()\n";
	update_output_window($static_output);
	$static_output .= "Acme, deleting renew_renew.sh\n";
	update_output_window($static_output);
	unlink_if_exists("/etc/rc.acme_renew.sh");
	$static_output .= "Acme, uninstalling cron job\n";
	update_output_window($static_output);
	install_cron_job("/etc/rc.acme_renew.sh", false);
	$static_output .= "Acme, running acme_custom_php_deinstall_command() DONE\n";
	update_output_window($static_output);
}

function acme_custom_php_install_command() {
	global $g, $config, $static_output;
	$static_output .= "Acme, running acme_custom_php_install_command()\n";
	update_output_window($static_output);

	$acme_renew = <<<EOD
#!/usr/local/bin/php-cgi -f
<?php

/*
	Updates acme certificates when applicable.
*/

namespace pfsense_pkg\acme;
			
require_once("globals.inc");		
require_once("functions.inc");
require_once("acme/acme.inc");
renew_all_certificates();

?>

EOD;
	// removing the \r prevents the "No input file specified." error..
	$acme_renew = str_replace("\r\n","\n", $acme_renew);
	$fd = fopen("/etc/rc.acme_renew.sh", "w");
	fwrite($fd, $acme_renew);
	fclose($fd);
	chmod("/etc/rc.acme_renew.sh", 0755);

	set_cronjob();
	
	$static_output .= "Acme, running acme_custom_php_install_command() DONE\n";
	update_output_window($static_output);
}

function get_itembyname($a_array, $name) {
	$i = 0;
	if (is_array($a_array)) {
		foreach ($a_array as $key => $item) {
			if ($item['name'] == $name) {
				return $i;
			}
			$i++;
		}
	}
	return null;	
}
	
function get_accountkey_id($name) {
	global $config;
	$a_array = &$config['installedpackages']['acme']['accountkeys']['item'];
	return get_itembyname($a_array, $name);
}
function get_accountkey($name) {
	global $config;
	$a_array = &$config['installedpackages']['acme']['accountkeys']['item'];
	$id = get_accountkey_id($name);
	return $a_array[$id];
}

function get_certificate_id($name) {
	global $config;
	$a_certificates = &$config['installedpackages']['acme']['certificates']['item'];
	$i = 0;
	if (is_array($a_certificates)) {
		foreach ($a_certificates as $key => $certificate) {
			if ($certificate['name'] == $name) {
				return $i;
			}
			$i++;
		}
	}
	return null;
}

function & get_certificate($name) {
	global $config;
	$a_certificates = &$config['installedpackages']['acme']['certificates']['item'];
	$id = get_certificate_id($name);
	if (is_numeric($id)) {
		return $a_certificates[$id];
	}
	return null;
}

	function createAcmeAccountKey(){
		$certificatename = "acme_account_key";
		$cert = lookup_cert_by_name($certificatename);
		if (!is_array($cert)) {
			global $config;
			$a_cert =& $config['cert'];
			$cert = array();
			$cert['refid'] = uniqid();
			$cert['descr'] = $certificatename;
			$accountkey = generateAccountKey();
			cert_import($cert, $accountkey['crt'], $accountkey['prv']);
			$a_cert[] = $cert;
			$changedesc = "Services: Acme";
			$changedesc .= " created acme account key";
			write_config($changedesc);
		}
	}
	function generateAccountKey()
    {
        $res = openssl_pkey_new(array(
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
            "private_key_bits" => 4096,
        ));
		if(!openssl_pkey_export($res, $privateKey)) {
            throw new \RuntimeException("Key export failed!");
        }
		return $privateKey;
	}

	function getAcmeClient($ca) {
		$logger = new Logger();
		$le = \Analogic\ACME\Lescript::createWithCustomEvents($ca, $logger);
		return $le;
	}
	function renew_all_certificates(){
		global $config;
		$a_global = &$config['installedpackages']['acme'];

		foreach($a_global['certificates']['item'] as $certificate) {
			echo "Checking if renewal is needed for: {$certificate['name']}\n";
			renew_certificate($certificate['name']);
		}
	}
	
	function renew_certificate($id, $force = false) {
		$certificate = & get_certificate($id);
		if (!$force) {
			if ($certificate['status'] != 'active') {
				echo "Certificate renewal for this certificate is set to: disabled\n";
				return;
			}

			$timetorenew = false;
			$now = new \DateTime();
			$lastrenewal = new \DateTime();
			$lastrenewal->setTimestamp($certificate['lastrenewal']);
			$nextrenewal = $lastrenewal->add(new \DateInterval('P'.$certificate['renewafter'].'D'));
			if ($now >= $nextrenewal) {
				echo "## Its time to renew ##\n";
				$timetorenew = true;
			}
		}
		
		if ($timetorenew || $force) {
			syslog(LOG_NOTICE, "Acme, renewing certificate: {$id}");
			echo "Renewing certificate";
			$domainstosign = array();
			foreach($certificate['a_domainlist']['item'] as $domain) {
				$domainstosign[] = $domain['name'];
			}
			
			echo "account: {$certificate['acmeaccount']} \n";
			$account = get_accountkey($certificate['acmeaccount']);
			$acmeserver = $account['acmeserver'];
			$key = $account['accountkey'];
			echo "server: $acmeserver \n";
			global $a_acmeserver;
			$url = $a_acmeserver[$acmeserver]['url'];
			$le = getAcmeClient($url);
			$le->setPrivateKey(base64_decode($key));
			
			$handler = new acme_handler();
			$handler->certificateinfo = & $certificate;
			$handler->path = "";
			$le->callback = $handler;
			$le->signDomains($domainstosign);
			
			foreach($certificate['a_actionlist']['item'] as $action) {
				if ($action['method'] == "shellcommand") {
					echo "Running {$action['command']}\n";
					mwexec_bg($action['command']);
				}
			}
			syslog(LOG_NOTICE, "Acme, certificate renewed: {$id}");
			
		}
	}
	class acme_handler {
		public $path = "";
		public $certificateinfo = null;
		function chalenge_response_put($domain, $token, $payload){
			foreach($this->certificateinfo['a_domainlist']['item'] as $domainitem) {
				if($domainitem['name'] == $domain){
					$domain_info = $domainitem;
				}
			}
			//echo "<br/><br/><br/><br/><br/><br/>domain_info({$domain})<br/>";
			//print_r($domain_info);
			if ($domain_info['method'] == 'webroot') {
				//echo "<br/>USING WEBROOT";
				$directory = $domain_info['webrootfolder'];
				if(!file_exists($directory) && !@mkdir($directory, 0755, true)) {
					throw new \RuntimeException("Couldn't create directory to expose challenge: ${tokenPath}");
				}
				$tokenPath = $directory . "/" . $token;
				//echo "<br/>Saving token in:".$tokenPath;
				file_put_contents($tokenPath, $payload);
			}
		}
		function chalenge_response_cleanup($domain, $token){
			foreach($this->certificateinfo['a_domainlist']['item'] as $domainitem) {
				if($domainitem['name'] == $domain){
					$domain_info = $domainitem;
				}
			}
			if ($domain_info['method'] == 'webroot') {
				@unlink($tokenPath);
			}
		}
		function getCertificatePSK(){
			$certificatename = "acme_cert_" . $this->certificateinfo['name'];
			$cert = lookup_cert_by_name($certificatename);
			if (!is_array($cert)) {
				global $config;
				$a_cert =& $config['cert'];
				$cert = array();
				$cert['refid'] = uniqid();
				$cert['descr'] = $certificatename;
				$accountkey = generateAccountKey();
				//cert_import($cert, $accountkey['crt'], $accountkey['prv']);
				//$cert['crt'] = base64_encode($crt_str);
				$cert['prv'] = base64_encode($accountkey['prv']);
				$a_cert[] = $cert;
			}
			return base64_decode($cert['prv']);
		}
		function storeCertificate($certificates){
			$certificatename = "acme_cert_" . $this->certificateinfo['name'];
			global $config;
			if (is_array($config['cert'])) {
				foreach ($config['cert'] as &$cert) {
					if ($cert['descr'] == $certificatename) {
						//TODO add validation that the new cert 'fits' on the private key..
						$cert['crt'] = base64_encode(array_shift($certificates));
						
						$id = get_certificate_id($this->certificateinfo['name']);
						$a_certificates = &$config['installedpackages']['acme']['certificates']['item'];
						$a_certificates[$id]['lastrenewal'] = time();
					}
				}
			}
			// store chain to.?.
			//$cert = lookup_cert_by_name($certificatename);

			//if (is_array($cert)) {
				//$cert['crt'] = array_shift($certificates);
				//echo "NEW CRT: ".base64_decode($cert['crt']);
			//}
			$changedesc = "Services: Acme";
			$changedesc .= "Storing signed certificate";
			write_config($changedesc);
		}
	}
	
	function registerAcmeAccountKey($ca, $key) {
		//public $ca = 'https://acme-v01.api.letsencrypt.org';
		//$ca = 'https://acme-staging.api.letsencrypt.org'; // testing
		
		//$certificatename = "acme_account_key";
		//$cert = lookup_cert_by_name($certificatename);
		//$key = $cert['prv'];
		
		//echo "<br/><br/><br/><br/><br/>ACME result:<br/>";
		$logger = new Logger();
		$le = \Analogic\ACME\Lescript::createWithCustomEvents($ca, $logger);
		//$le->setPrivateKey(base64_decode($key));
		$le->setPrivateKey($key);
		$result = $le->postNewReg();
		//print_r($result);
		//echo "</pre>";
	}
	class Logger { 
		function __call($name, $arguments) { 
			echo date('Y-m-d H:i:s')." [$name] ${arguments[0]}\n";
		}
	}
?>
