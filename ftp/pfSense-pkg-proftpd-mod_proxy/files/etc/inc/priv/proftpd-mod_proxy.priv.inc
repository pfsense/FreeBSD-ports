<?php
/*
 * proftpd-mod_proxy.priv.inc
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

$priv_list['page-services-proftpd-mod_proxy'] = array();
$priv_list['page-services-proftpd-mod_proxy']['name'] = "WebCfg - Services: FTP Reverse Proxy package";
$priv_list['page-services-proftpd-mod_proxy']['descr'] = "Allow access to FTP Reverse Proxy package GUI";
$priv_list['page-services-proftpd-mod_proxy']['match'] = array();
$priv_list['page-services-proftpd-mod_proxy']['match'][] = "pkg*.php?xml=proftpd-mod_proxy.xml*";
$priv_list['page-services-proftpd-mod_proxy']['match'][] = "pkg*.php?xml=proftpd-mod_proxy_users.xml*";

?>
