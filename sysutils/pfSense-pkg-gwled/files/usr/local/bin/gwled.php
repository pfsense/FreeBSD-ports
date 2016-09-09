#!/usr/local/bin/php-cgi -q
<?php
/*
 * gwled.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011-2015 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
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
require_once("config.inc");
require_once("functions.inc");
require_once("gwled.inc");
require_once("led.inc");
require_once("gwlb.inc");

global $config;
$gwled_config = $config['installedpackages']['gwled']['config'][0];

if (($gwled_config['enable_led2']) && ($gwled_config['gw_led2'])) {
	gwled_set_status($gwled_config['gw_led2'], 2);
}
if (($gwled_config['enable_led3']) && ($gwled_config['gw_led3'])) {
	gwled_set_status($gwled_config['gw_led3'], 3);
}
?>
