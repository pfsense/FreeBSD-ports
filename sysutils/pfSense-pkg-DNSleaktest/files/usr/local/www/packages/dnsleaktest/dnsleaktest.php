<?php
/*
 * dnsleaktest.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2022 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2022 Luis Moraguez (Package Author)
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
##|*IDENT=page-diagnostics-dnsleaktest
##|*NAME=Diagnostics: DNSleaktest
##|*DESCR=Allow access to the 'Diagnostics: DNSleaktest' page.
##|*MATCH=dnsleaktest.php*
##|-PRIV

$allowautocomplete = true;
$pgtitle = array(gettext("Diagnostics"), gettext("DNSleaktest"));
require_once("guiconfig.inc");
require_once("/usr/local/pkg/dnsleaktest.inc");

$do_dnsleaktest = false;
$api_domain = '';
$sourceif = '';

# Function to get real interfaces and convert to array
$real_interfaces = shell_exec("/sbin/ifconfig -a | /usr/bin/sed 's/[ :\t].*//;/^$/d'");
$real_interfaces_array = explode("\n", $real_interfaces);
# Remove last element of array (blank space)
array_pop($real_interfaces_array);

# if submit button is clicked
if ($_POST['submit']) {
    unset($input_errors);
    unset($do_dnsleaktest);

    # Check if source interface is selected
    if (empty($_POST['srcinterface'])) {
        $input_errors[] = gettext("You must select a source interface.");
    }

    # Check if API domain is empty
    if (empty($_POST['api_domain'])) {
        $input_errors[] = gettext("You must enter an API domain.");
    }

    # If no errors, run the test
    if (!$input_errors) {
        if ($_POST) {
            $do_dnsleaktest = true;
        }
        if (isset($_REQUEST['srcinterface'])) {
            $sourceif = $_REQUEST['srcinterface'];
        }
        if (isset($_REQUEST['api_domain'])) {
            $api_domain = $_REQUEST['api_domain'];
        }
    }
}

if ($do_dnsleaktest) {
    $command = "/usr/local/pkg/dnsleaktest.sh";
    $cmd = "{$command} {$api_domain} {$sourceif}";
    $result = shell_exec($cmd);
}

include("head.inc");

?>

<?php
    $form = new Form(false);
    $section = new Form_Section('DNSleaktest');

    $section->addInput(new Form_Select(
        'srcinterface',
        '*Source Interface',
        $sourceif,
        ['' => ''] + array_combine($real_interfaces_array, $real_interfaces_array)
    ))->setHelp('Select the source interface for the DNSleaktest query.');

    $section->addInput(new Form_Select(
        'api_domain',
        '*API Domain',
        $api_domain,
        ['' => '', 'bash.ws' => 'bash.ws']
    ))->setHelp('Select the API Domain for the DNSleaktest query.');

    $form->add($section);

    $form->addGlobal(new Form_Button(
        'submit',
        'Scan',
        null,
        'fa-rss'        
    ))->addClass('btn-primary');

    print $form;

    if ($do_dnsleaktest && !empty($result)) {
?>

    <div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title"><?=gettext('Results')?></h2>
		</div>

		<div class="panel-body">
			<pre><?= htmlspecialchars($result) ?></pre>
		</div>
	</div>

<?php 
}

include("foot.inc"); ?>