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

class fauxApiUtils {

    /**
     * sanitize()
     * 
     * @param mixed $input
     * @param array $allowed
     * @param int $__recurse_count
     * @param int $__recurse_limit
     * @return mixed
     * @throws Exception
     * 
     * Based on CakePHP 2.1 paranoid mode sanitize function:-
     * http://api.cakephp.org/2.1/source-class-Sanitize.html#24-264
     */
    public static function sanitize($input, $allowed = array(), $__recurse_count = 0, $__recurse_limit = 10) {
        
        if ($__recurse_count > $__recurse_limit) {
            throw new \Exception('FATAL: recusion limit reached in sanitize()');
        }

        $allow = null;
        if (!empty($allowed)) {
            foreach ($allowed as $value) {
                $allow .= "\\$value";
            }
        }

        if (is_array($input)) {
            $cleaned = array();
            foreach ($input as $key => $clean) {
                $cleaned[$key] = fauxApiUtils::sanitize($clean, $allowed, $__recurse_count + 1);
            }
        } else {
            $cleaned = preg_replace("/[^{$allow}a-zA-Z0-9]/", '', $input);
        }
        return $cleaned;
    }
    
    /**
     * get_client_ipaddr()
     * @return string
     */
    public static function get_client_ipaddr() {
        $ipaddress = NULL;
        
        if(getenv('HTTP_CLIENT_IP')) {
            $ipaddress = getenv('HTTP_CLIENT_IP');
        }
        elseif(getenv('HTTP_X_FORWARDED_FOR')){
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        }
        elseif(getenv('HTTP_X_FORWARDED')) {
            $ipaddress = getenv('HTTP_X_FORWARDED');
        }
        elseif(getenv('HTTP_FORWARDED_FOR')) {
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        }
        elseif(getenv('HTTP_FORWARDED')) {
            $ipaddress = getenv('HTTP_FORWARDED');
        }
        elseif(getenv('REMOTE_ADDR')) {
            $ipaddress = getenv('REMOTE_ADDR');
        } else {
            $ipaddress = 'UNKNOWN';
        }
        return $ipaddress;
    }
    
    /**
     * get_request_scheme()
     * @return string
     */
    public static function get_request_scheme() {
        $scheme = NULL;
        
        if(getenv('REQUEST_SCHEME')) {
            $scheme = strtolower(getenv('REQUEST_SCHEME'));
        }
        elseif(getenv('HTTPS') && strtolower(getenv('HTTPS')) !== 'off') {
            $scheme = 'https';
        }
        elseif(getenv('SERVER_PORT') && (int)getenv('SERVER_PORT') === 443) {
            $scheme = 'https';
        } else {
            $scheme = 'http';
        }
        return $scheme;
    }
    
    /**
     * get_request_ipaddr()
     * @return string
     */
    public static function get_request_ipaddr() {
        $ipaddress = NULL;
            
        if(getenv('SERVER_ADDR')) {
            $ipaddress = getenv('SERVER_ADDR');
        } else {
            $ipaddress = 'UNKNOWN';
        }
        return $ipaddress;
    }
}
