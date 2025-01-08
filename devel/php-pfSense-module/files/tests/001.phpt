--TEST--
Check if pfsense is loaded
--EXTENSIONS--
pfsense
--FILE--
<?php
echo 'The extension "pfsense" is available';
?>
--EXPECT--
The extension "pfsense" is available
