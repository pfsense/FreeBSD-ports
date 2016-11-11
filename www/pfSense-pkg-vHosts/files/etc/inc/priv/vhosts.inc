<?php
/*******************************************************************************
* vHosts package privileges.
* ------------------------------------------------------------------------------
* Part of pfSense 2.3 and later (https://www.pfSense.org/).
*
* Copyright (c) 2016 Rubicon Communications, LLC (Netgate)
* Licensed under the Apache 2.0 License.
*
* Copyright (C) 2016 Softlife Consulting
* Licensed under the same terms and conditions as pfSense itself.
*
* License: https://github.com/pfsense/pfsense/blob/master/LICENSE
*******************************************************************************/
global $priv_list;

$priv_list['page-services-vhosts'] = array();
$priv_list['page-services-vhosts']['name'] = "WebCfg - Services: vHosts package";
$priv_list['page-services-vhosts']['descr'] = "Allow access to vHosts package GUI";

$priv_list['page-services-vhosts']['match'] = array();
$priv_list['page-services-vhosts']['match'][] = "packages/vhosts.php*";

?>
