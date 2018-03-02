<?php
/*
 * vnstat_fetch_json.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008-2016 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

header('Content-Type: application/json');

require_once('auth_check.inc');

$json_string = '';
$fd = popen("vnstat --json", "r");

$error = str_replace("\n", ' ', fgets($fd));
$json_string = $error;

while (!feof($fd)) {
	$json_string .= fgets($fd);

	if(substr($json_string, 0, 5) === "Error") {
		$error = str_replace("\n", ' ', substr($json_string, 7));
		die('{ "error" : "' . trim(htmlspecialchars($error)) . '" }');
	}
}

pclose($fd);

//TODO prep json here instead of client-side

$json_obj = json_decode($json_string, true);

if(empty($json_obj)) {
	die('{ "error" : "'.trim(htmlspecialchars($error)).'" }');
}

echo json_encode($json_obj,JSON_PRETTY_PRINT|JSON_PARTIAL_OUTPUT_ON_ERROR|JSON_NUMERIC_CHECK);

?>
