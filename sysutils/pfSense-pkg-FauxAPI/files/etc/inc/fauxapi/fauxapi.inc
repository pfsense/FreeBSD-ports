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

include_once('fauxapi_logger.inc');
include_once('fauxapi_utils.inc');
include_once('fauxapi_auth.inc');
include_once('fauxapi_actions.inc');
include_once('fauxapi_pfsense_interface.inc');

class fauxApi {
    
    // NB: setting an input URL parameter __debug=true will cause this $debug 
    // to be set true within the __check_user_action_call() function
    public $debug = NULL;
    
    private $Auth = NULL;

    /**
     * __construct()
     */
    public function __construct($debug=FALSE) {
        $this->debug = $debug;
        $this->Auth = new fauxApiAuth();
    }

    /**
     * __call
     * @param string $user_action
     * @param array $user_args
     * @throws Exception
     */
    public function __call($user_action, $call_args) {
        
        fauxApiLogger::info(__METHOD__, array(
            'user_action' => $user_action,
            'callid' => FAUXAPI_CALLID,
            'client_ip' => fauxApiUtils::get_client_ipaddr()
        ));
        
        $Actions = new fauxApiActions();
        
        // check this user action request
        $checked = $this->__check_user_action_call($Actions, $user_action, $call_args[0], $call_args[1]);
        
        // create a $response object to work with below
        $response = (object) array(
            'callid' => FAUXAPI_CALLID,
            'http_code' => NULL, 
            'action' => NULL,
            'message' => NULL,
            'data' => NULL,
            'logs' => NULL);
        
        if(!empty($checked->http_code)) {
            $response->message = $checked->message;
            $response->http_code = $checked->http_code;
            
        } else {
            $Actions->response = &$response;
            $Actions->action_input_data = &$checked->data;
            
            $response->action = $checked_action = $checked->action;
            $Actions->$checked_action($checked->args);
            
            if(empty($response->http_code)) {
                $response->http_code = 500;
                $response->message = 'unexpected call response status';
            }
        }
        
        if (200 !== $response->http_code || TRUE === $this->debug) {
            if(empty($response->action)) {
                // feels prudent to provide limited return data in this case, also feels messy
                unset($response->logs);
                unset($response->action);
            } else {
                $response->logs = fauxApiLogger::get_logs($this->debug);
            }
        } else {
            unset($response->logs);
        }
        
        if(is_null($response->data)) {
            unset($response->data);
        }
        
        return $response;
    }
    
    /**
     * __check_user_action_call()
     * 
     * @param string $user_action
     * @param array $user_args
     * @param string $user_data
     * @return object
     */
    private function __check_user_action_call($Actions, $user_action, $user_args, $user_data) {
        fauxApiLogger::debug(__METHOD__);
        
        // create a $response object to work with below
        $checked = (object) array(
            'http_code' => NULL, 
            'message' => NULL, 
            'action'=> NULL, 
            'args' => array(),
            'data' => NULL
        );
        
        // confirm request is authenticated before we get too invloved with it
        if (TRUE !== $this->Auth->is_auth()) {
            $checked->http_code = 401;
            $checked->message = 'authentication failed';
            fauxApiLogger::error($checked->message);
            return $checked;
        }
        
        // confirm the user action data does not need to be scrubbed
        $permitted = array('_');
        $checked->action = fauxApiUtils::sanitize((string)$user_action, $permitted);
        if($user_action !== $checked->action) {
            $checked->http_code = 400;
            $checked->message = 'user action name contains non-permitted values';
            fauxApiLogger::error($checked->message, array(
                'user_action' => $user_action,
                'checked_action' => $checked->action
            ));
            return $checked;
        }

        // confirm the method being called actually exists
        if (!method_exists($Actions, $checked->action)) {
            $checked->http_code = 404;
            $checked->message = 'api action is not defined';
            fauxApiLogger::error($checked->message, array(
                'checked_action' => $checked->action
            ));
            return $checked;
        }
        
        // confirm any user_data provided is valid JSON
        if(!empty($user_data)) {
            $checked->data = @json_decode($user_data, TRUE);
            if(NULL === $checked->data && json_last_error() !== JSON_ERROR_NONE) {
                $checked->http_code = 400;
                $checked->message = 'user action data is not valid JSON';
                fauxApiLogger::error($checked->message);
                return $checked;
            }
        }
        
        // sanity check and clean up the user args provided direct from $_GET
        foreach($user_args as $arg_key=>$arg_value) {
            $permitted = array(' ','_','-','.','/');
            if($arg_key !== fauxApiUtils::sanitize($arg_key, $permitted) || $arg_value !== fauxApiUtils::sanitize($arg_value, $permitted)) {
                $checked->http_code = 400;
                $checked->message = 'user action arguments contain non-permitted values';
                fauxApiLogger::error($checked->message, array(
                    'arg_key' => $arg_key,
                    'arg_value' => $arg_value,
                ));
                return $checked;
            }
            if($arg_key !== 'action') {
                if('true'=== strtolower($arg_value)) {
                    $arg_value = TRUE;
                }
                elseif('false' === strtolower($arg_value)) {
                    $arg_value = FALSE;
                }
                if('__debug' === $arg_key) {
                    $this->debug = $arg_value;
                }
                else {
                    $checked->args[$arg_key] = $arg_value;
                }
            }
        }
        
        fauxApiLogger::debug(__METHOD__.'() checks all passed');
        return $checked;
    }
}
