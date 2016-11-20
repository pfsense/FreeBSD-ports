<?php
/**
 * FauxAPI
 *  - A REST API interface for pfSense to facilitate dev-ops.
 *  - https://github.com/ndejong/pfsense_fauxapi
 * 
 * Copyright 2016 Nicholas de Jong  
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *     http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace fauxapi\v1;
if (!defined('FAUXAPI_CALLID')) { echo 'FAUXAPI_CALLID missing'; exit; };

include_once '/etc/inc/globals.inc';
include_once '/etc/inc/util.inc';
include_once '/etc/inc/xmlparse.inc';
include_once '/etc/inc/config.lib.inc';
include_once '/etc/inc/system.inc';

class fauxApiPfsenseInterface {

    public $config_xml_root = 'pfsense';
    public $config_base_path = '/cf/conf';
    public $config_backup_path = '/cf/conf/backup';
    public $config_backup_cache = '/cf/conf/backup/backup.cache';
    public $config_fauxapi_backup_path = '/cf/conf/fauxapi';
    public $config_cache_filename = '/tmp/config.cache';
    public $config_default_filename = '/cf/conf/config.xml';
    public $config_reload_max_wait_secs = 60;
    
    /**
     * get_next_backup_config_filename()
     * 
     * @return string
     */
    public function get_next_backup_config_filename($type='pfsense') {
        fauxApiLogger::debug(__METHOD__, array(
            'type' => $type
        ));
        
        if('pfsense' === $type) {
            ## NB: config filename must be parseable by pfsense cleanup_backupcache()
            $filename = 'config-'. time() .'.xml';
            $path = $this->config_backup_path;
        } 
        elseif('fauxapi' == $type) {
            $filename = 'config-' . time() . '-' . FAUXAPI_APIKEY . '-' . FAUXAPI_CALLID .'.xml';
            $path = $this->config_fauxapi_backup_path;
        }
        else {
            throw new \Exception('unsupported $type requested');
        }
        
        return $path . '/' . $filename;
    }
    
    /**
     * config_load()
     * 
     * @param string $config_file
     * @return array
     */
    public function config_load($config_file, $__do_safe_check=TRUE) {
        fauxApiLogger::debug(__METHOD__, array(
            'config_file' => $config_file
        ));
        
        if($__do_safe_check) {
            if(strpos($config_file, $this->config_base_path) !== 0 || strpos($config_file, '..') !== FALSE){
                fauxApiLogger::error('attempting to load config file from non-supported path', array(
                    'config_file' => $config_file,
                ));
                return array();
            }
        }
        
        if(!is_file($config_file)) {
            fauxApiLogger::error('requested config file can not be found',array(
                'config_file' => $config_file
            ));
            return array();
        }
        
        return \parse_xml_config($config_file, $this->config_xml_root);
    }

    /**
     * config_save()
     * 
     * @param array $config
     * @param boolean $do_backup
     * @param boolean $do_reload
     * @return boolean
     */
    public function config_save($config, $do_backup=TRUE, $do_reload=TRUE) {
        fauxApiLogger::debug(__METHOD__, array(
            'do_backup' => $do_backup,
            'do_reload' => $do_reload
        ));
        
        $config_file = $this->config_default_filename;
        
        if (TRUE === $do_backup) {
            $config_backup_file = $this->config_backup($config_file);
            if (!is_file($config_backup_file)) {
                return FALSE;
            }
        }
        
        $username = 'fauxapi-'.FAUXAPI_APIKEY.'@'.fauxApiUtils::get_client_ipaddr();

        $config['revision'] = $config_revision = array(
            'time' => time(),
            'description' => $username.': update via fauxapi for callid: '.FAUXAPI_CALLID,
            'username' => $username
        );
        
        $xml_string = \dump_xml_config($config, $this->config_xml_root);
        $config_temp_file = tempnam(sys_get_temp_dir(), 'fauxApi_');
        file_put_contents($config_temp_file, $xml_string);
        
        fauxApiLogger::debug('attempting to (re)load a temp copy of the config supplied', array(
            'config_temp_file' => $config_temp_file, 
        ));
        $temp_config = $this->config_load($config_temp_file, FALSE);
        
        // remove the revision data before comparing since we did set it above
        $config['revision'] = $temp_config['revision'] = array();
        if ($config !== $temp_config) {
            fauxApiLogger::error('saved config does not match config when saved and reloaded');
            return FALSE;
        }
        $config['revision'] = $config_revision;
        
        fauxApiLogger::debug('confirmed the config supplied will reload into the same config supplied', array(
            'config_temp_file' => $config_temp_file, 
        ));
        unlink($config_file);
        rename($config_temp_file, $config_file);
        
        if (!is_file($config_file)) {
            fauxApiLogger::error('unable to find new config file', $config_file);
            return FALSE;
        }
        
        // attempt to reload, if this fails revert to previous backup
        if(TRUE === $do_reload) {
            if(!$this->system_load_config($config_file)) {
                $last_backup_file = $this->config_backup_path .'/'. \discover_last_backup();
                fauxApiLogger::warn('attempting to revert config to last known backup', array(
                    'last_backup_file' => $last_backup_file
                ));
                if(is_file($last_backup_file)) {
                    if (\config_restore($last_backup_file) !== 0) { // WTF, sucess == 0 ??
                        fauxApiLogger::error('unable to reload previous config backup');
                    } else {
                        fauxApiLogger::info('config file reverted to last known backup', array(
                            'config_file' => $last_backup_file
                        ));
                    }
                } else {
                    fauxApiLogger::error('unable to locate previous backup file to revert');
                }
                return FALSE;
            }
        }
        return TRUE;
    }
    
    /**
     * config_backup()
     * 
     * @param type $config_file
     * @return type
     */
    public function config_backup($config_file = NULL, $do_fauxapi_symlink=FALSE) {
        fauxApiLogger::debug(__METHOD__, $config_file);

        if (is_null($config_file)) {
            $config_file = $this->config_default_filename;
        }

        $config_backup_file = $this->get_next_backup_config_filename();
        copy($config_file, $config_backup_file);

        if ($this->config_load($config_file) !== $this->config_load($config_backup_file)) {
            fauxApiLogger::error('config backup failed consistency check',array(
                'source_file' => $config_file,
                'backup_file' => $config_backup_file
            ));
            return NULL;
        }
        
        # register this backup by doing a backup cache cleanup
        global $config;
        $config = $this->config_load($this->config_default_filename);
        \cleanup_backupcache();
        unset($config);
        
        # create a fauxapi symlink to make these backups easier to identify
        if(TRUE === $do_fauxapi_symlink) {
            if(!is_dir($this->config_fauxapi_backup_path)) {
                mkdir($this->config_fauxapi_backup_path, 0755, TRUE);
            }
            symlink($config_backup_file, $this->get_next_backup_config_filename('fauxapi'));
        }
        
        return $config_backup_file;
    }
    
    /**
     * config_backup_list
     */
    public function config_backup_list() {
        fauxApiLogger::debug(__METHOD__);
        
        $backup_cache = unserialize(file_get_contents($this->config_backup_cache));
        
        $backup_list = array();
        foreach(array_keys($backup_cache) as $backup_unixtime) {
            $backup_filename = $this->config_backup_path.'/config-'.$backup_unixtime.'.xml';
            if(is_file($backup_filename)) {
                $data = array(
                    'filename' => $backup_filename,
                    'timestamp' => date('Ymd\ZHis', (int)$backup_unixtime),
                    'description' => $backup_cache[$backup_unixtime]['description'],
                    'version' => $backup_cache[$backup_unixtime]['version'],
                    'filesize' => $backup_cache[$backup_unixtime]['filesize'],
                );
                $backup_list[] = $data;
            }
        }
        return $backup_list;
    }
    
    /**
     * system_load_config()
     * 
     * @return bool
     * @link https://doc.pfsense.org/index.php/How_can_I_reload_the_config_after_manually_editing_config.xml
     */
    public function system_load_config($config_file=NULL) {
        fauxApiLogger::debug(__METHOD__, $config_file);
        
        if(is_null($config_file)) {
            $config_file = $this->config_default_filename;
        }
        
        if(strpos($config_file, $this->config_base_path) !== 0 || strpos($config_file, '..') !== FALSE){
            fauxApiLogger::error('attempting to load config file from non-supported path', array(
                'config_file' => $config_file,
            ));
            return FALSE;
        }
        
        if($config_file !== $this->config_default_filename) {
            copy($config_file, $this->config_default_filename);
        }
        
        if(is_file($this->config_cache_filename)) {
            unlink($this->config_cache_filename);
        } else {
            fauxApiLogger::warn('pfsense config cache file does not exist before reload', array(
                'config_cache_filename' => $this->config_cache_filename
            ));
        }
        
        $wait_count_seconds = 0;
        while($wait_count_seconds < $this->config_reload_max_wait_secs) {
            
            // induce the pfsense config.cache to regenerate by requesting index.php
            $cache_respawn_url = fauxApiUtils::get_request_scheme() . '://' . fauxApiUtils::get_request_ipaddr() . '/index.php?__fauxapi_callid='.FAUXAPI_CALLID;
            $exec_command = 'curl --silent --insecure "'.addslashes($cache_respawn_url).'" > /dev/null';
            // unable to call file_get_contents() to a URL thus we resort to an exec!!
            fauxApiLogger::debug('exec curl', array(
                'exec_command' => $exec_command
            ));
            exec($exec_command); 
            
            if(is_file($this->config_cache_filename)) {
                return TRUE;
            }
            sleep(1);
            $wait_count_seconds++;
        }
        
        fauxApiLogger::error('unable confirm config reload before timeout', array(
            'config_cache_filename' => $this->config_cache_filename,
            'timeout' => $this->config_reload_max_wait_secs
        ));
        
        return FALSE;
    }
    
    /**
     * system_reboot()
     * 
     * @return bool
     */
    public function system_reboot() {
        fauxApiLogger::debug(__METHOD__);
        
        ignore_user_abort(TRUE);
        
        ob_start();
        \system_reboot();
        ob_end_clean();
        
        return TRUE;
    }
    
    /**
     * send_event()
     * 
     * @param string $command
     */
    public function send_event($command) {
        fauxApiLogger::debug(__METHOD__, $command);
        
        //
        // NB: quick oneliner to catch the commands that pfSense ordinarily sends to send_event()
        //  grep -r 'send_event(' * | grep -v 'function ' | grep -v 'retval' | cut -d':' -f2 | sed 's/^[\t ]*//g' | sort | uniq
        // 
        // send_event("filter reload");
        // send_event("filter sync");
        // send_event("interface all reload");
        // send_event("interface newip {$iface}");
        // send_event("interface reconfigure {$interface}");
        // send_event("interface reconfigure {$reloadif}");
        // send_event("service reload all");
        // send_event("service reload dyndnsall");
        // send_event("service reload dyndns {$interface}");
        // send_event("service reload ipsecdns");
        // send_event("service reload packages");
        // send_event("service reload sshd");
        // send_event("service restart packages");
        // send_event("service restart sshd");
        // send_event("service restart webgui");
        // send_event("service sync alias {$name}");
        // send_event("service sync vouchers");
        // 
        // Is this checking a help or a hinderance actually? - NdJ
        // 

        $valid = array(
            'filter'    => array('reload', 'sync'),
            'interface' => array('all', 'newip', 'reconfigure'),
            'service'   => array('reload', 'restart', 'sync'),
        );
        
        $command_parts = explode(' ', (string)$command);
        
        # check part #1
        if(!isset($command_parts[0]) || !in_array($command_parts[0], array_keys($valid))) {
            fauxApiLogger::error('supplied command command not listed in valid send_event() set', $command);
            return FALSE;
        }
        
        # check part #2
        if(!isset($command_parts[1]) || !in_array($command_parts[1], $valid[$command_parts[0]])) {
            fauxApiLogger::error('supplied command command not listed in valid send_event() set', $command);
            return FALSE;
        }
        
        \send_event($command);
        return TRUE;
    }

}
