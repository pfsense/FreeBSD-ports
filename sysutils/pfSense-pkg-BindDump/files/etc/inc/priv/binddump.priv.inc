<?php
/*
 * binddump.priv.inc
 *
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

<<<<<<< HEAD
<<<<<<< HEAD
$priv_list['page-sysutils-binddump'] = array();
$priv_list['page-sysutils-binddump']['name'] = "WebCfg - sysutils: binddump package";
$priv_list['page-sysutils-binddump']['descr'] = "Allow access to binddump package GUI";

$priv_list['page-sysutils-binddump']['match'] = array();
$priv_list['page-sysutils-binddump']['match'][] = "packages/binddump/binddump.php*";
=======
$priv_list['page-sysutils-binddumppackage'] = array();
$priv_list['page-sysutils-binddumppackage']['name'] = "WebCfg - sysutils: binddump package";
$priv_list['page-sysutils-binddumppackage']['descr'] = "Allow access to binddump package GUI";

$priv_list['page-sysutils-binddumppackage']['match'] = array();
$priv_list['page-sysutils-binddumppackage']['match'][] = "packages/binddump/binddump.php*";
>>>>>>> cb7c404ba (pfSense-pkg-BindDump: Show all Zone Records which are currently served by BIND inkluding DDNS entries and the ability to delete DDNS records from master zones without bind restart. Initial release.)
=======
$priv_list['page-sysutils-binddumppackage'] = array();
$priv_list['page-sysutils-binddumppackage']['name'] = "WebCfg - sysutils: binddump package";
$priv_list['page-sysutils-binddumppackage']['descr'] = "Allow access to binddump package GUI";

$priv_list['page-sysutils-binddumppackage']['match'] = array();
$priv_list['page-sysutils-binddumppackage']['match'][] = "packages/binddump/binddump.php*";
>>>>>>> c4a744170 (﻿pfSense-pkg-BindDump: Show all Zone Records which are currently served by BIND inkluding DDNS entries and the ability to delete DDNS records from master zones without bind restart. Initial release.)

?>
