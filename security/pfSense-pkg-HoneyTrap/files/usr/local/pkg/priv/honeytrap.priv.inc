<?php
/*
 * honeytrap.priv.inc
 *
 * part of pfSense (http://www.pfsense.org)
 * Copyright (c) 2019 DutchSec (https://dutchsec.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

global $priv_list;

$priv_list['page-service-honeytrap'] = array();
$priv_list['page-service-honeytrap']['name'] = 'WebCfg - Services: HoneyTrap package';
$priv_list['page-service-honeytrap']['descr'] = 'Allow acces to HoneyTrap package GUI';
$priv_list['page-service-honeytrap']['match'] = array();
$priv_list['page-service-honeytrap']['match'][] = 'honeytrap/honeytrap_settings.php*';
$priv_list['page-service-honeytrap']['match'][] = 'honeytrap/honeytrap_logs.php*';
?>
