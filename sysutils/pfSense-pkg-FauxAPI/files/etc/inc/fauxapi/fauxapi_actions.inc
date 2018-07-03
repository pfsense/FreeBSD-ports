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

class fauxApiActions {
    
    public $response = NULL;
    public $action_input_data = NULL;

    private $PfsenseInterface = NULL;

    /**
     * __construct()
     */
    public function __construct() {
        $this->PfsenseInterface = new fauxApiPfsenseInterface();
    }
    
    /**
     * config_get()
     * 
     * @param array $__args
     * @return boolean
     */
    public function config_get($__args) {
        fauxApiLogger::debug(__METHOD__);
        
        $config_file = NULL;
        extract($__args, EXTR_IF_EXISTS);
        
        if(empty($config_file)) {
            $config_file = $this->PfsenseInterface->config_default_filename;
        }
        
        $config = $this->PfsenseInterface->config_load($config_file);
        
        if (empty($config)) {
            $this->response->http_code = 500;
            $this->response->message = 'unable to load config file';
            $this->response->data = array(
                'config_file' => $config_file);
            return FALSE;
        }
        $this->response->http_code = 200;
        $this->response->message = 'ok';
        $this->response->data = array(
            'config_file' => $config_file, 
            'config' => $config);
        return TRUE;
    }
    
    /**
     * config_set()
     * 
     * @param array $__args
     * @return boolean
     */
    public function config_set($__args) {
        fauxApiLogger::debug(__METHOD__);
        
        $do_backup = TRUE;
        $do_reload = TRUE;
        extract($__args, EXTR_IF_EXISTS);
        
        // save this new config
        if (!$this->PfsenseInterface->config_save($this->action_input_data, $do_backup, $do_reload)) {
            $this->response->http_code = 500;
            $this->response->message = 'failed to set new config';
            return FALSE;
        }
        $this->response->http_code = 200;
        $this->response->message = 'ok';
        $this->response->data = array(
            'do_backup' => $do_backup, 
            'do_reload' => $do_reload,
            'previous_config_file' => $this->PfsenseInterface->backup_config_filename[FAUXAPI_CALLID]);
        return TRUE;
    }
    
    /**
     * config_patch()
     * 
     * @param array $__args
     * @return boolean
     */
    public function config_patch($__args) {
        fauxApiLogger::debug(__METHOD__);
        
        $do_backup = TRUE;
        $do_reload = TRUE;
        extract($__args, EXTR_IF_EXISTS);
        
        // patch the config data
        if (!$this->PfsenseInterface->config_patch($this->action_input_data, $do_backup, $do_reload)) {
            $this->response->http_code = 500;
            $this->response->message = 'failed to patch config data';
            return FALSE;
        }
        
        $this->response->http_code = 200;
        $this->response->message = 'ok';
        $this->response->data = array(
            'do_backup' => $do_backup, 
            'do_reload' => $do_reload,
            'previous_config_file' => $this->PfsenseInterface->backup_config_filename[FAUXAPI_CALLID]);
        return TRUE;
    }
    
    /**
     * config_item_get()
     * 
     * @param array $__args
     * @return boolean
     */
    public function config_item_get($__args) {
        fauxApiLogger::debug(__METHOD__);
        
        $item = NULL;
        extract($__args, EXTR_IF_EXISTS);
        
        $value = $this->PfsenseInterface->config_item_get($item);
        
        if (FALSE === $value) {
            $this->response->http_code = 500;
            $this->response->message = 'unable to get config item value';
            $this->response->data = array(
                'item' => $item);
            return FALSE;
        }
        $this->response->http_code = 200;
        $this->response->message = 'ok';
        $this->response->data = array(
            'item' => $item, 
            'value' => $value);
        return TRUE;
    }
    
    /**
     * config_item_set()
     * 
     * @param array $__args
     * @return boolean
     */
    public function config_item_set($__args) {
        fauxApiLogger::debug(__METHOD__);
        
        $item = NULL;
        $do_backup = TRUE;
        $do_reload = TRUE;
        $do_insert = FALSE;
        extract($__args, EXTR_IF_EXISTS);
        
        if (!$this->PfsenseInterface->config_item_set($item, $this->action_input_data, $do_insert, $do_backup, $do_reload)) {
            $this->response->http_code = 500;
            $this->response->message = 'failed to set config item value';
            $this->response->data = array(
                'item' => $item,
                'value' => $value);
            return FALSE;
        }
        
        $this->response->http_code = 200;
        $this->response->message = 'ok';
        $this->response->data = array(
            'item' => $item,
            'value' => $value,
            'do_insert' => $do_insert, 
            'do_backup' => $do_backup, 
            'do_reload' => $do_reload,
            'previous_config_file' => $this->PfsenseInterface->backup_config_filename[FAUXAPI_CALLID]);
        return TRUE;
    }
    
    /**
     * config_reload()
     * 
     * @return boolean
     */
    public function config_reload() {
        fauxApiLogger::debug(__METHOD__);

        if (!$this->PfsenseInterface->system_load_config()) {
            $this->response->http_code = 500;
            $this->response->message = 'unable to reload current config';
            return FALSE;
        }
        $this->response->http_code = 200;
        $this->response->message = 'ok';
        return TRUE;
    }
    
    /**
     * config_backup()
     * 
     * @return boolean
     */
    public function config_backup() {
        fauxApiLogger::debug(__METHOD__);

        $backup_config_file = $this->PfsenseInterface->config_backup();
        
        if(!is_file($backup_config_file)) {
            $this->response->http_code = 500;
            $this->response->message = 'unable to create config backup';
            $this->response->data = array(
                'backup_config_file' => $backup_config_file);
            return FALSE;
        }
        $this->response->http_code = 200;
        $this->response->message = 'ok';
        $this->response->data = array(
            'backup_config_file' => $backup_config_file);
        return TRUE;
    }
    
    /**
     * config_backup_list()
     * 
     * @return boolean
     */
    public function config_backup_list() {
        fauxApiLogger::debug(__METHOD__);
        
        $backup_files = $this->PfsenseInterface->config_backup_list();
        
        if (!is_array($backup_files)) {
            $this->response->http_code = 500;
            $this->response->message = 'unable to obtain list of backup files';
            return FALSE;
        }
        $this->response->http_code = 200;
        $this->response->message = 'ok';
        $this->response->data = array(
            'backup_files' => $backup_files);
        return TRUE;
    }
    
    /**
     * config_restore()
     * 
     * @param array $__args
     * @return boolean
     */
    public function config_restore($__args) {
        fauxApiLogger::debug(__METHOD__);
        
        $config_file = NULL;
        extract($__args, EXTR_IF_EXISTS);
        
        if (!$this->PfsenseInterface->system_load_config($config_file)) {
            $this->response->http_code = 500;
            $this->response->message = 'unable to restore backup config';
            $this->response->data = array(
                'config_file' => $config_file);
            return FALSE;
        }
        $this->response->http_code = 200;
        $this->response->message = 'ok';
        $this->response->data = array(
            'config_file' => $config_file);
        return TRUE;
    }

    /**
     * send_event
     * 
     * @return boolean
     */
    public function send_event() {
        fauxApiLogger::debug(__METHOD__);
        
        if(!is_array($this->action_input_data) || !isset($this->action_input_data[0])) {
            $this->response->http_code = 400;
            $this->response->message = 'incorrectly formatted request data';
            return FALSE;
        }
        
        $command = $this->action_input_data[0];
        
        if (!$this->PfsenseInterface->send_event($command)) {
            $this->response->http_code = 400;
            $this->response->message = 'unable to send_event()';
            $this->response->data = array(
                'command' => $command);
            return FALSE;
        }
        $this->response->http_code = 200;
        $this->response->message = 'ok';
        return TRUE;
    }
    
    /**
     * system_reboot()
     * 
     * @return boolean
     */
    public function system_reboot() {
        fauxApiLogger::debug(__METHOD__);
        
        if (!$this->PfsenseInterface->system_reboot()) {
            $this->response->http_code = 500;
            $this->response->message = 'unable to issue system reboot';
            return FALSE;
        }
        $this->response->http_code = 200;
        $this->response->message = 'ok';
        return TRUE;
    }
    
    /**
     * system_stats()
     * 
     * @return boolean
     */
    public function system_stats() {
        fauxApiLogger::debug(__METHOD__);
        
        $stats = $this->PfsenseInterface->system_stats();
        
        if (empty($stats)) {
            $this->response->http_code = 500;
            $this->response->message = 'unable to get system stats';
            return FALSE;
        }
        $this->response->http_code = 200;
        $this->response->message = 'ok';
        $this->response->data = array(
            'stats' => $stats
        );
        return TRUE;
    }
    
    /**
     * interface_stats()
     * 
     * @return boolean
     */
    public function interface_stats($__args) {
        fauxApiLogger::debug(__METHOD__);
        
        $interface = NULL;
        extract($__args, EXTR_IF_EXISTS);
        
        $stats = $this->PfsenseInterface->interface_stats($interface);
        
        if (empty($stats)) {
            $this->response->http_code = 500;
            $this->response->message = 'unable to get interface stats';
            return FALSE;
        }
        $this->response->http_code = 200;
        $this->response->message = 'ok';
        $this->response->data = array(
            'stats' => $stats
        );
        return TRUE;
    }
    
    /**
     * rule_get()
     * 
     * @param array $__args
     * @return boolean
     */
    public function rule_get($__args) {
        fauxApiLogger::debug(__METHOD__);
        
        $rule_number = NULL;
        extract($__args, EXTR_IF_EXISTS);
        
        $rules = $this->PfsenseInterface->rule_get((int)$rule_number);
        
        if (empty($rules)) {
            $this->response->http_code = 500;
            $this->response->message = 'unable to get rule(s)';
            return FALSE;
        }
        $this->response->http_code = 200;
        $this->response->message = 'ok';
        $this->response->data = array(
            'rules' => $rules
        );
        return TRUE;
    }
    
    /**
     * alias_update_urltables()
     * 
     * @param array $__args
     * @return boolean
     */
    public function alias_update_urltables($__args) {
        fauxApiLogger::debug(__METHOD__);
        
        $table = NULL;
        extract($__args, EXTR_IF_EXISTS);
        
        $updates = $this->PfsenseInterface->alias_update_urltables($table);
        
        $this->response->http_code = 200;
        $this->response->message = 'ok';
        $this->response->data = array(
            'updates' => $updates
        );
        return TRUE;
    }
    
    /**
     * gateway_status()
     * 
     * @return boolean
     */
    public function gateway_status() {
        fauxApiLogger::debug(__METHOD__);
        
        $gateway_status = $this->PfsenseInterface->gateway_status();
        
        if (empty($gateway_status)) {
            $this->response->http_code = 500;
            $this->response->message = 'unable to get gateway status';
            return FALSE;
        }
        $this->response->http_code = 200;
        $this->response->message = 'ok';
        $this->response->data = array(
            'gateway_status' => $gateway_status
        );
        return TRUE;
    }

    /**
     * function_call()
     * 
     * @return boolean
     */
    public function function_call() {
        fauxApiLogger::debug(__METHOD__);
        
        if(!is_array($this->action_input_data) || !isset($this->action_input_data['function'])) {
            $this->response->http_code = 400;
            $this->response->message = 'incorrectly formatted request data';
            return FALSE;
        }
        
        $function = $this->action_input_data['function'];
        if(!is_string($function)) {
            $this->response->http_code = 400;
            $this->response->message = 'function is not a string value';
            return FALSE;
        }

        $args = array();
        if(isset($this->action_input_data['args'])) {
            $args = $this->action_input_data['args'];
            if(is_string($args)) {
                $args = array($args);
            }
        }
        
        $includes = array();
        if(isset($this->action_input_data['includes'])) {
            $includes = $this->action_input_data['includes'];
            if(is_string($includes)) {
                $includes = array($includes);
            }
        }
        
        try {
            $return = $this->PfsenseInterface->function_call(
                    $function,
                    $args,
                    $includes
            );
        } catch (\Exception $e) {
            $this->response->http_code = 500;
            $this->response->message = 'problem calling function';
            $this->response->error = array('error' => $e);
            return FALSE;
        }
        
        $this->response->http_code = 200;
        $this->response->message = 'ok';
        $this->response->data = array(
            'return' => $return
        );
        return TRUE;
    }
}
