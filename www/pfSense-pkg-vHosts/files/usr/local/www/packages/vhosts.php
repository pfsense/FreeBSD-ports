<?php
/*******************************************************************************
* vHosts package configuration page. Drops a list of the configured virtual
* hosts or a form page for host configuration. When a form is posted back, the
* host information is saved and the server config file is rebuilt.
*
* GET: No parameters: 
*   Drops list of configured vhosts with options to add/change/delete.
*
* GET: 'act' = 'add'
*   Drops form for input of new host settings.
*
* GET: 'act' = 'chg', 'id' = n
*   Drops form for update of host 'n' config.
*
* GET: 'act' = 'del', 'id' = n
*   Removes host 'n' config, update web config, and drops list page.
*
* GET: 'act' = 'tog', 'id' = n
*	Toggles Disabled flag for host 'n'.
*
* POST: 'id' = n (optional)
*   Adds or updates host config, updates web config, and drops list page.
* ------------------------------------------------------------------------------
* Part of pfSense 2.3 and later (https://www.pfSense.org/).
*
* Copyright (c) 2016 Rubicon Communications, LLC (Netgate)
* Licensed under the Apache 2.0 License.
*
* Copyright (C) 2016 Softlife Consulting
* Licensed under the same terms and conditions as pfSense itself.
*
* License: https://github.com/pfsense/pfsense/blob/master/LICENSE
*******************************************************************************/
require_once("guiconfig.inc");
require_once("util.inc");
require("/usr/local/pkg/vhosts.inc");

/*******************************************************************************
* save_config_and_exit */
/**
* Writes out the configuration, updates the config files, and reloads page.
*******************************************************************************/
function save_config_and_exit()
	{
	write_config();
	vhosts_sync_package();
	mark_subsystem_dirty('vhosts');
	header("Location: ?");
	exit;
	}	

/*******************************************************************************
* Main Line
*******************************************************************************/
$a_vhosts  = &$config['installedpackages']['vhosts']['config'];
$a_cert    =  $config['cert'];
$act       = $_GET['act'];
$id        = ($_POST ? $_POST['id']  : $_GET['id']);
$good_id   = (isset($id) && $a_vhosts[$id]);
$me        = basename(__FILE__);

/*---------------------------------------------------------*/
/* Add/Update - Validate input and save changes to config. */
/*---------------------------------------------------------*/
if ($_POST)
	{
	$pconfig['name']        = trim($_POST['name']);
	$pconfig['ipaddress']   =      $_POST['ipaddress'];
	$pconfig['port']        =      $_POST['port'];
	$pconfig['hostname']    = trim($_POST['hostname']);
	$pconfig['certref']     =      $_POST['certref'];
	$pconfig['description'] = trim($_POST['description']);
	
	if ($_POST['disabled'] == 'yes')
		$pconfig['disabled'] = true;
    
	/*---------------------------------------*/
	/* Validate the input and update config. */
	/*---------------------------------------*/
	unset($input_errors);

	if (empty($pconfig['name']))
		$input_errors[] = gettext('Name is required.');
	  
	if (!is_ipaddrv4($pconfig['ipaddress']))
		$input_errors[] = gettext('IP Address is not a valid IPv4 address.');
	 
	if (!is_numeric($pconfig['port']))
		$input_errors[] = gettext('Port must be a number.');
	
	if (!$input_errors)
		{
	    if ($good_id)
	    	$a_vhosts[$id] = $pconfig;
		else
			$a_vhosts[]    = $pconfig;
			
		save_config_and_exit();	
		}
			
	/*-------------------------------------------------*/
	/* Validation failed, redisplay input with errors. */
	/*-------------------------------------------------*/
	else
		$input_action = ($good_id ? 'Edit' : 'Add');
	}

/*--------------------------*/
/* Toggle enabled/disabled. */
/*--------------------------*/
elseif ($good_id && $act == 'tog')
	{
	if (isset($a_vhosts[$id]['disabled']))
		unset($a_vhosts[$id]['disabled']);
	else
		$a_vhosts[$id]['disabled'] = true;

	save_config_and_exit();
	}
		
/*--------------------------------------*/
/* Delete - Remove vhost configuration. */
/*--------------------------------------*/
elseif ($good_id && $act == 'del')
	{	
	unset($a_vhosts[$id]);
	save_config_and_exit();
	}
	
/*----------------------------------------------*/
/* Edit - Load vhost configuration and display. */
/*----------------------------------------------*/
elseif ($good_id && $act == 'chg') 
	{
	$input_action = 'Edit';
	
	$pconfig['disabled']    = isset($a_vhosts[$id]['disabled']);
	$pconfig['name']        = $a_vhosts[$id]['name'];
	$pconfig['ipaddress']   = $a_vhosts[$id]['ipaddress'];
	$pconfig['port']        = $a_vhosts[$id]['port'];
	$pconfig['hostname']    = $a_vhosts[$id]['hostname'];
	$pconfig['certref']     = $a_vhosts[$id]['certref'];
	$pconfig['description'] = $a_vhosts[$id]['description'];
	
	/*-------------------------------------------------*/
	/* Verify the selected certificate is still valid. */
	/*-------------------------------------------------*/
	if (!empty(trim($pconfig['certref']))) 
		{
		$thiscert = lookup_cert($pconfig['certref']);
		$purpose  = ($thiscert ? cert_get_purpose($thiscert['crt'], true) : null);
		
		if (!$thiscert || !$purpose['server'] == 'Yes')
			$pconfig['certref'] = '';
		}
	}
	
/*------------------------------------*/
/* Add - Display input for new vHost. */
/*------------------------------------*/
elseif ($act == 'add')
	{
	$input_action   = 'Add';
	$pconfig['port'] = '8000';
	}

/*******************************************************************************
* User Input Form
*******************************************************************************/
if (isset($input_action))
	{
	/*-------------------------*/
	/* Build certificate list. */
	/*-------------------------*/
	$certlist[''] = 'None';
	foreach($a_cert as $cert)
		if (cert_get_purpose($cert['crt'], true)['server'] == 'Yes')
			$certlist[$cert['refid']] = $cert['descr'];
		
	/*---------------------------------------*/
	/* Set the page title and drop the form. */
	/*---------------------------------------*/
	$pgtitle = array(gettext("Services"), gettext("vHosts"), gettext($input_action));
	include("head.inc");
	
	if ($input_errors)
		print_input_errors($input_errors);
	
	$form    = new Form();
	$section = new Form_Section('Host Information');
	
	$section->addInput(new Form_Checkbox('disabled',     'Disabled', 'Disable this server', $pconfig['disabled']))
		->setHelp('Set this option to disable this server.');
		
	$section->addInput(new Form_Input    ('name',   'Directory Name', 'text', $pconfig['name']))
		->setHelp('Required. Document root directory name in /usr/local/vhosts.');
		
	$section->addInput(new Form_Input    ('ipaddress',   'IP Address', 'text', $pconfig['ipaddress']))
		->setHelp('Required. Make sure the IP and Port combination does not conflict with the local system.');
		
	$section->addInput(new Form_Input    ('port',        'Port', 'number', $pconfig['port'], ['min' => '0']))
		->setHelp('Required. Make sure the IP and Port combination does not conflict with the local system.');
		
	$section->addInput(new Form_Input    ('hostname',    'Host Name', 'text', $pconfig['hostname']))
		->setHelp('Name in Host Header for Name-Based Host. Not required for IP-Based host.');
		 
	$section->addInput(new Form_Select   ('certref',	'Server certificate', $pconfig['certref'], $certlist))
		->setHelp(gettext('SSL certificate for secure connection. One may be created here: <a href="/system_camanager.php">System &gt; Cert. Manager</a>'));
	
	$section->addInput(new Form_Input    ('description',  'Description', 'text', $pconfig['description']));
	
	if ($good_id) 
		$section->addInput(new Form_Input('id',	 null, 'hidden', $id));
		
	$form->add($section);
	print $form;
	}

/*******************************************************************************
* List Page
*******************************************************************************/
else
	{
	$pgtitle          = array(gettext("Services"), gettext("vHosts"));
	$shortcut_section = 'vhosts';
	
	include("head.inc");
	
	if (is_subsystem_dirty('vhosts'))
		print_info_box(gettext('vHosts configuration has been changed. (Re)start service to apply changes.'));
	
	?>
	<div class="panel panel-default">
		<div class="panel-heading"></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap table-rowdblclickedit" data-sortable>
				<thead>
					<tr>
						<th><?=gettext("Name")?></th>
						<th><?=gettext("IP Address")?></th>
						<th><?=gettext("Port")?></th>
						<th><?=gettext("Host Name")?></th>
						<th><?=gettext("Secure")?></th>
						<th><?=gettext("Description")?></th>
					</tr>
				</thead>
				<tbody>
				<?php
			
				for ($i=0; $i < count($a_vhosts); $i++)
					{
					$vhost     = $a_vhosts[$i];
					$disabled  = isset($vhost['disabled']);
					$name      = $vhost['name'];
					$hostname  = $vhost['hostname'];
					$ipaddress = $vhost['ipaddress'];
					$port      = $vhost['port'];
					$ssl       = lookup_cert($vhost['certref']);
					
					/*-------------------------------------------------*/
					/* Build link to vhost around host name. Use first */
					/* host listed in 'hostname' or 'ipaddress:port'.  */
					/*-------------------------------------------------*/
					$htmlname  = htmlspecialchars($name);
					$hosturl   = ($ssl ? 'https' : 'http')."://".($hostname ? explode(' ', $hostname)[0] : $ipaddress).":$port";
					$hostlink  = ($disabled ? $htmlname : "<a href='$hosturl'>$htmlname</a>");
					
					if ($disabled)
						{
						$toggle_text  = gettext('Enable');
						$toggle_class = 'fa fa-check-square-o';
						}
					
					?>
					<tr <?=$disabled ? 'class="disabled"':''?>>
						<td><?=$hostlink?></td>
						<td><?=htmlspecialchars($vhost['ipaddress'])?></td>
						<td><?=htmlspecialchars($vhost['port'])?></td>
						<td><?=htmlspecialchars($vhost['hostname'])?></td>
						<td><?=($ssl ? 'Yes' : 'No')?></td>
						<td><?=htmlspecialchars($vhost['description'])?></td>
						<td>
							<a class="fa fa-pencil"	title="<?=gettext('Edit')?>"   href="?act=chg&amp;id=<?=$i?>"></a>
							
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
	
	<nav class="action-buttons">
		<a href="?act=add" class="btn btn-sm btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
			<?=gettext("Add")?>
		</a>
	</nav>
	
	<div class="infoblock">
		<div class="alert alert-info clearfix" role="alert">
			<?=gettext('vHosts is a web server package that can host HTML, Javascript, CSS, and PHP. It creates another instance of the '.
						'nginx web server that is already installed. It uses PHP5 in FastCGI mode and has access to PHP Data Objects '.
						'and PDO SQLite. To use SFTP, enable SSH from <b>System->Advanced->Enable Secure Shell</b>. Then SFTP can be used '.
						'to access the files at <b>/usr/local/vhosts</b>. After adding or updating an entry make sure to restart the '.
						'<a href="/status_services.php">service</a> to apply the settings.'); ?>
		</div>
	</div>
	
	<?php
	}
	
include("foot.inc");
?>
	
	
	
	