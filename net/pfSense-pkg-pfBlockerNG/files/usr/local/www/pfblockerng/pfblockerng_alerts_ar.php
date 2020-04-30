<?php
/*
 * pfblockerng_alerts_ar.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2016 BBcan177@gmail.com
 * All rights reserved.
 *
 * Javascript Hostname Lookup modifications by J. Nieuwenhuizen
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

// Auto-resolve hostnames
if (isset($_REQUEST['getpfhostname'])) {
	$getpfhostname = trim(htmlspecialchars($_REQUEST['getpfhostname']));
	if (strlen($getpfhostname) >= 8) {
		$hostname = htmlspecialchars(gethostbyaddr($getpfhostname), ENT_QUOTES);
	} else {
		$hostname = $getpfhostname;
	}
	if ($hostname == $getpfhostname) {
		$hostname = 'unknown';
	}
	echo $hostname;
}

?>
