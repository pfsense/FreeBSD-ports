<?php
/*******************************************************************************
* vHosts package configuration page. Drops a list of the configured virtual
* hosts or a form page for host configuration. When a form is posted back, the
* host information is saved and the server config file is rebuilt.
*
* GET: No parameters:
*   Drops list of configured vhosts with options to add/change/delete or toggle
*   the disabled flag.
*
* GET: 'act' = 'add'
*   Drops form for input of new host settings.
*
* GET: 'act' = 'chg', 'id' = n
*   Drops form for update of host 'n' config.
*   Form action returns 'id' value.
*
* GET: 'act' = 'del', 'id' = n
*   Removes host 'n' config, update web config, and drops list page.
*
* GET: 'act' = 'dup', 'id' = n
*   Loads the 'add' page with defaults loaded from host 'n' config.
*   Form action returns 'srcid' set to 'id'.
*
* GET: 'act' = 'tog', 'id' = n
*	Toggles Disabled flag for host 'n'.
*
* POST: 'id' = n (optional), 'srcid' = n (optional)
*   Adds or updates host config, updates web config, and drops list page.
*   If 'id' is empty but 'srcid' has a value, the new copy is inserted
*   after the source host ID so it appears next in the list.
* ------------------------------------------------------------------------------
* Part of pfSense 2.3 and later (https://www.pfSense.org/).
* Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
*
* Inspired by vhosts package originally written by Mark Crane.
* Copyright (C) 2008 Mark J Crane
* Copyright (C) 2015 ESF, LLC
* Copyright (C) 2016 Softlife Consulting
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
*******************************************************************************/
require_once("guiconfig.inc");
require_once("util.inc");
require("/usr/local/pkg/vhosts.inc");

$pgtitle = [ gettext("Services"), gettext("vHosts"), gettext("Hosts") ];
$pgtabs  = 
	[
	[ gettext("Hosts"),        true,  "vhosts.php" ],
	[ gettext("Certificates"), false, "vhosts_certs.php" ]
	];

/*******************************************************************************
* Main Line
*******************************************************************************/
$a_vhosts = &$config['installedpackages']['vhosts']['config'];
$act      = $_GET['act'];
$id       = $_GET['id'];
$good_id  = (isset($id) && isset($a_vhosts[$id]));

/*----------------------------------*/
/* Add/Copy/Update - Validate input */
/* and save changes to config.      */
/*----------------------------------*/
if ($_POST) {
	$pconfig['dirname']       = trim($_POST['dirname']);
	$pconfig['ipaddress']     = $_POST['ipaddress'];
	$pconfig['port']          = $_POST['port'];
	$pconfig['hostname']      = trim($_POST['hostname']);
	$pconfig['certref']       = $_POST['certref'];
	$pconfig['description']   = trim($_POST['description']);
	$pconfig['custom_config'] = str_replace("\r\n", "\n", $_POST['custom_config']);

	if ($_POST['disabled'] == 'yes') {
		$pconfig['disabled'] = true;
	}

	/*---------------------------------------*/
	/* Validate the input and update config. */
	/*---------------------------------------*/
	unset($input_errors);

	if (empty($pconfig['dirname'])) {
		$input_errors[] = gettext('Name is required.');
	}

	if (!is_ipaddrv4($pconfig['ipaddress'])) {
		$input_errors[] = gettext('IP Address is not a valid IPv4 address.');
	}

	if (!is_numeric($pconfig['port'])) {
		$input_errors[] = gettext('Port must be a number.');
	}

	if (!$input_errors) {
		/*-------------------------------*/
		/* Edit - Update the host by ID. */
		/*-------------------------------*/
		if ($good_id) {
			$a_vhosts[$id] = $pconfig;
		}

		/*-------------------------------------------------------*/
		/* Copy - Add new host copy after copied host source ID. */
		/*-------------------------------------------------------*/
		else if (($id = $_GET['srcid']) != null && isset($a_vhosts[$id])) {
			array_splice($a_vhosts, $id+1, 0, [$pconfig]);
		}

		/*--------------------------------*/
		/* Add - Add new host to the end. */
		/*--------------------------------*/
		else {
			$a_vhosts[] = $pconfig;
		}

		vhosts_save_config_and_exit();
		}

	/*-------------------------------------------------*/
	/* Validation failed, redisplay input with errors. */
	/*-------------------------------------------------*/
	else {
		$input_action = ($good_id ? 'Edit' : 'Add');
	}
}

/*--------------------------*/
/* Toggle enabled/disabled. */
/*--------------------------*/
elseif ($good_id && $act == 'tog') {
	if (isset($a_vhosts[$id]['disabled'])) {
		unset($a_vhosts[$id]['disabled']);
	} else {
		$a_vhosts[$id]['disabled'] = true;
	}
	vhosts_save_config_and_exit();
}

/*--------------------------------------*/
/* Delete - Remove vhost configuration. */
/*--------------------------------------*/
elseif ($good_id && $act == 'del') {
	unset($a_vhosts[$id]);
	vhosts_save_config_and_exit();
}

/*---------------------------------------------------*/
/* Edit/Copy - Load vhost configuration and display. */
/*---------------------------------------------------*/
elseif ($good_id && ($act == 'chg' || $act == 'dup')) {
	$pconfig['disabled']      = isset($a_vhosts[$id]['disabled']);
	$pconfig['dirname']       = $a_vhosts[$id]['dirname'];
	$pconfig['ipaddress']     = $a_vhosts[$id]['ipaddress'];
	$pconfig['port']          = $a_vhosts[$id]['port'];
	$pconfig['hostname']      = $a_vhosts[$id]['hostname'];
	$pconfig['certref']       = $a_vhosts[$id]['certref'];
	$pconfig['description']   = $a_vhosts[$id]['description'];
	$pconfig['custom_config'] = $a_vhosts[$id]['custom_config'];

	/*-------------------------------------------------*/
	/* Verify the selected certificate is still valid. */
	/*-------------------------------------------------*/
	if (!empty(trim($pconfig['certref']))) {
		if (!vhosts_lookup_cert($pconfig['certref'])) {
			$pconfig['certref'] = '';
		}
	}

	/*---------------------------------------------------*/
	/* For Edit, set the form action to return the ID.   */
	/* For Copy, set the action to return the source ID. */
	/*---------------------------------------------------*/
	if ($act == 'chg') {
		$input_action = 'Edit';
		$form_action  = "?id=$id";
	} else {
		$input_action = 'Add';
		$form_action  = "?srcid=$id";
	}
}

/*------------------------------------*/
/* Add - Display input for new vHost. */
/*------------------------------------*/
elseif ($act == 'add') {
	$input_action    = 'Add';
	$pconfig['port'] = '8000';
}

/*******************************************************************************
* Add/Change Host Form Page
*******************************************************************************/
if (isset($input_action)) {
	/*-------------------------*/
	/* Build certificate list. */
	/*-------------------------*/
	$certlist[''] = 'None';
	foreach($config['installedpackages']['vhosts']['cert'] as $cert) {
		$certlist[$cert['refid']] = $cert['cn'];
	}
	
	/*---------------------------------------------------*/
	/* Grab the error log file path from the nginx       */
	/* compile args. This is where errors will be logged */
	/* when the server starts if the config is bad.      */
	/*---------------------------------------------------*/
	$nginx_cfg = shell_exec("sh -c \"nginx -V\" 2>&1 | grep ^configure");
	$error_log = preg_replace('/^.+--error-log-path=(.+?)(\\s--|$).*$/', '\\1', $nginx_cfg);

	/*---------------------------------------*/
	/* Set the page title and drop the form. */
	/*---------------------------------------*/
	$pgtitle[] = gettext($input_action);

	/*-----------------------------------------------------*/
	/* Build form. Save button auto set as default submit. */
	/*-----------------------------------------------------*/
	$help =
		[
		'disabled'      => gettext('Set this option to disable this server.'),
		'dirname'       => gettext("Required. Document root directory name in {$vhosts_g['root_base_path']}."
		                          ."<br/><b>Note:</b> Web server files must added to the file system manually and changing the name here will not move any files."),
		'ipaddress'     => gettext('Required. Make sure the IP and Port combination does not conflict with the local system.'),
		'port'          => gettext('Required. Make sure the IP and Port combination does not conflict with the local system.'),
		'hostname'      => gettext('Space-separated list of host names for Name-Based Host(s). Not required for IP-Based host.'),
		'certref'       => gettext('SSL/TLS certificate for secure connection (optional). Select from <a href="vhosts_certs.php">Certificates</a> list.'),
		'custom_config' => gettext('Enter any additional configuration parameters to add to the nginx configuration file for this web server.'
		                          ."<br/><b>Note:</b> If the vHosts service does not start, configuration errors can be found in <code>$error_log</code>.")
		];

	$sections =
		[
		[ 'Host Information' =>
			[
			new Form_Checkbox('disabled',      'Disabled',             'Disable this server', $pconfig['disabled']),
			new Form_Input   ('dirname',       'Directory Name',       'text',                $pconfig['dirname']),
			new Form_Input   ('ipaddress',     'IP Address',           'text',                $pconfig['ipaddress']),
			new Form_Input   ('port',          'Port',                 'number',              $pconfig['port'],       ['min' => '0']),
			new Form_Input   ('hostname',      'Host Name(s)',         'text',                $pconfig['hostname']),
			new Form_Input   ('description',   'Description',          'text',                $pconfig['description']),
			new Form_Select  ('certref',       'Secure certificate',                          $pconfig['certref'],    $certlist)
			]],
		[ 'Advanced Options' =>
			[
			new Form_Textarea('custom_config', 'Custom Configuration',                        $pconfig['custom_config'])
			]]
		];

	$form = new Form();

	/*-----------------------------*/
	/* Set the form action if any. */
	/*-----------------------------*/
	if (isset($form_action)) {
		$form->setAction($form_action);
	}

	foreach($sections as $s) {
		foreach($s as $title => $fields) {
			$section = new Form_Section($title);

			foreach($fields as $field) {
				$section->addInput($field->setHelp($help[$field->getName()] ?: null));
			}
			$form->add($section);
		}
	}

	/*----------------------------------*/
	/* Cancel button reloads list page. */
	/*----------------------------------*/
	$cancel = new Form_Button('cancel', 'Cancel', null, 'fa-undo');
	$cancel->setAttribute('type', 'button');
	$cancel->setAttribute('onclick', 'window.location = "?"');
	$cancel->addClass('btn-warning');
	$form->addGlobal($cancel);

	/*-----------------*/
	/* Drop form page. */
	/*-----------------*/
	$helpurl = "https://doc.pfsense.org/index.php/vHosts_package?";
	include("head.inc");

	if ($input_errors) {
		print_input_errors($input_errors);
	}
	
	display_top_tabs($pgtabs);
	
	print $form;
}

/*******************************************************************************
* List Page
*******************************************************************************/
else {
	$shortcut_section = $vhosts_g['subsystem_name'];
	$vhostsd_running  = vhostsd_is_running();

	include("head.inc");

	if (vhosts_dirty()) {
		print_info_box(gettext('vHosts configuration has been changed. Restart service to apply changes.'));
	}

	display_top_tabs($pgtabs);

	?>
	<div class="panel panel-default">
		<div class="panel-heading"></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap table-rowdblclickedit" data-sortable>
				<thead>
					<tr>
						<th><?=gettext("IP Address:Port")?></th>
						<th><?=gettext("Directory Name")?></th>
						<th><?=gettext("Host Name")?></th>
						<th><?=gettext("Secure")?></th>
						<th><?=gettext("Description")?></th>
					</tr>
				</thead>
				<tbody>
				<?php

				for ($i=0; $i < count($a_vhosts); $i++) {
					$vhost     = $a_vhosts[$i];
					$disabled  = isset($vhost['disabled']);
					$dirname   = $vhost['dirname'];
					$hostname  = $vhost['hostname'];
					$ipaddress = $vhost['ipaddress'];
					$port      = $vhost['port'];
					$ssl       = vhosts_lookup_cert($vhost['certref']);

					/*-------------------------------------------------*/
					/* Build link to vhost around host name. Use first */
					/* host listed in 'hostname' or 'ipaddress:port'.  */
					/*-------------------------------------------------*/
					$ipport    = htmlspecialchars("$ipaddress:$port");
					$hostname  = ($hostname ? explode(' ', $hostname)[0] : '');
					$hosturl   = ($ssl ? 'https' : 'http')."://".($hostname ?: $ipaddress).":$port";
					$hostlink  = ($disabled || !$vhostsd_running ? $ipport : "<a href='$hosturl'>$ipport</a>");

					if ($disabled) {
						$toggle_text  = gettext('Enable');
						$toggle_class = 'fa fa-check-square-o';
						}

					?>
					<tr <?=$disabled ? 'class="disabled"':''?>>
						<td><?=$hostlink?></td>
						<td><?=htmlspecialchars($dirname)?></td>
						<td><?=htmlspecialchars($hostname)?></td>
						<td><?=($ssl ? 'Yes' : 'No')?></td>
						<td><?=htmlspecialchars($vhost['description'])?></td>
						<td>
							<a class="fa fa-pencil" title="<?=gettext('Edit')?>"   href="?act=chg&amp;id=<?=$i?>"></a>
							<a class="fa fa-clone"  title="<?=gettext('Copy')?>"   href="?act=dup&amp;id=<?=$i?>"></a>

							<a class="fa fa-<?=($disabled ? 'check-square-o' : 'ban')?>"
							   title="<?=gettext($disabled ? "Enable" : "Disable")?>"
								href="?act=tog&amp;id=<?=$i?>"></a>

							<a class="fa fa-trash"	title="<?=gettext('Delete this host')?>" href="?act=del&amp;id=<?=$i?>"></a>
						</td>
					</tr>
					<?php
					}
				?>
				</tbody>
			</table>
		</div>
	</div>

	<?php
	/*-------------------------------------------------------------------*/
	/* pfSenseHelpers.js will add a listener to the "showinfo-vhosts"    */
	/* icon that will toggle display of "infoblock-vhosts". We are short */
	/* circuiting the "infoblock" feature to manually place the "Info"   */
	/* icon to the left of the "action-buttons" which looks cleaner.     */
	/*-------------------------------------------------------------------*/
	?>
	<nav class="action-buttons">
		<span style="float:left;margin-top:5px">
			<i id="showinfo-vhosts" title="More information"
				class="fa fa-info-circle icon-pointer" style="color: #337AB7; font-size:20px; margin-left:10px; margin-bottom:10px">
			</i>
		</span>
		<a href="?act=add" class="btn btn-sm btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
			<?=gettext("Add")?>
		</a>
	</nav>

	<div class="infoblock-vhosts alert alert-info clearfix" role="alert" style="display:none; margin-top:-10px">
		<?=gettext('<p>vHosts is a web server package to host HTML, Javascript, CSS, and PHP. It creates another instance'.
		           'of the <b>nginx</b> web server that is already installed and uses PHP5 in FastCGI mode.</p>'.
		           "<p>The document root folder of a vHost configuration is <b>{$vhosts_g['root_base_path']}/</b><i>Name</i>. Files can be ".
		           'copied to the root folder using <b>Diagnostics->Edit File</b> or, </b>to allow file transfer using <b>SCP</b> or <b>SFTP</b>, '.
		           'enable SSH from <b>System->Advanced->Enable Secure Shell</b>.</p>'.
		           '<p><b>SSL/TLS Certificates</b> for secure hosts are added to the <a href="vhosts_certs.php">Certificates</a> list. They can '.
		           'then be assigned to one or more hosts for secure SSL/TLS connections.</p>'.
		           '<p>After adding or updating a vHost configuration, <b>restart the service to apply the settings</b>.</p>'); ?>
	</div>

	<?php
}

include("foot.inc");
?>
