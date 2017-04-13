<?php
/*******************************************************************************
* vHosts package certfificate manager page.  Drops the current list of certs or
* a form page for adding/updating certs.
*
* GET: No parameters:
*   Drops current list of certs with options to add/change/delete. Delete option
*   is available only if cert is not in use.
*
* GET: 'act' = 'add'
*   Drops form for input of new certificate and key.
*
* GET: 'act' = 'chg', 'id' = n
*   Drops form for update of certificate 'n' settings.
*   Form action returns 'id' value.
*
* GET: 'act' = 'del', 'id' = n
*   Removes certificate 'n' and drops list page.
*
* GET: 'act' = 'show', 'id' = n
*   Drops certificate 'n' details for modal dialog.
*
* POST: 'id' = n (optional)
*   Adds or updates certificate config and drops list page.
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

$pgtitle   = [ gettext("Services"), gettext("vHosts"), gettext("Certificates") ];
$pgtabs =
	[
	[ gettext("Hosts"),        false, "vhosts.php" ],
	[ gettext("Certificates"), true,  "vhosts_certs.php" ]
	];

/*******************************************************************************
* Main Line
*******************************************************************************/
$a_vcerts = &$config['installedpackages']['vhosts']['cert'];
$act      = $_GET['act'];
$id       = $_GET['id'];
$good_id  = (isset($id) && isset($a_vcerts[$id]));
$oldcert  = ($good_id ? $a_vcerts[$id] : []);

/*---------------------------------------------------------*/
/* Add/Update - Validate input and save changes to config. */
/*---------------------------------------------------------*/
if ($_POST) {

	unset($input_errors);

	/*-----------------------------------------------*/
	/* Retrieve certs from PKCS#12 file if provided. */
	/*-----------------------------------------------*/
	if ($_FILES['p12file']['tmp_name']) {

		$p12pass = $_POST['p12pass'] ?: '';
		$p12path = $_FILES['p12file']['tmp_name'];

		if (is_uploaded_file($p12path)) {
			if ($p12file = file_get_contents($p12path)) {
				if (openssl_pkcs12_read($p12file, $certs, $p12pass)) {
					$crt = $certs[cert];
					$key = $certs[pkey];
				} else {
					$input_errors[] = gettext('Import Password is incorrect or Key-Pair file is not a valid PKCS#12 file.');
				}
			} else {
				$input_errors[] = gettext('Key-Pair File could not be read.');
			}
		} else {
			$input_errors[] = gettext('Key-Pair File upload failed. Please try again.');
		}

	/*----------------------------------------------------------*/
	/* Retrieve certs from user input and verify proper format. */
	/*----------------------------------------------------------*/
	} else {

		$crt = $_POST['certcrt'];
		$key = $_POST['certkey'];

		if (empty($crt) || empty($key)) {
			$input_errors[] = gettext('Certificate and Certificate Key are required.');

		} elseif ($crt && (!strstr($crt, "BEGIN CERTIFICATE") || !strstr($crt, "END CERTIFICATE"))) {
			$input_errors[] = gettext("This certificate does not appear to be valid.");
		}
	}

	/*---------------------------------------*/
	/* Verify certificate and key are valid. */
	/*---------------------------------------*/
	if (!$input_errors) {

		/*-------------------------------------------*/
		/* Verify cert and key belong to each other. */
		/*-------------------------------------------*/
		if (cert_get_modulus($crt, false) != prv_get_modulus($key, false)) {
			$input_errors[] = gettext("The private key does not match the certificate.");
		}
		/*------------------------------------------*/
		/* Verify cert is used to validate servers. */
		/*------------------------------------------*/
		$certinfo = openssl_x509_parse($crt);
		$newCN    = $certinfo['subject']['CN'];
		$purpose  = array_search('sslserver', array_column($certinfo['purposes'], 2));

		if (!isset($purpose)) {
			$input_errors[] = gettext("This certificate is not for verifying a server.");
		}
		/*---------------------------------*/
		/* For an edit, verify common name */
		/* of new cert matches the old.    */
		/*---------------------------------*/
		if ($good_id && $newCN != $oldcert['cn']) {
			$input_errors[] = gettext("Common Name '$newCN' must match existing certificate Common Name.");
		}

		/*--------------------------------------*/
		/* Verify another certificate with the  */
		/* same common name is not in the list. */
		/*--------------------------------------*/
		if (array_filter($a_vcerts, function($cert) use ($newCN, $oldcert) { return ($cert['cn'] == $newCN && $cert['refid'] != $oldcert['refid']); })) {
			$input_errors[] = gettext("A certificate with the common name '$newCN' already exists.");
		}
	}

	/*-------------------------------*/
	/* Save if validation succeeded. */
	/*-------------------------------*/
	if (!$input_errors) {

		$pconfig['cn']      = $newCN;
		$pconfig['certcrt'] = base64_encode($crt);
		$pconfig['certkey'] = base64_encode($key);
		$pconfig['refid']   = $oldcert['refid'] ?: uniqid();
		$a_vcerts[$id]    	= $pconfig;

		vhosts_save_config_and_exit();
	}

	/*-------------------------------------------------*/
	/* Validation failed, redisplay input with errors. */
	/*-------------------------------------------------*/
	else {
		$pconfig['certcrt'] = $crt;
		$pconfig['certkey'] = $key;
		$pconfig['cn']      = $oldcert['cn'];
		$input_action       = ($good_id ? 'Edit' : 'Add');
	}
}

/*-----------------------------*/
/* Delete - Remove certficate. */
/*-----------------------------*/
elseif ($good_id && $act == 'del') {
	unset($a_vcerts[$id]);
	vhosts_save_config_and_exit();
}

/*--------------------------------------*/
/* Edit - Load certificate and display. */
/*--------------------------------------*/
elseif ($good_id && $act == 'chg') {
	$pconfig['cn']      = $oldcert['cn'];
	$pconfig['certcrt'] = base64_decode($oldcert['certcrt']);
	$pconfig['certkey'] = base64_decode($oldcert['certkey']);

	$input_action = 'Edit';
	$form_action  = "?id=$id";
}

/*-----------------------------------*/
/* Add - Display input for new cert. */
/*-----------------------------------*/
elseif ($act == 'add') {
	$input_action = 'Add';
}

/*------------------------------------------------------*/
/* Show - Return certificate details for modal display. */
/*------------------------------------------------------*/
elseif ($good_id && $act == 'show') {
	$crt = base64_decode($oldcert['certcrt']);
	openssl_x509_export($crt, $details, false);
	header("Content-type: text/plain;");
	echo $details;
	exit;
}

/*******************************************************************************
* Add/Change Certificate Form Page
*******************************************************************************/
if (isset($input_action)) {

	/*-----------------------------------------------------*/
	/* Build form. Save button auto set as default submit. */
	/*-----------------------------------------------------*/
	$help =
		[
		'certcrt' => gettext('Drop or paste contents of X.509 PEM format certificate file here.'),
		'certkey' => gettext('Drop or paste contents of X.509 PEM format certificate key file here.'),
		'p12file' => gettext('Select .p12/.pfx file containing certificate key-pair.'),
		'p12pass' => gettext('Provide the password assigned to Key-Pair File.')
		];

	$certinfo = [];
	
	/*-----------------------------------*/
	/* Show the common name for an edit. */
	/*-----------------------------------*/
	if ($pconfig['cn']) {
		$certinfo[] = new Form_StaticText('Common Name', $pconfig['cn']);
	}
	$certinfo[] = (new Form_Textarea('certcrt', 'Certificate',     $pconfig['certcrt']))->setWidth(6);
	$certinfo[] = (new Form_Textarea('certkey', 'Certificate Key', $pconfig['certkey']))->setWidth(6);

	$sections =
		[
		[ 'Certificate Information' => $certinfo ],
		[ 'PKCS#12 Key-Pair File Upload' =>
			[
			new Form_Input('p12file', 'Key-Pair File',   'file',     null),
			new Form_Input('p12pass', 'Import Password', 'password', null)
			]]
		];


	$form = new Form();
	$form->setMultipartEncoding();

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
	$pgtitle[] = gettext($input_action);
	$helpurl   = "https://doc.pfsense.org/index.php/vHosts_package?";
	include("head.inc");

	if ($input_errors) {
		print_input_errors($input_errors);
	}

	display_top_tabs($pgtabs);

	print $form;

	include("foot.inc");

	/*----------------------------------------------------------*/
	/* Event handlers for dropping text files on crt/key input. */
	/*                                                          */
	/* Note: Add the dataTransfer property for use with the     */
	/* native `drop` event to capture information about files   */
	/* dropped into the browser window                          */
	/*----------------------------------------------------------*/
	?><script type="text/javascript">
		jQuery.event.props.push("dataTransfer");

		$('textarea#certcrt, textarea#certkey').on('drop', function(e) {
			e.preventDefault();

			var reader    = new FileReader();
			reader.onload = function(re) {
				e.target.value = re.target.result;
			}

			reader.readAsText(e.dataTransfer.files[0]);
			return false;
		});
		$('textarea#certcrt, textarea#certkey').on('dragover', function(e) {
			e.preventDefault();
			e.dataTransfer.dropEffect = 'copy'
			return false;
		});
	</script><?php
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
			<table class="table table-striped table-hover">
				<thead>
					<tr>
						<th><?=gettext("Common Name")?></th>
						<th><?=gettext("Valid From")?></th>
						<th><?=gettext("Valid Until")?></th>
						<th><?=gettext("Used By")?></th>
					</tr>
				</thead>
				<tbody>
				<?php

				for ($i=0; $i < count($a_vcerts); $i++) {
					$refid      = $a_vcerts[$i]['refid'];
					$crt        = openssl_x509_parse(base64_decode($a_vcerts[$i]['certcrt']));
					$commonName = htmlspecialchars($crt['subject']['CN']);
					$validFrom  = htmlspecialchars(date('r', $crt['validFrom_time_t']));
					$validUntil = htmlspecialchars(date('r', $crt['validTo_time_t']));

					$vhosts     = $config['installedpackages']['vhosts']['config'];
					$vhosts     = array_filter($vhosts, function($vhost) use ($refid) { return ($vhost['certref'] == $refid); });
					$usedby     = join('<br/>', array_column($vhosts, 'description'));

					?>
					<tr>
						<td><?=$commonName?></td>
						<td><?=$validFrom?></td>
						<td><?=$validUntil?></td>
						<td><?=$usedby?></td>
						<td>
							<a class="fa fa-pencil"	     title="<?=gettext('Edit')?>"                     href="?act=chg&amp;id=<?=$i?>"></a>
							<a class="fa fa-certificate" title="<?=gettext('Show Certificate Details')?>" href="?act=show&amp;id=<?=$i?>" rel="details" cn="<?=$commonName?>"></a>

							<?php if (!$usedby) { ?>
								<a class="fa fa-trash"	 title="<?=gettext('Delete this Certificate')?>"  href="?act=del&amp;id=<?=$i?>"></a>
							<?php } ?>
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
		<?=gettext('<p>This is the list of SSL/TLS certificates that can be assigned to a Host. '.
		           'After adding or updating a certificate, <b>restart the service to apply the settings</b>.</p>'); ?>
	</div>

	<?php
	/*--------------------------------------------------*/
	/* Modal dialog for display of certificate details. */
	/*--------------------------------------------------*/
	?>
	<div id="details-modal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
					<h4 class="modal-title"></h4>
				</div>
				<div class="modal-body" style="height:400px; overflow:auto; white-space: pre; font-family: monospace"></div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				</div>
			</div>
		</div>
	</div>

	<?php include("foot.inc");

	/*----------------------------------------------------------------*/
	/* Event handler for loading certificate details to modal dialog. */
	/*----------------------------------------------------------------*/
	?><script type="text/javascript">
		$('a[rel=details]').on('click', function(evt) {
			evt.preventDefault();
			var modal = $('#details-modal').modal();
			modal.find('.modal-title').text($(this).attr('cn'));
			modal.find('.modal-body').load($(this).attr('href'), function(responseText, textStatus) {
				if (textStatus === 'success' || textStatus === 'notmodified') {
					modal.show();
				} else {
					modal.find('.modal-body').empty();
				}
			});
		});
	</script><?php
}
?>
