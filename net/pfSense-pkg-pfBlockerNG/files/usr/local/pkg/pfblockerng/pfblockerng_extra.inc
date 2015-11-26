<?php
/*
	pfBlockerNG_extra.inc

	pfBlockerNG
	Copyright (c) 2015 BBcan177@gmail.com
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

// This file is used to 'include functions' not yet merged into pfSense


// IPv6 Range to CIDR function used courtesey from:
// https://github.com/stilez/pfsense-leases/blob/50cc0fa81dba5fe91bcddaea016c245d1b8479cc/etc/inc/util.inc
function ip_range_to_subnet_array_temp($ip1, $ip2) {

	if (is_ipaddrv4($ip1) && is_ipaddrv4($ip2)) {
		$proto = 'ipv4';  // for clarity
		$bits = 32;
		$ip1bin = decbin(ip2long32($ip1));
		$ip2bin = decbin(ip2long32($ip2));
	} elseif (is_ipaddrv6($ip1) && is_ipaddrv6($ip2)) {
		$proto = 'ipv6';
		$bits = 128;
		$ip1bin = Net_IPv6::_ip2Bin($ip1);
		$ip2bin = Net_IPv6::_ip2Bin($ip2);
	} else
		return array();

	// it's *crucial* that binary strings are guaranteed the expected length;  do this for certainty even though for IPv6 it's redundant
	$ip1bin = str_pad($ip1bin, $bits, '0', STR_PAD_LEFT);
	$ip2bin = str_pad($ip2bin, $bits, '0', STR_PAD_LEFT);

	if ($ip1bin === $ip2bin)
		return array($ip1 . '/' . $bits);
	
	if (strcmp($ip1bin, $ip2bin) > 0)
		list ($ip1bin, $ip2bin) = array($ip2bin, $ip1bin);  // swap contents of ip1 <= ip2

	$rangesubnets = array();
	$netsize = 0;

	do {
		// at loop start, $ip1 is guaranteed strictly less than $ip2 (important for edge case trapping and preventing accidental binary wrapround)
		// which means the assignments $ip1 += 1 and $ip2 -= 1 will always be "binary-wrapround-safe"

		// step #1 if start ip (as shifted) ends in any '1's, then it must have a single cidr to itself (any cidr would include the '0' below it)
		
		if (substr($ip1bin, -1, 1) == '1') {
			// the start ip must be in a separate one-IP cidr range
			$new_subnet_ip = substr($ip1bin, $netsize, $bits - $netsize) . str_repeat('0', $netsize);
			$rangesubnets[$new_subnet_ip] = $bits - $netsize;
			$n = strrpos($ip1bin, '0');  //can't be all 1's
			$ip1bin = ($n == 0 ? '' : substr($ip1bin, 0, $n)) . '1' . str_repeat('0', $bits - $n - 1);  // BINARY VERSION OF $ip1 += 1
		} 

		// step #2, if end ip (as shifted) ends in any zeros then that must have a cidr to itself (as cidr cant span the 1->0 gap)
		
		if (substr($ip2bin, -1, 1) == '0') {
			// the end ip must be in a separate one-IP cidr range
			$new_subnet_ip = substr($ip2bin, $netsize, $bits - $netsize) . str_repeat('0', $netsize);
			$rangesubnets[$new_subnet_ip] = $bits - $netsize;
			$n = strrpos($ip2bin, '1');  //can't be all 0's
			$ip2bin = ($n == 0 ? '' : substr($ip2bin, 0, $n)) . '0' . str_repeat('1', $bits - $n - 1);  // BINARY VERSION OF $ip2 -= 1
			// already checked for the edge case where end = start+1 and start ends in 0x1, above, so it's safe
		}

		// this is the only edge case arising from increment/decrement.
		// it happens if the range at start of loop is exactly 2 adjacent ips, that spanned the 1->0 gap. (we will have enumerated both by now)
		
		if (strcmp($ip2bin, $ip1bin) < 0)
			continue;

		// step #3 the start and end ip MUST now end in '0's and '1's respectively
		// so we have a non-trivial range AND the last N bits are no longer important for CIDR purposes.

		$shift = $bits - max(strrpos($ip1bin, '0'), strrpos($ip2bin, '1'));  // num of low bits which are '0' in ip1 and '1' in ip2
		$ip1bin = str_repeat('0', $shift) . substr($ip1bin, 0, $bits - $shift);
		$ip2bin = str_repeat('0', $shift) . substr($ip2bin, 0, $bits - $shift);
		$netsize += $shift;
		if ($ip1bin === $ip2bin) {
			// we're done.
			$new_subnet_ip = substr($ip1bin, $netsize, $bits - $netsize) . str_repeat('0', $netsize);
			$rangesubnets[$new_subnet_ip] = $bits - $netsize;
			continue;
		}
		
		// at this point there's still a remaining range, and either startip ends with '1', or endip ends with '0'. So repeat cycle.
	} while (strcmp($ip1bin, $ip2bin) < 0);

	// subnets are ordered by bit size. Re sort by IP ("naturally") and convert back to IPv4/IPv6

	ksort($rangesubnets, SORT_STRING);
	$out = array();

	foreach ($rangesubnets as $ip => $netmask) {
		if ($proto == 'ipv4') {
			$i = str_split($ip, 8);
			$out[] = implode('.', array( bindec($i[0]),bindec($i[1]),bindec($i[2]),bindec($i[3]))) . '/' . $netmask;
		} else
			$out[] = Net_IPv6::compress(Net_IPv6::_bin2Ip($ip)) . '/' . $netmask;
	}

	return $out;
}

?>