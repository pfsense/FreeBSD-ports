<?php

global $priv_list;

$priv_list['page-status-sarg-reports'] = array();
$priv_list['page-status-sarg-reports']['name'] = "WebCfg - Status: Sarg reports";
$priv_list['page-status-sarg-reports']['descr'] = "Allow access to Sarg reports page.";
$priv_list['page-status-sarg-reports']['match'] = array();
$priv_list['page-status-sarg-reports']['match'][] = "sarg_reports.php*";
$priv_list['page-status-sarg-reports']['match'][] = "sarg_frame.php*";
$priv_list['page-status-sarg-reports']['match'][] = "sarg_ng.php*";
$priv_list['page-status-sarg-reports']['match'][] = "sarg_realtime.php*";
$priv_list['page-status-sarg-reports']['match'][] = "pkg_edit.php?xml=sarg.xml*";

?>
