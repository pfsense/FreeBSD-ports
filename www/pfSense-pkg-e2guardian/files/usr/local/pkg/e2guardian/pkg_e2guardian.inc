<?php
/*
 * pkg_e2guardian.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015 Marcello Coutinho
 * Copyright (c) 2020 Rubicon Communications, LLC (Netgate)
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

global $shortcuts;

$shortcuts['e2guardian'] = array();
$shortcuts['e2guardian']['main'] = "pkg_edit.php?xml=e2guardian.xml";
$shortcuts['e2guardian']['status'] = "status_services.php";
$shortcuts['e2guardian']['service'] = "e2guardian";

?>
