#!/usr/bin/env php
<?php
require_once("notices.inc");

$timestamp=$argv[1];
$ifname=convert_real_interface_to_friendly_descr($argv[2]);
$hostname=$argv[3];
$ipaddr=$argv[4];
$new_hwaddr=$argv[5];
$new_hwaddr_org=$argv[6];
$old_hwaddr=$argv[7];
$old_hwaddr_org=$argv[8];

$msg = "\nANDwatch notificaton\n\n";
$msg .= sprintf("%22s: %s\n", "timestamp", $timestamp);
$msg .= sprintf("%22s: %s\n", "interface", $ifname);
$msg .= sprintf("%22s: %s\n", "hostname", $hostname);
$msg .= sprintf("%22s: %s\n", "ip address", $ipaddr);
$msg .= sprintf("%22s: %s\n", "new ethernet address", $new_hwaddr);
$msg .= sprintf("%22s: %s\n", "new ethernet org", $new_hwaddr_org);
$msg .= sprintf("%22s: %s\n", "old ethernet address", $old_hwaddr);
$msg .= sprintf("%22s: %s\n", "old ethernet org", $old_hwaddr_org);

notify_all_remote($msg);
?>
