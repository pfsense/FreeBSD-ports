#!/usr/local/bin/php-cgi -f
<?php

include_once('haproxy_socketinfo.inc');

$first = true;
$args = "";
foreach($argv as $arg) {
	if ($first) {
		$first = false;
		continue;
	}
	$args .= "{$arg} ";
}

echo $args;

$result = haproxy_socket_command($args);
foreach($result as $line)
	echo $line;