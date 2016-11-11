<?php
/*******************************************************************************
* Support definitions for vHosts package.
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
require_once('service-utils.inc');

/*******************************************************************************
* Globals
*******************************************************************************/
global $vhosts_g;
$vhosts_g = [];
$vhosts_g['service_name']     = 'vhostsd';
$vhosts_g['conf_file_path']   = "/var/etc/{$vhosts_g['service_name']}.conf";
$vhosts_g['conf_file_prefix'] = "/var/etc/{$vhosts_g['service_name']}";
$vhosts_g['pid_file_path']    = "/var/run/{$vhosts_g['service_name']}.pid";
$vhosts_g['root_base_path']   = '/usr/local/vhosts';

/*-----------------------------------------*/
/* nginx web server config file template.  */
/*                                         */
/* @servers = Server configuration blocks. */
/*-----------------------------------------*/
$vhosts_g['conf'] = <<<CONF
pid {$vhosts_g['pid_file_path']};

user  root wheel;
worker_processes  2;
error_log  syslog:server=unix:/var/run/log,facility=local5;

events 
	{
    worker_connections  1024;
	}

http 
	{
	include       		/usr/local/etc/nginx/mime.types;
	default_type  		application/octet-stream;
	add_header 			X-Frame-Options SAMEORIGIN;
	server_tokens 		off;
	sendfile        	on;

	access_log      	syslog:server=unix:/var/run/log,facility=local5 combined;
	keepalive_timeout 	75;
	
	@servers
	}
CONF;

/*--------------------------------------------*/
/* nginx server config block template.        */
/*                                            */
/* @listen_conf = 'listen' directive(s).      */
/* @hostnames   = Host names.                 */
/* @ssl_conf    = Secure configuration block. */
/* @root_folder = Document root folder.       */
/*--------------------------------------------*/
$vhosts_g['conf_server'] = <<<CONF_SERVER
	server
		{
		@listen_conf
		server_name @hostnames;
		@ssl_conf
		
		client_max_body_size 200m;

		gzip on;
		gzip_types text/plain text/css text/javascript application/x-javascript text/xml application/xml application/xml+rss application/json;

		root @root_folder;
		
		location / { index  index.php index.html index.htm; }

		location ~ \.php$ 
			{
			try_files 				\$uri =404;	#  This line closes a potential security hole
												# ensuring users can't execute uploaded files
												# see: http://forum.nginx.org/read.php?2,88845,page=3
			fastcgi_pass   			unix:/var/run/php-fpm.socket;
			fastcgi_index  			index.php;
			fastcgi_param  			SCRIPT_FILENAME  \$document_root\$fastcgi_script_name;
			fastcgi_param  			HTTP_PROXY  ""; # Fix httpoxy - https://httpoxy.org/#fix-now
			fastcgi_read_timeout 	180;
			include        			/usr/local/etc/nginx/fastcgi_params;
			}
		}
CONF_SERVER;

/*-----------------------------------------*/
/* nginx server SSL config block template. */
/*                                         */
/* @crt_file_path = Certificate file path. */
/* @key_file_path = Private key file path. */
/*-----------------------------------------*/
$vhosts_g['conf_server_ssl'] = <<<CONF_SERVER_SSL
		ssl_certificate         				@crt_file_path;
		ssl_certificate_key     				@key_file_path;
		ssl_session_timeout     				10m;
		keepalive_timeout       				70;
		ssl_session_cache       				shared:SSL:10m;
		ssl_protocols   						TLSv1.1 TLSv1.2;
		ssl_ciphers 							"EECDH+AESGCM:EDH+AESGCM:AES256+EECDH:AES256+EDH";
		ssl_prefer_server_ciphers       		on;
		add_header Strict-Transport-Security	"max-age=31536000";
		add_header X-Content-Type-Options 		nosniff;
		ssl_session_tickets 					off;
		ssl_dhparam 							/etc/dh-parameters.4096;
CONF_SERVER_SSL;

/*******************************************************************************
* is_vhosts_running */
/**
* Returns true if the vHosts nginx server is running.
*******************************************************************************/
function is_vhosts_running() 
	{
	global $vhosts_g;
	
	return is_pid_running("{$vhosts_g['pid_file_path']}");
	}

/*******************************************************************************
* vhosts_deinstall_command */
/**
* Custom uninstall command to remove extra files created by package.
*******************************************************************************/
function vhosts_deinstall_command() 
	{
	global $vhosts_g;
	
	exec("/bin/rm -f {$vhosts_g['conf_file_prefix']}*");
	}

/*******************************************************************************
* vhosts_install_command */
/**
* Custom install command to create rcfile and build config file.
*******************************************************************************/
function vhosts_install_command() 
	{
	global $vhosts_g;
	
	$conf_dir  = dirname ($vhosts_g['conf_file_path']);
	$conf_file = basename($vhosts_g['conf_file_path']);
	
	write_rcfile(
		[
		"file"  => "vhostsd.sh",
		"start" => "/usr/local/sbin/nginx -p {$conf_dir}/ -c $conf_file",
		"stop"  => "kill `cat {$vhosts_g['pid_file_path']}`"
		]);

	vhosts_sync_package();
	}

/*******************************************************************************
* vhosts_sync_package */
/**
* Rebuilds nginx configuration for vhosts. Called for package synchronization
* and when any vhost is modified.
*******************************************************************************/
function vhosts_sync_package() 
	{
	global $config, $vhosts_g;
	
	$conf_file_path   = $vhosts_g['conf_file_path'];
	$conf_file_prefix = $vhosts_g['conf_file_prefix'];

	/*-------------------------------------*/
	/* Remove all existing configurations. */
	/*-------------------------------------*/
	conf_mount_rw();
	exec("/bin/rm -f $conf_file_prefix*");

	/*---------------------------------------*/
	/* Build 'server' section for each host. */
	/*---------------------------------------*/
	$servers = '';
	
	foreach ($config['installedpackages']['vhosts']['config'] as $vhost)
		{
		/*---------------------------*/
		/* Skip if host is disabled. */
		/*---------------------------*/
		if ($vhost['disabled'])
			continue;
			
		/*--------------------------------------------------*/
		/* Create the root folder and a default index page. */
		/*--------------------------------------------------*/
		$root_folder = "{$vhosts_g['root_base_path']}/{$vhost['name']}";
		
		safe_mkdir($root_folder);
		
		if (!file_exists("$root_folder/index.php"))
			file_put_contents("$root_folder/index.php", "<?php\necho phpinfo();\n?>\n");
		
		/*--------------------------------------*/
		/* Build SSL config if host has a cert. */
		/*--------------------------------------*/
		if (isset($vhost['certref']))
			{
			$cert = lookup_cert($vhost['certref']);
			$crt  = base64_decode($cert['crt']);
			$key  = base64_decode($cert['key']);
			
			/*----------------------------------------------------------------*/
			/* Create Cert/Key files and SSL config to add to Server section. */
			/*----------------------------------------------------------------*/
			if ($crt && $key)
				{
				$crt_file_path = "$conf_file_prefix-{$vhost['name']}.crt";
				$key_file_path = "$conf_file_prefix-{$vhost['name']}.key";
				file_put_contents($crt_file_path, $crt); 
				file_put_contents($key_file_path, $key);
				
				$ssl_conf = strtr($vhosts_g['conf_server_ssl'], 
					[ 
					'@crt_file_path' => $crt_file_path, 
					'@key_file_path' => $key_file_path 
					]);
				}
			}
			
		/*-----------------------------------------------------*/
		/* Add server section for this vhost. Set hostnames    */
		/* for 'server_name' catchall if no hostname provided. */
		/*-----------------------------------------------------*/
		$listen_conf = "listen {$vhost['ipaddress']}:{$vhost['port']}".($ssl_conf ? ' ssl;' : ';');
		$hostnames   = ($vhost['hostname'] ?: "_");
		
		$servers .= strtr($vhosts_g['conf_server'], 
			[
			'@listen_conf' => $listen_conf,
			'@hostnames'   => $hostnames,
			'@ssl_conf'    => $ssl_conf,
			'@root_folder' => $root_folder
			]);
		}
		
	/*--------------------*/
	/* Build config file. */
	/*--------------------*/
	file_put_contents($conf_file_path, strtr($vhosts_g['conf'], [ '@servers' => $servers ]));
	conf_mount_ro();
	}
?>
