<?php
global $priv_list;

$priv_list['page-services-telegraf'] = array();
$priv_list['page-services-telegraf']['name'] = "WebCfg - Services: Telegraf";
$priv_list['page-services-telegraf']['descr'] = "Allow access to Telegraf package GUI";
$priv_list['page-services-telegraf']['match'] = array();
$priv_list['page-services-telegraf']['match'][] = "pkg_edit.php?xml=telegraf.xml*";
?>
