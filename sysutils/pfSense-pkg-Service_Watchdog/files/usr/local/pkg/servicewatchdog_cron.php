#!/usr/local/bin/php-cgi -f
<?php
require_once("globals.inc");
require_once("servicewatchdog.inc");

global $g;

/* Do nothing at bootup. */
if ($g['booting'] || file_exists("{$g['varrun_path']}/booting")) {
	return;
}

servicewatchdog_check_services();
?>