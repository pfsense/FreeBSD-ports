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
include_once '/etc/inc/notices.inc';
include_once '/etc/inc/config.lib.inc';
include_once '/etc/inc/system.inc';

class fauxApiPfsenseInterface {

    public $config_xml_root = 'pfsense';
    public $config_base_path = '/cf/conf';
    public $config_backup_path = '/cf/conf/backup';
    public $config_backup_cache = '/cf/conf/backup/backup.cache';
    public $config_fauxapi_backup_path = '/cf/conf/fauxapi';
    public $config_pfsense_function_calls = '/etc/fauxapi/pfsense_function_calls.txt';
    public $config_pfsense_include_path = '/etc/inc';
    public $config_cache_filename = '/tmp/config.cache';
    public $config_default_filename = '/cf/conf/config.xml';
    public $config_reload_max_wait_secs = 60;
    
    public $backup_config_filename = array();
    
    /**
     * get_next_backup_config_filename()
     * 
     * @param string $type
     * @return string
     * @throws \Exception
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
     * config_patch()
     * 
     * @param array $config_patch
     * @param boolean $do_backup
     * @param boolean $do_reload
     * @return boolean
     */
    public function config_patch($config_patch, $do_backup=TRUE, $do_reload=TRUE) {
        fauxApiLogger::debug(__METHOD__, array(
            'do_backup' => $do_backup,
            'do_reload' => $do_reload
        ));
        
        $config = $this->array_merge_recursive_distinct(
                    $this->config_load($this->config_default_filename),
                    $config_patch
                );
        
        fauxApiLogger::info('config_patch merged with current config, attempting to save');
        return $this->config_save($config, $do_backup, $do_reload);
    }
    
    /**
     * array_merge_recursive_distinct()
     * 
     * @param array $array1
     * @param array $array2
     * @return array
     * @note adapted from http://php.net/manual/en/function.array-merge-recursive.php#92195
     */
    private function array_merge_recursive_distinct(array &$array1, array &$array2) {
        $merged = $array1;
        foreach ($array2 as $key => &$value) {
            if (is_array($value) && !$this->is_numeric_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->array_merge_recursive_distinct($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }
        return $merged;
    }
    
    /**
     * is_numeric_array()
     * 
     * @param array $array
     * @return bool
     * @note adapted from https://codereview.stackexchange.com/questions/201/is-numeric-array-is-missing
     */
    private function is_numeric_array($array) {
        return array_keys($array) === range(0,(count($array)-1));
    }
    
    /**
     * config_item_get()
     * 
     * @param string $item
     * @return string
     */
    public function config_item_get($item) {
        fauxApiLogger::debug(__METHOD__, array(
            'item' => $item
        ));
        
        // not yet implemented !!
        fauxApiLogger::error('not yet implemented');
        return FALSE;
    }
    
    /**
     * config_item_set()
     * 
     * @param string $item
     * @param string $value
     * @param boolean $do_insert
     * @param boolean $do_backup
     * @param boolean $do_reload
     * @return boolean
     */
    public function config_item_set($item, $value, $do_insert=FALSE, $do_backup=TRUE, $do_reload=TRUE) {
        fauxApiLogger::debug(__METHOD__, array(
            'item' => $item,
            'value' => $value,
            'do_insert' => $do_insert,
            'do_backup' => $do_backup,
            'do_reload' => $do_reload
        ));
        
        // not yet implemented !!
        fauxApiLogger::error('not yet implemented');
        return FALSE;
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
            $this->backup_config_filename[FAUXAPI_CALLID] = $config_backup_file;
        }
        else {
            $this->backup_config_filename[FAUXAPI_CALLID] = FALSE;
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
        
        if(strpos($config_file, $this->config_base_path) === FALSE || strpos($config_file, '..') !== FALSE){
            fauxApiLogger::error('attempting to load config file from non-supported path', array(
                'config_file' => $config_file,
            ));
            return FALSE;
        }
        
        if(!is_file($config_file)) {
            fauxApiLogger::error('attempting to load config file that does not exist', array(
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
            $cache_respawn_url = fauxApiUtils::get_request_scheme() . '://127.0.0.1/index.php?__fauxapi_callid='.FAUXAPI_CALLID;
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
    
    /**
     * system_stats
     * 
     * @return array
     */
    public function system_stats() {
        fauxApiLogger::debug(__METHOD__);
        
        include_once '/usr/local/www/includes/functions.inc.php';
        
        // Mostly as per the get_stats() in functions.inc.php
        // NB: calls are now wrapped in function_exists() because some functions
        // have been removed in later versions of pfSense

        if (function_exists('cpu_usage')) { 
            $stats['cpu'] = strip_tags(\cpu_usage()); 
        }
        if (function_exists('mem_usage')) {
            $stats['mem'] = strip_tags(\mem_usage());
        }
        if (function_exists('get_uptime')) {
            $stats['uptime'] = strip_tags(\get_uptime());
        }
        if (function_exists('get_pfstate')) { 
            $stats['pfstate'] = strip_tags(\get_pfstate());
            $stats['pfstatepercent'] = strip_tags(\get_pfstate(true));
        }
        if (function_exists('get_temp')) {
            $stats['temp'] = strip_tags(\get_temp()); 
        }
        if (function_exists('update_date_time')) { 
            $stats['datetime'] = gmdate('Ymd\ZHis', strtotime((\update_date_time()))); 
        }
        if (function_exists('get_interfacestats')) { 
            $stats['interfacestatistics'] = strip_tags(\get_interfacestats()); 
        }
        if (function_exists('get_interfacestatus')) { 
            $stats['interfacestatus'] = strip_tags(\get_interfacestatus()); 
        }
        if (function_exists('get_cpufreq')) { 
            $stats['cpufreq'] = strip_tags(\get_cpufreq()); 
        }
        if (function_exists('get_load_average')) { 
            $stats['load_average'] = explode(',',str_replace(' ','',strip_tags(\get_load_average()))); 
        }
        
        if (function_exists('get_mbuf')) {
            
            // Might be the worst hack work-around in history, but I'd love to 
            // understand why pfSense developers decided to make the get_mbuf()
            // function a pass-by-reference function in 2.4.x - if there is a 
            // better way than this, drop me line!
            
            $content = file_get_contents('/usr/local/www/includes/functions.inc.php');
            if (strpos($content, 'function get_mbuf(&$mbuf')) {
                // pfSense 2.4.x
                \get_mbuf($stats['mbuf'], $stats['mbufpercent']);
                $stats['mbuf'] = strip_tags($stats['mbuf']);
                $stats['mbufpercent'] = strip_tags($stats['mbufpercent']);
            } else {
                // pfSense 2.3.x
                $stats['mbuf'] = strip_tags(\get_mbuf());
                $stats['mbufpercent'] = strip_tags(\get_mbuf(true));
                
            }
        }
        
        return $stats;
    }
    
    /**
     * rule_get
     * 
     * @param int $rule_number
     * @return array
     */
    public function rule_get($rule_number=NULL) {
        fauxApiLogger::debug(__METHOD__, array(
            'rule_number' => $rule_number
        ));
        
        $rules_temp_file = tempnam(sys_get_temp_dir(), 'fauxApi_');
        $exec_command = 'pfctl -sr -vv > "'.$rules_temp_file.'"';
        fauxApiLogger::debug('exec pfctl', array(
            'exec_command' => $exec_command
        ));
        exec($exec_command); 
        
        if(!is_file($rules_temp_file)) {
            fauxApiLogger::error('unable to locate output temp file from exec pfctl call', array(
                'rules_temp_file' => $rules_temp_file,
            ));
            return array();
        }
        
        $rules_temp_file_content = file_get_contents($rules_temp_file);
        unlink($rules_temp_file);
        
        $rule = NULL;
        $rules = array();
        foreach(explode("\n",$rules_temp_file_content) as $line) {
            if('@' === substr($line, 0, 1)) {
                preg_match('/^\@([0-9]+)\(\d+\) (.*?)$/', $line, $m);
                if(!isset($m[1]) || !isset($m[2])) {
                    fauxApiLogger::error('problem parsing pfctl rules output rule-line', array(
                        'line' => $line,
                    ));
                    return array();
                } else {
                    $rule = (int)$m[1];
                }
                $rules[$rule] = array(
                    'rule' => $m[2]
                );
            } elseif (strpos($line,'[') && strpos($line,']')) {
                // Hack to make the output more easily parseable
                $line = str_replace(': pid ', ': ', $line);
                $line = str_replace('State Creations: ', 'State_Creations: ', $line);
                $line = strtolower($line);
                
                preg_match_all('/(\w+)\: ([0-9]+)/', $line, $m);
                if(!isset($m[1]) || !isset($m[2])) {
                    fauxApiLogger::error('problem parsing pfctl rules output stats-line', array(
                        'line' => $line,
                    ));
                    return array();
                } else {
                    $rules[$rule]['number'] = $rule;
                    foreach($m[1] as $index=>$attribute) {
                        $rules[$rule][$attribute] = $m[2][$index];
                    }
                }
            }
        }
        
        if(!empty($rule_number)) {
            if(!array_key_exists($rule_number, $rules)) {
                fauxApiLogger::error('unable to locate rule', array(
                    'rule_number' => $rule_number
                ));
                return array();
            }
            return array($rules[$rule_number]);
        }
        return $rules;
    }
    
    /**
     * alias_update_urltables()
     * 
     * @param string $table
     * @return array
     * @note based on /etc/rc.update_urltables
     */
    public function alias_update_urltables($table=NULL) {
        fauxApiLogger::debug(__METHOD__, array(
            'table' => $table
        ));
        
        include_once '/etc/inc/config.inc';
        include_once '/etc/inc/util.inc';
        include_once '/etc/inc/pfsense-utils.inc';
        
        $config = $this->config_load($this->config_default_filename);

        $urltables = array();
        foreach ($config['aliases']['alias'] as $alias) {
            if (preg_match('/urltable/i', $alias['type'])) {
                $urltables[$alias['name']] = array(
                    'url'  => $alias['url'],
                    'type' => $alias['type'],
                    'updatefreq' => $alias['updatefreq']
                );
            }
        }
        
        $updates = array();
        foreach ($urltables as $urltablename => $urltable) {
            $updates[$urltablename] = array('url' => $urltable['url']);
            if (empty($table) || $table === $urltablename) {
                $result = \process_alias_urltable(
                    $urltablename, $urltable['type'], $urltable['url'], $urltable['updatefreq'], TRUE
                );
                if ((int)$result === 1) {
                    $exec_return = '';
                    if ($urltable['type'] === 'urltable') {
                        exec("/sbin/pfctl -t " . escapeshellarg($urltablename) . " -T replace -f /var/db/aliastables/" . escapeshellarg($urltablename) . ".txt 2>&1", $exec_return);
                    }
                    $updates[$urltablename]['status'] = $exec_return;
                    fauxApiLogger::debug('Updated '.$urltablename.' content from '.$urltable['url'], $exec_return);
                }
            }
        }
        return $updates;
    }
    
    /**
     * gateway_status()
     * 
     * @return array
     * @note calls pfsense return_gateways_status() from gwlb.inc
     */
    public function gateway_status() {
        fauxApiLogger::debug(__METHOD__);
        
        include_once '/etc/inc/gwlb.inc';
        
        $byname = false;
        return \return_gateways_status($byname);
    }
    
    /**
     * interface_stats()
     * 
     * @param array $interface
     * @return array
     */
    public function interface_stats($interface){
        fauxApiLogger::debug(__METHOD__);
        
        if(is_null($interface)) {
            $error_message = 'no interface parameter provided';
            fauxApiLogger::error($error_message);
            throw new \Exception($error_message);
        }
        
        return \pfSense_get_interface_stats($interface);
    }
    
    /**
     * function_call()
     * 
     * @param string $function
     * @param array $args
     * @param array $includes
     * @return array
     * @note calls any (or most) functions available in pfSense if whitelisted in /etc/fauxapi_pfsense_interface.txt
     */
    public function function_call($function, $args, $includes) {
        fauxApiLogger::debug(__METHOD__, array(
            'function' => $function,
            'args' => $args,
            'includes' => $includes,
        ));
        
        // load and parse pfsense_function_calls.txt
        $pf_function_calls_raw = file_get_contents($this->config_pfsense_function_calls);
        $pf_function_calls = array();
        $pf_include_files = array();
        foreach(explode("\n", str_replace("\r",'',$pf_function_calls_raw)) as $line) {
            if(strlen($line) > 1 && strpos($line, '#') === FALSE && strpos($line, ':') !== FALSE ) {
                $ln_parts = explode(':', rtrim($line));
                $include_candidate = $this->config_pfsense_include_path . '/' . fauxApiUtils::sanitize($ln_parts[0], array('.', '-', '_'));
                $include_candidate = str_replace('..','',$include_candidate);
                if(!is_file($include_candidate)) {
                    fauxApiLogger::warn('skipping include file because not found', array(
                        'include' => $include_candidate,
                    ));
                    continue 1;
                }
                if(!in_array($include_candidate, $pf_include_files)) {
                    $pf_include_files[] = $include_candidate;
                }
                if(count($ln_parts) !== 2) { 
                    continue 1; 
                }
                $fn_parts = ltrim(str_replace('function', '', $ln_parts[1]));
                preg_match('/^(.*?)\((.*?)\)/', $fn_parts, $fn_matches);
                if(count($fn_matches) < 2) {
                    continue 1; 
                }
                if (isset($fn_matches[2]) && strlen($fn_matches[2]) > 0) {
                    $fn_args = split(',',str_replace(' ','', $fn_matches[2]));
                } else {
                    $fn_args = array();
                }
                $pf_function_calls[] = array(
                    'include' => $include_candidate, 
                    'function' => $fn_matches[1],
                    'args' => $fn_args,
                );
            }
        }
        
        // check if the function is valid and determine the include file it comes from
        $pass = FALSE;
        $fn_args = array();
        $fn_include = NULL;
        foreach($pf_function_calls as $pf_function_call) {
            if($pf_function_call['function'] === $function) {
                $pass = TRUE;
                $fn_args = $pf_function_call['args'];
                $fn_include = $pf_function_call['include'];
                break;
            }
        }
        if(FALSE === $pass) {
            $error_message = 'function not defined as valid in function calls reference file';
            $error_data = array(
                'function' => $function,
                'function calls reference file' => $this->config_pfsense_function_calls,
            );
            fauxApiLogger::error($error_message, $error_data);
            throw new \Exception($error_message);
        }
        
        // check if the number of args appears to be appropriate
        $arg_count_required = 0;
        $arg_count_optional = 0;
        foreach($fn_args as $fn_arg) {
            if(!strstr($fn_arg, '=')) {
                $arg_count_required++;
            } else {
                $arg_count_optional++;
            }
        }
        if(count($args) < $arg_count_required || count($args) > $arg_count_required + $arg_count_optional) {
            $error_message = 'incorrect number of function args provided';
            $error_data = array(
                'function' => $function,
                'args' => $args,
            );
            fauxApiLogger::error($error_message, $error_data);
            throw new \Exception($error_message);
        }
        
        // process the user supplied includes first
        foreach($includes as $include) {
            $include_candidate = $this->config_pfsense_include_path . '/' . fauxApiUtils::sanitize($include, array('.', '-', '_'));
            $include_candidate = str_replace('..','',$include_candidate);
            if(in_array($include_candidate, $pf_include_files) && is_file($include_candidate)) {
                fauxApiLogger::debug('include_once('.$include.') by user includes');
                include_once($include_candidate);
            } else {
                $error_message = 'failed to include file for function call';
                $error_data = array(
                    'function' => $function,
                    'include file' => $include_candidate,
                );
                fauxApiLogger::error($error_message, $error_data);
                throw new \Exception($error_message);
            }
        }
        
        // include the file for the underlaying function
        if(is_file($fn_include)) {
            fauxApiLogger::debug('include_once('.$fn_include.') implied by function');
            include_once($fn_include);
        } else {
            $error_message = 'failed to include implied include file for function call';
            $error_data = array(
                'function' => $function,
                'include file' => $fn_include,
            );
            fauxApiLogger::error($error_message, $error_data);
            throw new \Exception($error_message);
        }
        
        // issue the function_call with supplied args
        return call_user_func_array($function, $args);
    }
    
}
