<?php
/*
 * tftpd.priv.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2016 Stefan Seidel
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

global $priv_list;

$priv_list['page-services-tftpd'] = array();
$priv_list['page-services-tftpd']['name'] = "WebCfg - Services: tftpd package";
$priv_list['page-services-tftpd']['descr'] = "Allow access to tftpd package GUI";
$priv_list['page-services-tftpd']['match'] = array();

$priv_list['page-services-tftpd']['match'][] = "pkg_edit.php?xml=tftpd.xml*";
$priv_list['page-services-tftpd']['match'][] = "tftp_files.php*";

?>
