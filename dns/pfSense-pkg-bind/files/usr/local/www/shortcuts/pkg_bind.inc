<?php
/*
 * pkg_bind.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2022-2024 Rubicon Communications, LLC (Netgate)
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

$shortcuts['bind']				= [];
$shortcuts['bind']['main']		= '/pkg_edit.php?xml=bind.xml';
$shortcuts['bind']['service']	= 'named';

$shortcuts['bind_acls']			= $shortcuts['bind'];
$shortcuts['bind_sync']			= $shortcuts['bind'];
$shortcuts['bind_views']		= $shortcuts['bind'];
$shortcuts['bind_zones']		= $shortcuts['bind'];
