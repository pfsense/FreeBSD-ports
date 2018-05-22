<?php
/*
 * services_servicewatchdog_add.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2013-2017 Rubicon Communications, LLC (Netgate)
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

##|+PRIV
##|*IDENT=page-services-servicewatchdog-add
##|*NAME=Services: Add Service Watchdog Services
##|*DESCR=Allow access to the 'Add Service Watchdog Services' page.
##|*MATCH=services_servicewatchdog.php-add*
##|-PRIV

require("guiconfig.inc");
require_once("service-utils.inc");
require_once("servicewatchdog.inc");

if (!is_array($config['installedpackages']['servicewatchdog'])) {
	$config['installedpackages']['servicewatchdog'] = array();
}

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
		write_config(gettext("Services: Service Watchdog: added a service to watchdog."));

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
