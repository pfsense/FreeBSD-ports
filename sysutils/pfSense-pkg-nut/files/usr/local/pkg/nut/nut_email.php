#!/usr/local/bin/php-cgi -q
<?php
/*
 * nut_email.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2016 Denny Page
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

require_once("notices.inc");

$subject = "UPS Notification from " . gethostname();
$message = date('r') . "\n\n";
$message .= implode(' ', array_slice($argv, 1));

@notify_all_remote($subject . " - " . $message);
