<?php
global $priv_list;

$priv_list['page-services-lteinterface'] = array();
$priv_list['page-services-lteinterface']['name'] = "WebCfg - Status: Cellular Data module package";
$priv_list['page-services-lteinterface']['descr'] = "GUI package for Cellular Cards";

$priv_list['page-services-lteinterface']['match'] = array();
$priv_list['page-services-lteinterface']['match'][] = "pkg.php?xml=cellular.xml*";

$priv_list['page-services-lteinterface']['match'][] = "pkg_edit.php?xml=cellular.xml*";

$priv_list['page-services-lteinterface']['match'][] = "widgets/widgets/cellular.widget.php*";

?>
