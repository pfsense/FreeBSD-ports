<?php

require("guiconfig.inc");

$profileDir   = '/usr/local/libdata/vpn-profile';
$ovpnProfile = 'remote-access-openvpn.ovpn';

if (file_exists("$profileDir/$ovpnProfile")) {

	header('Content-Description: File Transfer');
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename='.$ovpnProfile);
	header('Content-Transfer-Encoding: binary');
	header('Expires: 0');
	header('Cache-Control: must-revalidate');
	header('Pragma: public');
	header('Content-Length: ' . filesize("$profileDir/$ovpnProfile"));
	ob_clean();
	flush();
	readfile("$profileDir/$ovpnProfile");
} else {
	echo "OpenVPN profile $ovpnProfile does not exist\n";
}

exit;

?>
