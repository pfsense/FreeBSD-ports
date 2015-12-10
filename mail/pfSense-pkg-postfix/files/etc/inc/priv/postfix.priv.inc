<?php

global $priv_list;

$priv_list['page-diagnostics-search-email'] = array();
$priv_list['page-diagnostics-search-email']['name'] = "WebCfg - Diagnostics: Postfix search email";
$priv_list['page-diagnostics-search-email']['descr'] = "Allow access to postfix search page.";
$priv_list['page-diagnostics-search-email']['match'] = array();
$priv_list['page-diagnostics-search-email']['match'][] = "postfix_search.php*";

$priv_list['page-status-postfix-queue'] = array();
$priv_list['page-status-postfix-queue']['name'] = "WebCfg - Status: Postfix queue";
$priv_list['page-status-postfix-queue']['descr'] = "Allow access to postfix queue page.";
$priv_list['page-status-postfix-queue']['match'] = array();
$priv_list['page-status-postfix-queue']['match'][] = "postfix_queue.php*";

$priv_list['page-services-postfix-forwarder'] = array();
$priv_list['page-services-postfix-forwarder']['name'] = "WebCfg - Services: All xml pages (config).";
$priv_list['page-services-postfix-forwarder']['descr'] = "Allow access to any xml package page.";
$priv_list['page-services-postfix-forwarder']['match'] = array();
$priv_list['page-services-postfix-forwarder']['match'][] = "pkg_edit.php*";
$priv_list['page-services-postfix-forwarder']['match'][] = "postfix*.php*";

?>