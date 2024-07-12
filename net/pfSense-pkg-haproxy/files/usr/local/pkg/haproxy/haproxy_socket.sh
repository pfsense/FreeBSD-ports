#!/usr/local/bin/php-cgi -f
<?php
/*
 * haproxy_socket.sh
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2016 PiBa-NL
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

include_once('haproxy_socketinfo.inc');

$first = true;
$args = "";
foreach($argv as $arg) {
	if ($first) {
		$first = false;
		continue;
	}
	$args .= "{$arg} ";
}

echo $args;

$result = haproxy_socket_command($args);
foreach($result as $line)
	echo $line;
