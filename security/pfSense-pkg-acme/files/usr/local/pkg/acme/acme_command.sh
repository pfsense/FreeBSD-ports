#!/usr/local/bin/php -f
<?php
namespace pfsense_pkg\acme;

include_once("config.lib.inc");
include_once("acme.inc");

$command = $argv[1];

if ($command == "renewall") {
	renew_all_certificates();
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
	$certificate = &$config['installedpackages']['acme']['certificates']['item'][$id];
	$certificate['lastrenewal'] = time();

	$changedesc = "Services: Acme: ";
	$changedesc .= "Storing signed certificate: " . $certificatename;
	write_config($changedesc);

	if (is_array($certificate['a_actionlist']['item'])) {
		foreach($certificate['a_actionlist']['item'] as $action) {
			if ($action['status'] == "disable") {
				continue;
			}
			if ($action['method'] == "shellcommand") {
				syslog(LOG_NOTICE, "Acme, Running {$action['command']}");
				mwexec_bg($action['command']);
			}
			if ($action['method'] == "php_command") {
				syslog(LOG_NOTICE, "Acme, Running php {$action['command']}");
				eval($action['command']);
			}
			if ($action['method'] == "servicerestart") {
				syslog(LOG_NOTICE, "Acme, Restarting service {$action['command']}");
				list($servicename, $extras) = acme_fixup_service_args($action['command']);
				if (!empty($servicename)) {
					service_control_restart($servicename, $extras);
				}
			}
			if ($action['method'] == "xmlrpcservicerestart") {
				syslog(LOG_NOTICE, "Acme, Restarting remote service via XMLRPC {$action['command']}");
				list($servicename, $extras) = acme_fixup_service_args($action['command']);
				if (!empty($servicename)) {
					acme_xmlrpc_restart_service($servicename, $extras);
				}
			}
		}
	}
}

if ($command == "deploykey") {
	$certificatename = $argv[2];
	$domain = $argv[3];
	$token = $argv[4];
	$payload = $argv[5];
	challenge_response_put($certificatename, $domain, $token, $payload);
}

if ($command == "removekey") {
	$certificatename = $argv[2];
	$domain = $argv[3];
	$token = $argv[4];
	challenge_response_cleanup($certificatename, $domain, $token);
}