<?php
/*
	sfpnfo.inc.php
	pfSense package (https://www.pfSense.org/)
	Copyright (C) 2023 Marco Goetze
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
require_once("pfsense-utils.inc");
require_once("services.inc");
require_once("util.inc");



/**
 * Checks if the given interface name is plausible and returns the result of the ifconfig command.
 *
 * @param string $IFName The name of the interface to check. Only ix[0-9] are accepted.
 * @return mixed The output of the ifconfig command if the interface name is plausible, or -1 if the interface name is not in the expected format.
 */
function sfpnfo_runIfConfig($IFName) {
		// Check for plausible interface names or return error
		if(preg_match("/^ix[0-9]+$/", $IFName) === 1) {
			return shell_exec("/sbin/ifconfig -vm " . escapeshellarg($IFName));
		} else {
			// Interface is not ix[0-9]
			return -1;
		}
}

/**
 * Extracts information from the given sfpnfo_runIfConfig() output and returns it as an array.
 *
 * @param string $ifvmOutput the output of ifconfig -vm to be processed
 * @return array The extracted information as Array
 */
function sfpnfo_structure_output($ifvmOutput) {
	$ifvmOutput = trim($ifvmOutput);

	// Empty Response?
	if (empty($ifvmOutput)) {
		return -2;
	}

	// NO SFP Found in IF
	if (!str_contains($ifvmOutput, "plugged:")) {
		return -3;
	}

	// Good, collect the information
	preg_match('/plugged:\s*(.*?)\s*vendor:\s*(.*?)\s*PN:\s*(.*?)\s*SN:\s*(.*?)\s*DATE:\s*(.*?)\s*module temperature:\s*(.*?)\s*Voltage:\s*(.*?)\s*RX:\s*(.*?)\s*TX:\s*([^TX:]+)=eof=/s', $ifvmOutput . PHP_EOL . "=eof=", $matches);

	$DATA = [
		"plugged" => trim($matches[1]),
		"vendor" => trim($matches[2]),
		"pn" => trim($matches[3]),
		"sn" => trim($matches[4]),
		"date" => trim($matches[5]),
		"temp" => trim($matches[6]),
		"voltage" => trim($matches[7]),
		"rx" => trim($matches[8]),
		"tx" => trim($matches[9])
	];

	return $DATA;
}


/**
 * Retrieves the list of interfaces that start with the prefix "ix" + number using the ifconfig command.
 *
 * @return array An array containing the names of the interfaces.
 */
function sfpnfo_get_ix_interfaces() {
	$tmp = shell_exec("/sbin/ifconfig -l ");
	$tmp = explode(" ", $tmp);
	$interfaces = array_filter($tmp, function($interface) {
		return preg_match("/^ix[0-9]+$/", $interface) === 1;
	});
	return array_map('trim', $interfaces);
}


/**
 * Displays a table row if the specified data is not empty.
 *
 * @param array $data The array of data to dosplay.
 * @param string $key The key to access the data.
 * @param string $label The label to display in the table.
 * @return void
 */
function display_row_if_not_empty($data, $key, $label) {
	if (!empty($data[$key])) {
		echo "<tr><th>".$label."</th><td>".htmlentities($data[$key])."</td></tr>";
	}
}
