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

class fauxApiAuth {

    private $credentials = array();
    private $api_credentials_file = '/etc/fauxapi/credentials.ini';
    private $api_timestamp_delta_max = 60;
    private $api_demo_credentials = array(
        'PFFAexample01' => 'abcdefghijklmnopqrstuvwxyz0123456789abcd',
        'PFFAexample02' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789ABCD',
    );

    /**
     * is_authenticated()
     * 
     * @return boolean
     */
    public function is_authenticated() {
        fauxApiLogger::debug(__METHOD__);

        if (!isset($_SERVER['HTTP_FAUXAPI_AUTH'])) {
            fauxApiLogger::error('request missing FAUXAPI_AUTH header value');
            return FALSE;
        }

        // make sure the AUTH is well formed and has expected input
        $elements = split(':', fauxApiUtils::sanitize($_SERVER['HTTP_FAUXAPI_AUTH'], array(':')));
        if (4 !== count($elements)) {
            fauxApiLogger::error('unexpected number of FAUXAPI_AUTH elements supplied', array(
                $elements
            ));
            return FALSE;
        }

        $auth = array(
            'apikey' => $elements[0],
            'timestamp' => $elements[1],
            'nonce' => $elements[2],
            'hash' => $elements[3],
        );
        
        if(!$this->load_credentials($this->api_credentials_file, $auth['apikey'])) {
            return FALSE;
        }
        
        // make sure demo credentials are not in use
        foreach($this->api_demo_credentials as $apidemo_key => $apidemo_secret) {
            if($apidemo_key === $auth['apikey'] || $apidemo_secret === $this->credentials[FAUXAPI_CALLID]['secret']) {
                fauxApiLogger::error('demo api credential value in use, these MUST be changed!');
                return FALSE;
            }
        }

        // confirm the timestamp is valid
        $caller_ts = strtotime($auth['timestamp']);
        $system_ts = time();
        
        if ($system_ts < ($caller_ts - $this->api_timestamp_delta_max) || 
                $system_ts > ($caller_ts + $this->api_timestamp_delta_max)) {
            fauxApiLogger::error('timestamp provided is out-of-bounds', array(
                'caller_time' => gmdate('Ymd\ZHis', $caller_ts), 
                'system_time' => gmdate('Ymd\ZHis', $system_ts)
            ));
            return FALSE;
        }

        // TODO - nonce
        // these nonce values really should be checked to ensure requests are not
        // replayed by a third party, should be easy enough to do with files
        // contained in a /tmp/fauxapi/nonce path for example

        // confirm the nonce is valid
        if (strlen($auth['nonce']) < 8 || strlen($auth['nonce']) > 40) {
            fauxApiLogger::error('length of nonce value is out-of-bounds');
            return FALSE;
        }
      
        // confirm the api credentials now in use meet the rules:-
        //  - NB1: refer to fauxApiUtils::sanitize() above, hence alpha numeric only
        //  - NB2: <apikey_value> MUST start with the prefix PFFA (pfSense Faux API)
        //  - NB3: <apikey_value> MUST be >= 12 chars AND <= 40 chars in total length
        //  - NB4: <apisecret_value> MUST be >= 40 chars AND <= 128 chars in length
        if('PFFA' !== substr($auth['apikey'], 0, 4) || strlen($auth['apikey']) < 12 || strlen($auth['apikey']) > 40) {
            fauxApiLogger::error('apikey is out-of-bounds, refer to documentation');
            return FALSE;
        }
        if(strlen($this->credentials[FAUXAPI_CALLID]['secret']) < 40 || strlen($this->credentials[FAUXAPI_CALLID]['secret']) > 128) {
            fauxApiLogger::error('apisecret is out-of-bounds, refer to documentation');
            return FALSE;
        }

        // confirm the hash is valid
        $auth_hash_input = $this->credentials[FAUXAPI_CALLID]['secret'] . $auth['timestamp'] . $auth['nonce'];
        if (hash('sha256', $auth_hash_input) !== $auth['hash']) {
            fauxApiLogger::error('invalid hash value provided');
            return FALSE;
        }
        
        define('FAUXAPI_APIKEY', $auth['apikey']);
        fauxApiLogger::info('valid auth for call', array(
            'apikey' => FAUXAPI_APIKEY,
            'callid' => FAUXAPI_CALLID,
            'client_ip' => fauxApiUtils::get_client_ipaddr()
        ));
        return TRUE;
    }
    
    /**
     * is_authorized()
     * 
     * @param string $action
     * @return bool
     */
    public function is_authorized($action) {
        fauxApiLogger::debug(__METHOD__);
        
        if (!array_key_exists(FAUXAPI_CALLID, $this->credentials)) {
            fauxApiLogger::error('credentials for this action have not been established');
            return FALSE;
        }
        
        $permits = explode(',',str_replace(' ', '', $this->credentials[FAUXAPI_CALLID]['permit']));
        foreach($permits as $permit){
            if(fnmatch($permit, $action)) {
                fauxApiLogger::debug('permit allows action', array(
                    'action' => $action,
                    'permit' => $permit,
                    'permits' => $permits,
                ));
                return TRUE;
            }
        }
        fauxApiLogger::debug('permits do NOT allow action!', array(
            'action' => $action,
            'permits' => $permits,
        ));
        return FALSE;
    }
    
    /**
     * load_credentials()
     * 
     * @param string $filename
     * @return mixed
     */
    private function load_credentials($filename, $apikey) {
        fauxApiLogger::debug(__METHOD__);
        
        if(!is_file($filename)) {
            fauxApiLogger::error('unable to find fauxapi credentials file', array(
                'filename' => $filename
            ));
            return FALSE;
        }
        
        $ini_credentials = parse_ini_file($filename, TRUE);
        
        $credentials = array();
        foreach($ini_credentials as $key => $ini) {
            if(isset($ini['secret']) && !array_key_exists($key, $this->api_demo_credentials)) {
                $credentials[$key] = array(
                    'secret' => $ini['secret'],
                    'permit' => array_key_exists('permit', $ini) ? $ini['permit'] : ''
                );
            }
        }
        
        if (array_key_exists($apikey, $credentials)) {
            $this->credentials[FAUXAPI_CALLID] = $credentials[$apikey];
            return TRUE;
        }
        
        fauxApiLogger::error('apikey not defined in credential file', array(
            'apikey' => $apikey,
            'filename' => $filename
        ));
        return FALSE;
    }

}
