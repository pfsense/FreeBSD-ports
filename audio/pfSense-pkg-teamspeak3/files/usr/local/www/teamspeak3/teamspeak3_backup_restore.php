<?php
/* $Id$ */
/* ========================================================================== */
/*
	teamspeak3_backup_restore.php
	Copyright (C) 2016 Sander Peterse
	All rights reserved.
                                                                              */
/* ========================================================================== */
/*
	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1.	Redistributions of source code must retain the above copyright notice,
		this list of conditions and the following disclaimer.

	2.	Redistributions in binary form must reproduce the above copyright
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
/* ========================================================================== */

require_once("/usr/local/pkg/teamspeak3.inc");
require_once("guiconfig.inc");

$sActionMessage = "";
if(isset($_POST['download_ts3server_sqlitedb']))
{
	if(teamspeak3_download_backup(TEAMSPEAK3_BACKUP_TYPE_SQLITEDB, $sActionMessage))
	{
		exit();
	}
}
else if(isset($_POST['download_ts3server_ini']))
{
	if(teamspeak3_download_backup(TEAMSPEAK3_BACKUP_TYPE_INI, $sActionMessage))
	{
		exit();
	}
}
else if(isset($_POST['download_ts3filebrowser']))
{
	if(teamspeak3_download_backup(TEAMSPEAK3_BACKUP_TYPE_FILES, $sActionMessage))
	{
		exit();
	}
}	
else if(isset($_POST['ts3_restore']))
{	
	if(!empty($_FILES['file_ts3server_sqlitedb']['tmp_name']))
	{
		teamspeak3_restore_backup(TEAMSPEAK3_BACKUP_TYPE_SQLITEDB, $_FILES['file_ts3server_sqlitedb'], $sActionMessage, "<strong>Database:</strong> ");
	}
	
	if(!empty($_FILES['file_ts3server_ini']['tmp_name']))
	{
		teamspeak3_restore_backup(TEAMSPEAK3_BACKUP_TYPE_INI, $_FILES['file_ts3server_ini'], $sActionMessage, "<strong>Configuration:</strong> ");
	}
	
	if(!empty($_FILES['file_ts3filebrowser']['tmp_name']))
	{
		teamspeak3_restore_backup(TEAMSPEAK3_BACKUP_TYPE_FILES, $_FILES['file_ts3filebrowser'], $sActionMessage, "<strong>Files:</strong> ");
	}
}

$pgtitle = array(gettext("Package"), gettext("TeamSpeak 3"), gettext("Backup &amp; Restore"));
include("head.inc");	

if(!empty($sActionMessage))
{
	print_info_box($sActionMessage);
}

$tab_array = array();
$tab_array[] = array(gettext("Settings"), false, "/pkg_edit.php?xml=teamspeak3.xml&amp;id=0");
$tab_array[] = array(gettext("Logs"), false, "/teamspeak3/teamspeak3_show_logs.php");
$tab_array[] = array(gettext("Backup &amp; Restore"), true, "/teamspeak3/teamspeak3_backup_restore.php");				
display_top_tabs($tab_array);				

// Form
$form = new Form(false);
$form->setMultipartEncoding();

// Backup section...
$section = new Form_Section('Backup TeamSpeak 3');

// Database backup group.
$group = new Form_Group('Database');
$group->add(new Form_Button(
		'download_ts3server_sqlitedb',
		'Download',
		null,
		'fa-download'
		))->removeClass('btn-default')->addClass('btn-success btn-sm')
		->setHelp('The TeamSpeak3 server SQLite database (ts3server.sqlitedb) will be downloaded. This will restart the TeamSpeak 3 service.');
$section->add($group);

// Configuration backup group.
$group = new Form_Group('Configuration');
$group->add(new Form_Button(
		'download_ts3server_ini',
		'Download',
		null,
		'fa-download'
		))->removeClass('btn-default')->addClass('btn-success btn-sm')
		->setHelp('The TeamSpeak3 server configuration file (ts3server.ini) will be downloaded.');
$section->add($group);	

// Files backup group.
$group = new Form_Group('Files');
$group->add(new Form_Button(
		'download_ts3filebrowser',
		'Download',
		null,
		'fa-download'
		))->removeClass('btn-default')->addClass('btn-success btn-sm')
		->setHelp('All files uploaded to the TeamSpeak3 server will be downloaded as an archive (tar.gz).');
$section->add($group);

// Section
$form->add($section);

// Restore section.
$section = new Form_Section('Restore TeamSpeak 3');

// Database restore group.
$group = new Form_Group('Database');
$group->add(new Form_Input(
        'file_ts3server_sqlitedb',
        'Database file',
        'file',
        null
))->setHelp('This will overwrite the current TeamSpeak 3 server database file (ts3server.sqlitedb) and restart the TeamSpeak 3 service.');
$section->add($group);

// Configuration restore group.
$group = new Form_Group('Configuration');
$group->add(new Form_Input(
        'file_ts3server_ini',
        'Configuration file',
        'file',
        null
))->setHelp('This will overwrite the current TeamSpeak 3 server configuration file (ts3server.ini) and restart the TeamSpeak 3 service.');
$section->add($group);

// Files restore group.
$group = new Form_Group('Files');
$group->add(new Form_Input(
        'file_ts3filebrowser',
        'Files archive',
        'file',
        null
))->setHelp('This will overwrite all files currently stored on your TeamSpeak 3 server and restart the TeamSpeak 3 service.');
$section->add($group);

// Restore button.
$group = new Form_Group('');
$group->add(new Form_Button(
		'ts3_restore',
		'Restore the database, configuration and/or files',
		null,
		'fa-undo'
		))->addClass('btn-danger restore')->setAttribute('id');
$section->add($group);

// Section
$form->add($section);

// Form
print $form;

include("foot.inc");
?>
