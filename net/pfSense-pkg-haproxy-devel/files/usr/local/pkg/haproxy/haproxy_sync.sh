#!/usr/local/bin/php -f
<?php

require_once('haproxy.inc');
echo "HAProxy Start sync ". (new DateTime('NOW'))->format('c') . "\n";
haproxy_do_xmlrpc_sync();
echo "HAProxy Stop sync ". (new DateTime('NOW'))->format('c') . "\n";
