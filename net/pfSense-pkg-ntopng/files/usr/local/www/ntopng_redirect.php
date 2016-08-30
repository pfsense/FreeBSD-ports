<?php

require_once("config.inc");

global $config;

$proto = $config['system']['webgui']['protocol'];

$httphost = getenv("HTTP_HOST");
$colonpos = strpos($httphost, ":");
if ($colonpos) {
	$baseurl = substr($httphost, 0, $colonpos);
} else {
	$baseurl = $httphost;
}

/* Change if port becomes configurable */
$port=3000;

$url = "$proto://$baseurl:$port";
header("Location: $url");

?>
