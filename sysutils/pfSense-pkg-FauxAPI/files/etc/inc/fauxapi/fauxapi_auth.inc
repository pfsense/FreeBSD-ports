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

    private $api_credentials_file = '/etc/fauxapi/credentials.ini';
    private $api_timestamp_delta_max = 60;
    private $api_demo_credentials = array(
        'PFFAexample01' => 'abcdefghijklmnopqrstuvwxyz0123456789abcd',
        'PFFAexample02' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789ABCD',
    );

    /**
     * is_auth()
     * 
     * @return boolean
     */
    public function is_auth() {
        fauxApiLogger::debug(__METHOD__);

        if (!isset($_SERVER['HTTP_FAUXAPI_AUTH'])) {
            fauxApiLogger::error('request missing FAUXAPI_AUTH header value');
            return FALSE;
        }

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
        
        $api_credentials = $this->load_credentials($this->api_credentials_file);
        if(!$api_credentials) {
            return FALSE;
        }

        // confirm the presented apikey is a known credential
        if (!array_key_exists($auth['apikey'], $api_credentials)) {
            fauxApiLogger::error('apikey provided is unknown', array(
                'apikey' => $auth['apikey']
            ));
            return FALSE;
        }
        
        // make sure demo credentials are not in use
        foreach($this->api_demo_credentials as $apidemo_key => $apidemo_secret) {
            if($apidemo_key === $auth['apikey'] || $apidemo_secret === $api_credentials[$auth['apikey']]) {
                fauxApiLogger::error('demo api credential value in use, these must be changed!');
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
        if(strlen($api_credentials[$auth['apikey']]) < 40 || strlen($api_credentials[$auth['apikey']]) > 128) {
            fauxApiLogger::error('apisecret is out-of-bounds, refer to documentation');
            return FALSE;
        }

        // confirm the hash is valid
        $auth_hash_input = $api_credentials[$auth['apikey']] . $auth['timestamp'] . $auth['nonce'];
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
     * load_credentials()
     * 
     * @param string $filename
     * @return mixed
     */
    private function load_credentials($filename) {
        fauxApiLogger::debug(__METHOD__);
        
        if(!is_file($filename)) {
            fauxApiLogger::error('unable to find fauxapi credentials file', array(
                'filename' => $filename
            ));
            return FALSE;
        }
        
        $ini_credentials = parse_ini_file($filename, TRUE);
        
        $credentials = array();
        foreach($ini_credentials as $api_key => $api_secret) {
            if(isset($api_secret['secret']) &&!array_key_exists($api_key, $this->api_demo_credentials)) {
                $credentials[$api_key] = $api_secret['secret'];
            }
        }
        
        return $credentials;
    }

}
