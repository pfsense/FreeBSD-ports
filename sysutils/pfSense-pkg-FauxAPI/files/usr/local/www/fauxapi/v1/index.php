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
define('FAUXAPI_CALLID', uniqid());

include_once('/etc/inc/fauxapi/fauxapi.inc');

$action = (string)filter_input(INPUT_GET, 'action');
if(empty($action)) { 
    $action = 'undefined'; 
}

$fauxapi = new fauxApi();
$response = $fauxapi->$action($_GET, file_get_contents("php://input"));

http_response_code($response->http_code);
if(!empty($response->action)) {
    header('fauxapi-callid: ' . FAUXAPI_CALLID);
}
header('Content-Type: application/json');

unset($response->http_code);
echo json_encode($response);
