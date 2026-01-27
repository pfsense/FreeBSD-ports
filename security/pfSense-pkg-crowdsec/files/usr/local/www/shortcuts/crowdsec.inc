<?php
/*
 * crowdsec.inc
 *
 * part of pfSense (https://www.pfSense.org/)
 * Copyright (c) 2023 CrowdSec
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

$shortcuts['crowdsec']				= [];
$shortcuts['crowdsec']['main']		= '/pkg_edit.php?xml=crowdsec.xml';
$shortcuts['crowdsec']['log']           = '/status_logs_packages.php?pkg=crowdsec';
$shortcuts['crowdsec']['status']        = '/crowdsec/status.php';
// No service shortcut because there are two, crowdsec and crowdsec_firewall; it would be confusing
