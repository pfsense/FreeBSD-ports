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
            $this->response->message = 'failed to save new config';
            return FALSE;
        }
        $this->response->http_code = 200;
        $this->response->message = 'ok';
        $this->response->data = array(
            'do_backup' => $do_backup, 
            'do_reload' => $do_reload);
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
     * system_data()
     * 
     * @param array $__args
     * @return boolean
     */
    public function system_data($__args) {
        fauxApiLogger::debug(__METHOD__);
        
        $section = NULL;
        extract($__args, EXTR_IF_EXISTS);
        
        $this->response->http_code = 500;
        $this->response->message = 'system_data not yet implemented';
        return FALSE;
    }

}
