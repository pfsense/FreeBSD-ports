<?php
/*
	services_servicewatchdog_add.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2013 Jim Pingle
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
/*
	pfSense_MODULE:	system
*/

##|+PRIV
##|*IDENT=page-services-servicewatchdog-add
##|*NAME=Services: Add Service Watchdog Services
##|*DESCR=Allow access to the 'Add Service Watchdog Services' page.
##|*MATCH=services_servicewatchdog.php-add*
##|-PRIV

require("guiconfig.inc");
require_once("service-utils.inc");
require_once("servicewatchdog.inc");

if (!is_array($config['installedpackages']['servicewatchdog']['item'])) {
	$config['installedpackages']['servicewatchdog']['item'] = array();
}
$a_pwservices = &$config['installedpackages']['servicewatchdog']['item'];

unset($input_errors);

if ($_POST) {
	if (!is_numeric($_POST['svcid'])) {
		return;
	}

	$system_services = get_services();
	if (!isset($system_services[$_POST['svcid']])) {
		$input_errors[] = gettext("The supplied service appears to be invalid.");
	}

	if (!$input_errors) {
		$a_pwservices[] = $system_services[$_POST['svcid']];
		servicewatchdog_cron_job();
		write_config();

		header("Location: services_servicewatchdog.php");
		return;
	}
}

$pgtitle = array(gettext("Services"), gettext("Service Watchdog"), gettext("Add"));
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$form = new Form("Add");

$section = new Form_Section('Add Service to Monitor');

$section->addInput(new Form_Select(
	'svcid',
	'Service to Add',
	null,
	servicewatchdog_build_service_list()
))->setHelp('Select a service to add to the monitoring list.');


$form->add($section);

print($form);

?>
<?php include("foot.inc"); ?>
