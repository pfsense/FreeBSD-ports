<?php
/*
 * pkg_tailscale.inc
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

$shortcuts['tailscale'] = [];
$shortcuts['tailscale']['main'] = '/pkg_edit.php?xml=tailscale.xml';
$shortcuts['tailscale']['status'] = '/status_tailscale.php';
$shortcuts['tailscale']['service'] = 'tailscale';

$shortcuts['tailscale_auth'] = [];
$shortcuts['tailscale_auth']['main'] = '/pkg_edit.php?xml=tailscale/tailscale_auth.xml';
$shortcuts['tailscale_auth']['status'] = '/status_tailscale.php';
$shortcuts['tailscale_auth']['service'] = 'tailscale';
