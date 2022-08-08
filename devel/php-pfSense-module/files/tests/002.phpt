--TEST--
test1() Basic test
--EXTENSIONS--
pfsense
--FILE--
<?php
$ret = test1();

var_dump($ret);
?>
--EXPECT--
The extension pfsense is loaded and working!
NULL
