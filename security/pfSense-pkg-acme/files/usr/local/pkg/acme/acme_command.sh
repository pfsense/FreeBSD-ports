#!/usr/local/bin/php -f
<?php
namespace pfsense_pkg\acme;

include_once("config.lib.inc");
include_once("acme.inc");

$command = $argv[1];

$force = false;
$perform = "";
$certname = "";
for($i = 0; $i < count($argv); $i++){
	if ($argv[$i] == '-force') {
		$force = true;
	}
	if (substr($argv[$i],0,9) == '-perform=') {
		$perform = substr($argv[$i],9);
	}
	if (substr($argv[$i],0,10) == '-certname=') {
		$certname = substr($argv[$i],10);
	}
}

if ($command == "renewall") {
	renew_all_certificates($force);
	return;
}

if ($command == "importcert") {
	$certificatename = $argv[2];
	$domain = $argv[3];
	$CERT_KEY_PATH = $argv[4];
	$CERT_PATH = $argv[5];
	$CA_CERT_PATH = $argv[6];
	$CERT_FULLCHAIN_PATH = $argv[7];
	echo "\nIMPORT CERT $certificatename, $CERT_KEY_PATH, $CERT_PATH";
	storeCertificateCer($certificatename, $CERT_KEY_PATH, $CERT_PATH, $CERT_FULLCHAIN_PATH);

	$id = get_certificate_id($certificatename);
	config_set_path("installedpackages/acme/certificates/item/{$id}/lastrenewal", time());

	$changedesc = "Services: Acme: ";
	$changedesc .= "Storing signed certificate: " . $certificatename;
	write_config($changedesc);

	acme_write_all_certificates();
	foreach(config_get_path("installedpackages/acme/certificates/item/{$id}/a_actionlist/item", []) as $action) {
		if ($action['status'] == "disable") {
			continue;
		}
		if ($action['method'] == "shellcommand") {
			logger(LOG_NOTICE, localize_text("Running %s", $action['command']), LOG_PREFIX_PKG_ACME);
			mwexec_bg($action['command']);
		}
		if ($action['method'] == "php_command") {
			logger(LOG_NOTICE, localize_text("Running php %s", $action['command']), LOG_PREFIX_PKG_ACME);
			eval($action['command']);
		}
		if ($action['method'] == "servicerestart") {
			logger(LOG_NOTICE, localize_text("Restarting service %s", $action['command']), LOG_PREFIX_PKG_ACME);
			list($servicename, $extras) = acme_fixup_service_args($action['command']);
			if (!empty($servicename)) {
				service_control_restart($servicename, $extras);
			}
		}
		if ($action['method'] == "xmlrpcservicerestart") {
			logger(LOG_NOTICE, localize_text("Restarting remote service via XMLRPC %s", $action['command']), LOG_PREFIX_PKG_ACME);
			list($servicename, $extras) = acme_fixup_service_args($action['command']);
			if (!empty($servicename)) {
				/* Wait a few seconds before triggering the restart in case the
					secondary node is not yet ready after configuration sync */
				sleep(10);
				acme_xmlrpc_restart_service($servicename, $extras);
			}
		}
	}
	return;
}

if ($command == "deploykey") {
	$certificatename = $argv[2];
	$domain = $argv[3];
	$token = $argv[4];
	$payload = $argv[5];
	challenge_response_put($certificatename, $domain, $token, $payload);
	return;
}

if ($command == "removekey") {
	$certificatename = $argv[2];
	$domain = $argv[3];
	$token = $argv[4];
	challenge_response_cleanup($certificatename, $domain, $token);
	return;
}

if ($perform == "issue" && !empty($certname)) {
	issue_certificate($certname, $force);
	return;
}
if ($perform == "renew" && !empty($certname)) {
	issue_certificate($certname, $force, true);
	return;
}

echo "Use acme_command.sh like this:\n";
echo "  acme_command.sh renewall\n";
echo "  acme_command.sh importcert MyCertificate DomainName CertKeyPath CertPath CaCertPath CertFullChainPath\n";
echo "  acme_command.sh deploykey MyCertificate DomainName Token Payload\n";
echo "  acme_command.sh removekey MyCertificate DomainName Token\n";
echo "  acme_command.sh -- -perform=issue -certname=MyCertificate [-force]\n";
echo "  acme_command.sh -- -perform=renew -certname=MyCertificate [-force]\n";
