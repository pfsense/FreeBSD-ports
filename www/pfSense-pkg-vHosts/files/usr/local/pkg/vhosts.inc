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
$vhosts_g['subsystem_name']   = 'vhosts';
$vhosts_g['service_name']     = 'vhostsd';
$vhosts_g['conf_file_path']   = "/var/etc/{$vhosts_g['service_name']}.conf";
$vhosts_g['conf_file_prefix'] = "/var/etc/{$vhosts_g['service_name']}";
$vhosts_g['pid_file_path']    = "/var/run/{$vhosts_g['service_name']}.pid";
$vhosts_g['dirty_file_path']  = "/var/run/{$vhosts_g['subsystem_name']}.dirty";
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
    include             /usr/local/etc/nginx/mime.types;
    default_type        application/octet-stream;
    add_header          X-Frame-Options SAMEORIGIN;
    server_tokens       off;
    sendfile            on;

    access_log          syslog:server=unix:/var/run/log,facility=local5 combined;
    keepalive_timeout   75;
    
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
        
        location / { index  index.html index.htm index.php; }

        location ~ \.php$ 
            {
            try_files                 \$uri =404; #  This line closes a potential security hole
                                                  # ensuring users can't execute uploaded files
                                                  # see: http://forum.nginx.org/read.php?2,88845,page=3
            fastcgi_pass               unix:/var/run/php-fpm.socket;
            fastcgi_index              index.php;
            fastcgi_param              SCRIPT_FILENAME  \$document_root\$fastcgi_script_name;
            fastcgi_param              HTTP_PROXY  ""; # Fix httpoxy - https://httpoxy.org/#fix-now
            fastcgi_read_timeout       180;
            include                    /usr/local/etc/nginx/fastcgi_params;
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

        ssl_certificate                         @crt_file_path;
        ssl_certificate_key                     @key_file_path;
        ssl_session_timeout                     10m;
        keepalive_timeout                       70;
        ssl_session_cache                       shared:SSL:10m;
        ssl_protocols                           TLSv1 TLSv1.1 TLSv1.2;
        ssl_ciphers                             "EECDH+AESGCM:EDH+AESGCM:AES256+EECDH:AES256+EDH";
        ssl_prefer_server_ciphers               on;
        add_header Strict-Transport-Security    "max-age=31536000";
        add_header X-Content-Type-Options       nosniff;
        ssl_session_tickets                     off;
        ssl_dhparam                             /etc/dh-parameters.4096;
CONF_SERVER_SSL;

/*******************************************************************************
* vhosts_build_service_config */
/**
* Rebuilds nginx configuration for vhosts. Called for package synchronization
* and when any vhost is modified.
*
* Config file setting: <custom_php_resync_config_command>
*******************************************************************************/
function vhosts_build_service_config() 
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
        if (isset($vhost['disabled']))
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
        unset($ssl_conf);
        if (isset($vhost['certref']))
            {
            $cert = lookup_cert($vhost['certref']);
            $crt  = base64_decode($cert['crt']);
            $key  = base64_decode($cert['prv']);
            
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
            
        /*---------------------------------------------------------*/
        /* Set listen configuration for ipaddress:port.            */
        /* Set 'server_name' to hostname and hostname:port for all */
        /* names listed. Set to catchall if no hostnames listed.   */
        /*---------------------------------------------------------*/
        $listen_conf = "listen {$vhost['ipaddress']}:{$vhost['port']}".($ssl_conf ? ' ssl;' : ';');

        if ($vhost['hostname'])
            $hostnames = join(' ', array_map(function($n) use ($vhost) { return "$n $n:{$vhost['port']}"; }, explode(' ', $vhost['hostname'])));
        else
            $hostnames = '_';
        
        /*------------------------------------*/
        /* Add server section for this vhost. */
        /*------------------------------------*/
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

/*******************************************************************************
* vhosts_dirty */
/**
* Sets/return vhosts subsystem dirty flag.
* 
* @param  dirty  True/false to set/clear dirty flag, undef for no change.
* @return        Returns new or current value of subsystem dirty flag.
*
* Config file setting: <service/startcmd>
*******************************************************************************/
function vhosts_dirty($dirty)
    {
    global $vhosts_g;

    if (isset($dirty))
        if ($dirty)
            mark_subsystem_dirty($vhosts_g['subsystem_name']);
        else
            clear_subsystem_dirty($vhosts_g['subsystem_name']);
            
    return is_subsystem_dirty($vhosts_g['subsystem_name']);
    }
                        
/*******************************************************************************
* vhosts_post_install */
/**
* Creates rc file and builds the service config after package install.  
*
* Config file settings: <custom_php_install_command>
*******************************************************************************/
function vhosts_post_install() 
    {
    global $vhosts_g;

    write_rcfile(
        [
        "file"  => "vhostsd.sh",
        "start" => "/usr/local/sbin/nginx -c '{$vhosts_g['conf_file_path']}' && rm -f '{$vhosts_g['dirty_file_path']}'",
        "stop"  => "kill `cat {$vhosts_g['pid_file_path']}`                  && rm -f '{$vhosts_g['dirty_file_path']}'"
        ]);

    vhosts_build_service_config();
    }

/*******************************************************************************
* vhosts_pre_deinstall */
/**
* Removes generated files before uninstall. 'pkg remove' command calls
* '/etc/rc.packages <name> DEINSTALL' before remove and POST-DEINSTALL after.
* <custom_php_deinstall_command> runs in POST-DEINSTALL after config file is gone.
*
* Config file setting: <custom_php_pre_deinstall_command>
*******************************************************************************/
function vhosts_pre_deinstall() 
    {
    global $vhosts_g;
    exec("/bin/rm -f {$vhosts_g['conf_file_prefix']}*");
    }
    
/*******************************************************************************
* vhostsd_is_running */
/**
* Returns true if vhosts server is running.
*
* Config file setting: <custom_php_service_status_command>
*******************************************************************************/
function vhostsd_is_running()
    {
    global $vhosts_g;
    return is_pid_running($vhosts_g['pid_file_path']);
    }    
?>
