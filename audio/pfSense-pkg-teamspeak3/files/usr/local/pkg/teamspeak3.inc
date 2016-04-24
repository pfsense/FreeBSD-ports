<?php
/* $Id$ */
/* ========================================================================== */
/*
	teamspeak3.inc
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

/**
* Includes
*/
require_once('util.inc');
require_once('filter.inc');
require_once('interfaces.inc');
require_once('pfsense-utils.inc');
require_once('service-utils.inc');

/**
* Constants
*/
const TEAMSPEAK3_SERVICE_NAME = "teamspeak";
const TEAMSPEAK3_SERVICE_SCRIPT = "teamspeak.sh";
const TEAMSPEAK3_FILEOWNER_USER = "teamspeak";
const TEAMSPEAK3_FILEOWNER_GROUP = "teamspeak";
const TEAMSPEAK3_CONFIG_FILE = "/usr/local/etc/teamspeak/ts3server.ini";
const TEAMSPEAK3_DB_FILE = "/var/db/teamspeak/ts3server.sqlitedb";
const TEAMSPEAK3_LOGS_DIRECTORY = "/var/log/teamspeak";
const TEAMSPEAK3_FILES_DIRECTORY = "/var/db/teamspeak/files";
const TEAMSPEAK3_BACKUP_TYPE_SQLITEDB = "sqlitedb";
const TEAMSPEAK3_BACKUP_TYPE_INI = "ini";
const TEAMSPEAK3_BACKUP_TYPE_FILES = "files";

/**
 * Make sure the correct shortcuts are shown on the TeamSpeak pages.
 */
$shortcut_section = "teamspeak3";

/**
 * All package functions.
 */
function teamspeak3_custom_php_install_command()
{
	write_rcfile(array(
		"file" => TEAMSPEAK3_SERVICE_SCRIPT,
		"start" => "/usr/local/etc/rc.d/" . TEAMSPEAK3_SERVICE_NAME . " onestart",
		"stop" => "/usr/local/etc/rc.d/" . TEAMSPEAK3_SERVICE_NAME . " onestop"
	));
	
	teamspeak3_custom_php_resync_command();
}

function teamspeak3_custom_php_resync_command()
{
	global $config;

	teamspeak3_write_server_config();	
	filter_configure();
}

function teamspeak3_custom_php_pre_deinstall_command()
{
	global $config;
		
	conf_mount_rw();
		
	stop_service(TEAMSPEAK3_SERVICE_NAME);	
	unlink_if_exists("/usr/local/etc/rc.d/" . TEAMSPEAK3_SERVICE_SCRIPT);
	
	if($config['installedpackages']['teamspeak3']['config'][0]['keepfiles'] != 'on')
	{
		// Remove the configuration from the PFSense config file.
		unset($config['installedpackages']['teamspeak3']);
		
		// Note: this doesn't delete the TeamSpeak 3 database, server ini and uploaded 
		// files as that's managed by the FreeBSD teamspeak3-server package/port.
	}
	
	write_config();
	conf_mount_ro();
	
	// Make sure we remove our auto-created firewall rules.
	update_status("Reconfiguring filter...");
	filter_configure();
}

function teamspeak3_generate_rules($sType)
{
	global $config;
	
	if(!($config['installedpackages']['teamspeak3']['config'][0]['createrule'] == 'on'))
	return "";
	
	$aInterfaces = explode(',', $config['installedpackages']['teamspeak3']['config'][0]['serverinterfaces']);
	$aInterfaces = array_map('convert_friendly_interface_to_real_interface_name', $aInterfaces);
	
	$nVoicePort = (int)teamspeak3_get_config_value('default_voice_port');
	if(empty($nVoicePort)) $nVoicePort = 9987;
	
	$nFilePort = (int)teamspeak3_get_config_value('filetransfer_port');
	if(empty($nFilePort)) $nFilePort = 30033;	
	
	$sRules = "# TeamSpeak 3 package" . PHP_EOL;
	switch($sType) 
	{
	case 'nat':
		break;
	case 'filter':
	case 'rule':
		foreach ($aInterfaces as $sInterface)
		{
			if($sInterface != "lo0")
			{
				$sRules .= "pass quick on {$sInterface} proto udp to port {$nVoicePort} keep state label \"TEAMSPEAK3: Voice\"" . PHP_EOL;
				$sRules .= "pass quick on {$sInterface} proto tcp to port {$nFilePort} flags S/SA keep state label \"TEAMSPEAK3: Data\"" . PHP_EOL;						
			}
		}
		break;
	default:
		break;
	}
	
	return $sRules;
}

function teamspeak3_write_server_config()
{
	global $config;
	
	conf_mount_rw();

	// Update server ini config when param has been set.
	if(!isset($config['installedpackages']['teamspeak3']['config']))
	{
		$config['installedpackages']['teamspeak3']['config'] = array();

		// Apply default package settings
		$sDefaultConfig = "";
		$sDefaultConfig .= "machine_id=" . PHP_EOL;
		$sDefaultConfig .= "default_voice_port=9987" . PHP_EOL;
		$sDefaultConfig .= "voice_ip=0.0.0.0" . PHP_EOL;
		$sDefaultConfig .= "filetransfer_port=30033" . PHP_EOL;
		$sDefaultConfig .= "filetransfer_ip=0.0.0.0" . PHP_EOL;
		$sDefaultConfig .= "query_port=10011" . PHP_EOL;
		$sDefaultConfig .= "query_ip=0.0.0.0" . PHP_EOL;
		$sDefaultConfig .= "query_ip_whitelist=query_ip_whitelist.txt" . PHP_EOL;
		$sDefaultConfig .= "query_ip_blacklist=query_ip_blacklist.txt" . PHP_EOL;
		$sDefaultConfig .= "dbplugin=ts3db_sqlite3" . PHP_EOL;
		$sDefaultConfig .= "dbpluginparameter=" . PHP_EOL;
		$sDefaultConfig .= "dbconnections=10" . PHP_EOL;
		$sDefaultConfig .= "logquerycommands=0" . PHP_EOL;
		$sDefaultConfig .= "dbclientkeepdays=30" . PHP_EOL;
		$sDefaultConfig .= "logappend=0" . PHP_EOL;

		$config['installedpackages']['teamspeak3']['config'][0] = array(
			'ts3serverconfig' => base64_encode($sDefaultConfig)
		);
	}
	
	// Apply interface setting to configuration file.
	$sConfigFileData = base64_decode($config['installedpackages']['teamspeak3']['config'][0]['ts3serverconfig']);
	
	// Get array of interface IPs on where the teamspeak service should listen.
	$aSelectedInterfaceIPs = array();	
	if(!empty($config['installedpackages']['teamspeak3']['config'][0]['serverinterfaces']))
	{
		$aSelectedInterfaces = explode(',', $config['installedpackages']['teamspeak3']['config'][0]['serverinterfaces']);
		if(count($aSelectedInterfaces) > 0)
		{	
			foreach($aSelectedInterfaces as $sInterface)
			{
				$sInterface = convert_friendly_interface_to_real_interface_name($sInterface);
				$aSelectedInterfaceIPs[] = find_interface_ip($sInterface);
			}
		}	
	}
	
	// Create string of the selected interfaces or use LOOPBACK when no interfaces are selected.
	if(count($aSelectedInterfaceIPs) > 0) 
	{ 
		//$sSelectedInterfaceValue = implode(', ', $aSelectedInterfaceIPs);
		// Teamspeak currently supports only 1 interface per virtual server, so get the first element.
		$sSelectedInterfaceValue = $aSelectedInterfaceIPs[0];
	}
	else
	{
		$config['installedpackages']['teamspeak3']['config'][0]['serverinterfaces'] = "lo0";
		$sInterface = convert_friendly_interface_to_real_interface_name("lo0");
		$sSelectedInterfaceValue = find_interface_ip($sInterface);
	}
	
	// Update the configure file by replacing the values for each IP param.
	$aParamsToReplaceWithInterfaceValue = array('voice_ip', 'filetransfer_ip', 'query_ip');
	
	$sParsedConfigFileData = "";
	$aConfigLines = explode(PHP_EOL, $sConfigFileData);	
	foreach($aConfigLines as $sConfigLine)
	{
		if(!empty($sParsedConfigFileData)) $sParsedConfigFileData .= PHP_EOL;
		
		$aConfigKeyValuePair = explode('=', $sConfigLine, 2);		
		if(count($aConfigKeyValuePair) == 2
				&& in_array($aConfigKeyValuePair[0], $aParamsToReplaceWithInterfaceValue))
		{
			$sConfigLine = $aConfigKeyValuePair[0]."=".$sSelectedInterfaceValue;
		}
		$sParsedConfigFileData .= $sConfigLine;			
	}
	
	// Stop teamspeak service, update config (to disk) and restart it again.
	$bServiceRunning = is_service_running(TEAMSPEAK3_SERVICE_NAME);
	if($bServiceRunning) 
	{
		stop_service(TEAMSPEAK3_SERVICE_NAME);
	}
	
	// Write server config file to disk & fix owner.
	file_put_contents(
	TEAMSPEAK3_CONFIG_FILE, 
	$sParsedConfigFileData);
	teamspeak3_chown_file_or_directory(TEAMSPEAK3_CONFIG_FILE, false);	
	
	if($bServiceRunning) 
	{
		start_service(TEAMSPEAK3_SERVICE_NAME);
	}
		
	$config['installedpackages']['teamspeak3']['config'][0]['ts3serverconfig'] = base64_encode($sParsedConfigFileData);
	
	write_config();	
	conf_mount_ro();
}

function teamspeak3_chown_file_or_directory($sPath, $bRecursive)
{
	if(!file_exists($sPath)) return;

	$sChownCmd = "chown ";
	if($bRecursive) $sChownCmd .= "-R ";
	$sChownCmd .= TEAMSPEAK3_FILEOWNER_USER.":";
	$sChownCmd .= TEAMSPEAK3_FILEOWNER_GROUP." ";
	$sChownCmd .= $sPath;
	mwexec($sChownCmd);
}

function teamspeak3_get_config_value($sProperty)
{
	global $config;
	
	$sConfigFileData = base64_decode($config['installedpackages']['teamspeak3']['config'][0]['ts3serverconfig']);
	
	if(!empty($sConfigFileData))
	{
		$aConfigLines = explode(PHP_EOL, $sConfigFileData);
		foreach($aConfigLines as $sConfigLine)
		{
			$aConfigKeyValuePair = explode('=', $sConfigLine, 2);
			if(count($aConfigKeyValuePair) == 2 && $aConfigKeyValuePair[0] == $sProperty)
			{
				return $aConfigKeyValuePair[1];
			}
		}
	}
	
	return null;
}

function teamspeak3_get_all_log_files()
{
	$aLogFiles = glob(TEAMSPEAK3_LOGS_DIRECTORY . DIRECTORY_SEPARATOR . "*.log");		
	usort($aLogFiles, create_function('$a,$b', 'return filemtime($b) - filemtime($a);')); // Sort files array (DESC, new to old).
	
	$aLogFileames = array();
	foreach($aLogFiles as $aLogFile)
	{
		$aLogFileames[] = basename($aLogFile);
	}
	return $aLogFileames;
}

function teamspeak3_download_file($sFilePath, $sFilename, $b404OnError=true)
{
	// Send a 404 error to the browser when we can't find the file.
	if(!file_exists($sFilePath))
	{
		if($b404OnError)
		{
			header("HTTP/1.0 404 Not Found");
			echo "404 Not Found";
		}
		return;
	}
	
	// Make the file available for the browser (as a file download).
	$sContentType = (substr(strrchr($sFilePath,'.'),1) == 'sqlitedb') ? "application/x-sqlite3" : "text/plain";
	header("Content-Type: {$sContentType}");
	header("Content-Disposition: attachment; filename=\"{$sFilename}\"");
	echo file_get_contents($sFilePath);
}

function teamspeak3_download_log_file($sLogFile, $b404OnError=true)
{
	// Make sure it's a safe filename.
	// We don't want anyone downloading unexpected files by adding directories to the filename!!! E.g. changing it to /etc/passwd.
	if(strstr($sLogFile, DIRECTORY_SEPARATOR) !== false 
			|| strstr($sLogFile, '\\') !== false
			|| substr($sLogFile, -4) != '.log')
	{
		if($b404OnError)
		{
			header("HTTP/1.0 404 Not Found");
			echo "404 Not Found";
		}		
		return;
	}
	else
	{
		$sLogPath = TEAMSPEAK3_LOGS_DIRECTORY . DIRECTORY_SEPARATOR . $sLogFile;
	}
	
	teamspeak3_download_file($sLogPath, $sLogFile, $b404OnError);
}

function teamspeak3_download_backup($sBackupType, &$sMessage)
{
	if(!in_array($sBackupType, array(
					TEAMSPEAK3_BACKUP_TYPE_SQLITEDB,
					TEAMSPEAK3_BACKUP_TYPE_INI,
					TEAMSPEAK3_BACKUP_TYPE_FILES
					))) 
	{
		teamspeak3_add_message($sMessage, "Can't download backup, unsupported backup type.");
		return false;
	}
	
	// The ts3server.sqlitedb file requires the TeamSpeak 3 service to stop (and start again afterwards).
	// This because we have to make sure that all data has been flushed to the sqlite database before downloading it.
	$bShouldRestartService = ($sBackupType == TEAMSPEAK3_BACKUP_TYPE_SQLITEDB);

	if($sBackupType == TEAMSPEAK3_BACKUP_TYPE_INI)
	{
		$sFilePath = TEAMSPEAK3_CONFIG_FILE;
	}
	else if($sBackupType == TEAMSPEAK3_BACKUP_TYPE_SQLITEDB)
	{
		$sFilePath = TEAMSPEAK3_DB_FILE;
	}	
	else if($sBackupType == TEAMSPEAK3_BACKUP_TYPE_FILES)
	{		
		// Create an archive to allow downloading of the files.
		$sFilePath = teamspeak3_archive_file_browser();
	}
		
	if($bShouldRestartService)
	{
		$bServiceRunning = is_service_running(TEAMSPEAK3_SERVICE_NAME);
				
		if($bServiceRunning) 
		{
			stop_service(TEAMSPEAK3_SERVICE_NAME);
		}
	}
	
	teamspeak3_download_file($sFilePath, basename($sFilePath));
	if($sBackupType == TEAMSPEAK3_BACKUP_TYPE_FILES)
	{
		// Remove temporary archive file from the system.
		mwexec("/bin/rm -f ".$sFilePath);		
	}
	
	if($bShouldRestartService && $bServiceRunning) start_service(TEAMSPEAK3_SERVICE_NAME);
	return true;
}

function teamspeak3_restore_backup($sBackupType, $aFile, &$sMessage, $sMessagePrefix=null)
{
	global $config;
	
	if(!in_array($sBackupType, array(
					TEAMSPEAK3_BACKUP_TYPE_SQLITEDB, 
					TEAMSPEAK3_BACKUP_TYPE_INI, 
					TEAMSPEAK3_BACKUP_TYPE_FILES))) 
	{
		teamspeak3_add_message($sMessage, "Can't restore backup, unsupported backup type.", $sMessagePrefix);
		return;
	}
	
	if(empty($aFile) || $aFile['error'] != UPLOAD_ERR_OK)
	{
		teamspeak3_add_message($sMessage, "Can't restore backup due to an upload error. Please try again.", $sMessagePrefix);
		return false;
	}
	
	if($sBackupType == TEAMSPEAK3_BACKUP_TYPE_INI)
	{
		if(substr($aFile['name'], -4) != '.ini')
		{
			teamspeak3_add_message($sMessage, "Can't restore backup, the uploaded file should have the '.ini' extension.", $sMessagePrefix);				
			return false;
		}
	}
	else if($sBackupType == TEAMSPEAK3_BACKUP_TYPE_SQLITEDB)
	{
		if(substr($aFile['name'], -9) != '.sqlitedb')
		{
			teamspeak3_add_message($sMessage, "Can't restore backup, the uploaded file should have the '.sqlitedb' extension.", $sMessagePrefix);				
			return false;
		}
	}	
	else if($sBackupType == TEAMSPEAK3_BACKUP_TYPE_FILES)
	{
		if(substr($aFile['name'], -7) != '.tar.gz')
		{
			teamspeak3_add_message($sMessage, "Can't restore backup, the uploaded file should have the '.tar.gz' extension.", $sMessagePrefix);				
			return false;
		}
	}
	
	// Stop teamspeak service, write file (to disk) and restart it again.
	$bServiceRunning = is_service_running(TEAMSPEAK3_SERVICE_NAME);
	if($bServiceRunning) 
	{
		stop_service(TEAMSPEAK3_SERVICE_NAME);
	}
	
	$bStatus = false;
	if($sBackupType == TEAMSPEAK3_BACKUP_TYPE_INI)
	{
		$sTargetFilePath = TEAMSPEAK3_CONFIG_FILE;
		$bStatus = move_uploaded_file($aFile['tmp_name'], $sTargetFilePath);
	}
	else if($sBackupType == TEAMSPEAK3_BACKUP_TYPE_SQLITEDB)
	{
		$sTargetFilePath = TEAMSPEAK3_DB_FILE;
		$bStatus = move_uploaded_file($aFile['tmp_name'], $sTargetFilePath);
	}
	else if($sBackupType == TEAMSPEAK3_BACKUP_TYPE_FILES)
	{
		$sTargetFilePath = TEAMSPEAK3_FILES_DIRECTORY;
		mwexec("/bin/rm -Rf {$sTargetFilePath}");
		
		if(!file_exists($sTargetFilePath))
		{		
			$sExtractOutputPath = dirname($sTargetFilePath);
			mwexec("tar -zxf {$aFile['tmp_name']} -C {$sExtractOutputPath}");
			$bStatus = file_exists($sTargetFilePath);
			
			if($bStatus)
			{
				teamspeak3_chown_file_or_directory($sTargetFilePath, true);
			}
		}
	}
	
	if($bStatus)
	{
		teamspeak3_chown_file_or_directory($sTargetFilePath, false);	
		
		conf_mount_rw();		
		
		$config['installedpackages']['teamspeak3']['config'][0]['ts3serverconfig'] = base64_encode(file_get_contents(TEAMSPEAK3_CONFIG_FILE));
		write_config();
		
		conf_mount_ro();
		
		 teamspeak3_add_message($sMessage, "Successfully restored backup.", $sMessagePrefix);		
	}
	else 
	{
		teamspeak3_add_message($sMessage, "Can't restore backup, error while processing data!", $sMessagePrefix);
	}
	
	if($bServiceRunning)
	{
		start_service(TEAMSPEAK3_SERVICE_NAME);
	}
	
	return $bStatus;
}

function teamspeak3_archive_file_browser()
{
	$sDate = date("Ymd", time());
	$sTmpArchivePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "ts3files-{$sDate}.tar.gz";
	if(!is_dir(TEAMSPEAK3_FILES_DIRECTORY)) return null;
	
	mwexec("tar -zcf {$sTmpArchivePath} " . TEAMSPEAK3_FILES_DIRECTORY);
	return $sTmpArchivePath;
}

function teamspeak3_add_message(&$sMessage, $sNewMessage, $sMessagePrefix=null)
{
	if(!empty($sMessage))
	{
		$sMessage .= "<br/>" . PHP_EOL;
	}	
	
	if(!empty($sMessagePrefix))
	{
		$sMessage .= $sMessagePrefix;
	}
	
	$sMessage .= $sNewMessage;
}
?>
