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

/**
 * quasi-private global $__fauxapi_logs variable to store log messages
 */
$__fauxapi_logs = array();

class fauxApiLogger {
    
    public static $log_count_limit = 100;
    public static $syslog_filename = 'fauxapi.log';

    public static function debug($message, $data=NULL) {
        fauxApiLogger::write_syslog(
            LOG_DEBUG,
            fauxApiLogger::append_fauxapi_logs_entry('debug', $message, $data)
        );
    }

    public static function info($message, $data=NULL) {
        fauxApiLogger::write_syslog(
            LOG_INFO,
            fauxApiLogger::append_fauxapi_logs_entry('info', $message, $data)
        );
    }

    public static function warn($message, $data=NULL) {
        fauxApiLogger::write_syslog(
            LOG_WARNING,
            fauxApiLogger::append_fauxapi_logs_entry('warn', $message, $data)
        );
    }

    public static function error($message, $data=NULL) {
        fauxApiLogger::write_syslog(
            LOG_ERR,
            fauxApiLogger::append_fauxapi_logs_entry('error', $message, $data)
        );
    }
    
    public static function get_logs($include_debug=FALSE) {
        global $__fauxapi_logs;
        $logs = array();
        foreach($__fauxapi_logs as $log) {
            if(array_key_exists('DEBUG', $log) && TRUE === $include_debug) {
                $logs[] = $log;
            }
            elseif(!array_key_exists('DEBUG', $log)) {
                $logs[] = $log;
            }
        }
        return $logs;
    }
    
    private static function write_syslog($level, $log) {
        #openlog(fauxApiLogger::$syslog_filename, LOG_PID | LOG_LOCAL0);
        syslog($level, json_encode($log));
        #closelog();
    }
    
    private static function append_fauxapi_logs_entry($type, $message, $data) {
        global $__fauxapi_logs;
        $ts = date('Ymd\ZHis', time()); // zulu time
        if(is_null($data)){
            $log[strtoupper($type)] = $ts.' :: '.$message;
        } else {
            $log[strtoupper($type)] = $ts.' :: '.$message;
            $log['DATA'] = $data;
        }
        $__fauxapi_logs[] = $log;
        
        if(count($__fauxapi_logs) > fauxApiLogger::$log_count_limit) {
            array_shift($__fauxapi_logs);
        }
        return $log;
    }

}
