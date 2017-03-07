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
*    Toggles Disabled flag for host 'n'.
*
* POST: 'id' = n (optional), 'srcid' = n (optional)
*   Adds or updates host config, updates web config, and drops list page.
*   If 'id' is empty but 'srcid' has a value, the new copy is inserted 
*   after the source host ID so it appears next in the list. 
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
* Writes out the configuration, updates the config files, and reloads page. If
* the service is running, the subsystem is marked 'dirty' and a warning message
* to restart the service is shown on the list page. 'dirty' mark is cleared when
* the service starts as declared in the <startcmd> of the config file.
*******************************************************************************/
function save_config_and_exit()
    {
    global $vhosts_g;
    
    write_config();
    vhosts_build_service_config();
    
    if (vhostsd_is_running())
        vhosts_dirty(TRUE);
        
    header("Location: ?");
    exit;
    }    

/*******************************************************************************
* Main Line
*******************************************************************************/
$a_vhosts  = &$config['installedpackages']['vhosts']['config'];
$act       = $_GET['act'];
$id        = $_GET['id'];
$good_id   = (isset($id) && isset($a_vhosts[$id]));

/*----------------------------------*/
/* Add/Copy/Update - Validate input */
/* and save changes to config.      */
/*----------------------------------*/
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
        /*-------------------------------*/
        /* Edit - Update the host by ID. */
        /*-------------------------------*/
        if ($good_id)
            $a_vhosts[$id] = $pconfig;
        
        /*-------------------------------------------------------*/
        /* Copy - Add new host copy after copied host source ID. */
        /*-------------------------------------------------------*/
        else if (($id = $_GET['srcid']) != null && isset($a_vhosts[$id]))
            array_splice($a_vhosts, $id+1, 0, [$pconfig]);
            
        /*--------------------------------*/
        /* Add - Add new host to the end. */
        /*--------------------------------*/
        else        
            $a_vhosts[] = $pconfig;
            
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
    
/*---------------------------------------------------*/
/* Edit/Copy - Load vhost configuration and display. */
/*---------------------------------------------------*/
elseif ($good_id && ($act == 'chg' || $act == 'dup')) 
    {
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
        
    /*---------------------------------------------------*/
    /* For Edit, set the form action to return the ID.   */
    /* For Copy, set the action to return the source ID. */
    /*---------------------------------------------------*/
    if ($act == 'chg')
        {
        $input_action = 'Edit';
        $form_action  = '?id='.$id;
        }
    else
        {
        $input_action = 'Add';
        $form_action  = '?srcid='.$id;
        }
    }
    
/*------------------------------------*/
/* Add - Display input for new vHost. */
/*------------------------------------*/
elseif ($act == 'add')
    {
    $input_action    = 'Add';
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
    foreach($config['cert'] as $cert)
        if (cert_get_purpose($cert['crt'], true)['server'] == 'Yes')
            $certlist[$cert['refid']] = $cert['descr'];
        
    /*---------------------------------------*/
    /* Set the page title and drop the form. */
    /*---------------------------------------*/
    $pgtitle = array(gettext("Services"), gettext("vHosts"), gettext($input_action));
    
    /*-----------------------------------------------------*/
    /* Build form. Save button auto set as default submit. */
    /*-----------------------------------------------------*/
    $help = 
        [
        'disabled'   => gettext('Set this option to disable this server.'),
        'name'       => gettext("Required. Document root directory name in {$vhosts_g['root_base_path']}."),
        'ipaddress'  => gettext('Required. Make sure the IP and Port combination does not conflict with the local system.'),
        'port'       => gettext('Required. Make sure the IP and Port combination does not conflict with the local system.'),
        'hostname'   => gettext('Name in Host Header for Name-Based Host. Not required for IP-Based host.'),
        'certref'    => gettext('SSL certificate for SSL/TLS connection.  See: <a href="/system_camanager.php">System &gt; Cert. Manager</a>')
        ];
        
    $sections = 
        [
        [ 'Host Information' =>
            [
            new Form_Checkbox('disabled',    'Disabled',           'Disable this server', $pconfig['disabled']),        
            new Form_Input   ('name',        'Directory Name',     'text',                $pconfig['name']),
            new Form_Input   ('ipaddress',   'IP Address',         'text',                $pconfig['ipaddress']),
            new Form_Input   ('port',        'Port',               'number',              $pconfig['port'],       ['min' => '0']),
            new Form_Input   ('hostname',    'Host Name',          'text',                $pconfig['hostname']),
            new Form_Input   ('description', 'Description',        'text',                $pconfig['description'])
            ]],                                                     
        [ 'Secure Connection' =>                                   
            [                                                      
            new Form_Select  ('certref',     'Server certificate',                        $pconfig['certref'],    $certlist)
            ]]
        ];
        
    $form = new Form();
   
    /*-----------------------------*/
    /* Set the form action if any. */
    /*-----------------------------*/
    if (isset($form_action))
        $form->setAction($form_action);
    
    foreach($sections as $s)
        foreach($s as $title => $fields)
            {
            $section = new Form_Section($title);
            
            foreach($fields as $field)
                $section->addInput($field->setHelp($help[$field->getName()] ?: null));
                
            $form->add($section);
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
    
    if ($input_errors)
        print_input_errors($input_errors);
        
    print $form;
    }

/*******************************************************************************
* List Page
*******************************************************************************/
else
    {
    $pgtitle          = array(gettext("Services"), gettext("vHosts"));
    $shortcut_section = $vhosts_g['subsystem_name'];
    $vhostsd_running  = vhostsd_is_running();
    
    include("head.inc");
    
    if (vhosts_dirty())
        print_info_box(gettext('vHosts configuration has been changed. Restart service to apply changes.'));
    
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
                    $hostlink  = ($disabled || !$vhostsd_running ? $htmlname : "<a href='$hosturl'>$htmlname</a>");
                    
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
                            <a class="fa fa-pencil"    title="<?=gettext('Edit')?>"   href="?act=chg&amp;id=<?=$i?>"></a>
                            <a class="fa fa-clone"     title="<?=gettext('Copy')?>"   href="?act=dup&amp;id=<?=$i?>"></a>
                            
                            <a class="fa fa-<?=($disabled ? 'check-square-o' : 'ban')?>" 
                               title="<?=gettext($disabled ? "Enable" : "Disable")?>" 
                                href="?act=tog&amp;id=<?=$i?>"></a>
                                
                            <a class="fa fa-trash"    title="<?=gettext('Delete this host')?>" href="?act=del&amp;id=<?=$i?>"></a>
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
                    'of the <b>nginx</b> web server that is already installed and uses PHP5 in FastCGI mode. '.
                    "The document root folder of a vHost configuration is <b>{$vhosts_g['root_base_path']}/</b><i>Name</i>. Files can be ".
                    'copied to the root folder using <b>Diagnostics->Edit File</b> or, </b>to allow file transfer using <b>SCP</b> or <b>SFTP</b>, '.
                    'enable SSH from <b>System->Advanced->Enable Secure Shell</b>.</p>'.
                    '<p>After adding or updating a vHost configuration, restart the service to apply the settings.</p>'); ?>
    </div>
    
    <?php
    }
    
include("foot.inc");
?>
    
    
    
    