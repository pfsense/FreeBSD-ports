<?php
/*
 * ntopng_redirect.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2016 Denny Page <dennypage@me.com>
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

$nocsrf = true;
require_once("config.inc");
require_once("guiconfig.inc");

global $config;

$proto = $config['system']['webgui']['protocol'];

$httphost = getenv("HTTP_HOST");
$colonpos = strpos($httphost, ":");
if ($colonpos) {
	$baseurl = substr($httphost, 0, $colonpos);
} else {
	$baseurl = $httphost;
}

/* Change if port becomes configurable */
$port=3000;

$url = "$proto://$baseurl:$port";
header("Location: $url");

?>
