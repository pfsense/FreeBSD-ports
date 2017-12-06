#!/usr/local/bin/php
<?php
####
# Command Line Arguments
# -i opt2 (Modem interface in pfSense, will be determined automatiucally otherwise)
# -p cuaXX (Modem data port)
# -r 10 (Reset modem after X tries)
###

require_once("/etc/inc/interfaces.inc");
require_once("/etc/inc/pfsense-utils.inc");

$options = getopt("i:p:r:");

$port = "/dev/cuaZ99.1";

$reset = False;
$reset_file = '/tmp/cellular.reset';

if ($options["p"] && file_exists($options["p"])) {
        $port = $options["p"];
}

if ($options["r"]) {
        $reset = $options["r"];
}

if ($options["i"]) {
        $interface = $options["i"];
} else {
        $ifdescrs = get_configured_interface_with_descr(false, true);
        foreach ($ifdescrs as $ifdescr => $ifname) {
                $ifinfo = get_interface_info($ifdescr);
                if (preg_match("/ppp[0-9]*/", $ifinfo['hwif'])) {
                        $interface = $ifdescr;
                        break;
                }
        }
}

$ifinfo = get_interface_info($interface);

if ($ifinfo['ppplink']) {
        if (( $ifinfo['ppplink'] != "up" || empty($ifinfo['ipaddr']) ) && !$ifinfo['nodevice']) {

                #Do we have a reset counter
                if ($reset) {
                        if (is_file($reset_file)) {
                                $count = file_get_contents($reset_file);
                                $count += 1;
                        } else {
                                $count = 1;
                        }

                        if ($count >= $reset) {
                                print "Resetting Modem";
                                shell_exec('echo "AT^RESET" > ' . escapeshellarg($port));
                                $count = 0;
                        }

                        file_put_contents($reset_file, $count);
                }

                print "Restarting Connection";
                interface_configure($interface);
        } else {
                if ($reset) {
                        file_put_contents($reset_file, 0);
                }

                print "PPP is running";
        }
}

?>