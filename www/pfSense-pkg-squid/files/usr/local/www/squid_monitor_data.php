<?php
/* ========================================================================== */
/*
	squid_monitor_data.php
	part of pfSense (http://www.pfSense.com)
	Copyright (C) 2012-2014 Marcello Coutinho
	Copyright (C) 2012-2014 Carlos Cesario - carloscesario@gmail.com
	All rights reserved.
                                                                              */
/* ========================================================================== */
/*
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
/* ========================================================================== */
# ------------------------------------------------------------------------------
# Defines
# ------------------------------------------------------------------------------
require_once("guiconfig.inc");

# ------------------------------------------------------------------------------
# Requests
# ------------------------------------------------------------------------------
if ($_POST) {
	# Actions
	$filter = preg_replace('/(@|!|>|<)/',"",htmlspecialchars($_POST['strfilter']));
	$program = strtolower($_POST['program']);
    switch ($program) {
       case 'squid':
			// Define log file
			$log='/var/squid/logs/access.log';
			//show table headers
			show_tds(array("Date","IP","Status","Address","User","Destination"));
			//fetch lines
			$logarr=fetch_log($log);
			// Print lines
    		foreach ($logarr as $logent) {
		        // Split line by space delimiter
				$logline  = preg_split("/\s+/", $logent);

		        // Apply date format to first line
		        //$logline[0] = date("d.m.Y H:i:s",$logline[0]);

		        // Word wrap the URL
		        $logline[7] = htmlentities($logline[7]);
		        $logline[7] = html_autowrap($logline[7]);

		        // Remove /(slash) in destination row
				$logline_dest =  preg_split("/\//", $logline[9]);

		        // Apply filter and color
				// Need validate special chars
		        if ($filter != "")
		            $logline = preg_replace("@($filter)@i","<spam><font color='red'>$1</font></span>",$logline);
				echo "<tr valign=\"top\">\n";
				echo "<td class=\"listlr\" nowrap>{$logline[0]} {$logline[1]}</td>\n";
				echo "<td class=\"listr\">{$logline[3]}</td>\n";
				echo "<td class=\"listr\">{$logline[4]}</td>\n";
		        echo "<td class=\"listr\" width=\"*\">{$logline[7]}</td>\n";
				echo "<td class=\"listr\">{$logline[8]}</td>\n";
				echo "<td class=\"listr\">{$logline_dest[1]}</td>\n";
				echo "</tr>\n";
		    }
			break;
		case 'sguard';
				$log='/var/squidGuard/log/block.log';
				//show table headers
				show_tds(array("Date-Time","ACL","Address","Host","User"));
				//fetch lines
				$logarr=fetch_log($log);
			    foreach ($logarr as $logent) {
			        // Split line by space delimiter
				    $logline  = preg_split("/\s+/", $logent);

			        // Apply time format
			        $logline[0] = date("d.m.Y", strtotime($logline[0]));

			    	// Word wrap the URL
			        $logline[4] = htmlentities($logline[4]);
			        $logline[4] = html_autowrap($logline[4]);


			        // Apply filter color
					// Need validate special chars
			        if ($filter != "")
			            $logline = preg_replace("@($filter)@i","<spam><font color='red'>$1</font></span>",$logline);


			        echo "<tr>\n";
			        echo "<td class=\"listlr\" nowrap>{$logline[0]} {$logline[1]}</td>\n";
			        echo "<td class=\"listr\">{$logline[3]}</td>\n";
					echo "<td class=\"listr\" width=\"*\">{$logline[4]}</td>\n";
			        echo "<td class=\"listr\">{$logline[5]}</td>\n";
			        echo "<td class=\"listr\">{$logline[6]}</td>\n";
			        echo "</tr>\n";
			    }
			break;
		case 'clamav';
				// Define log file
				$log='/var/log/c-icap/virus.log';
				//show table headers
				show_tds(array("Date-Time","Message","Virus","URL","Host","User"));
				//fetch lines
				$logarr=fetch_log($log);
			    	foreach ($logarr as $logent) {
			        	// Split line by space delimiter
				    	$logline  = preg_split("/\|/", $logent);

			        	// Apply time format
			        	$logline[0] = date("d.m.Y H:i:s", strtotime($logline[0]));

            				// Word wrap the URL
		        		$logline[3] = htmlentities($logline[3]);
		        		$logline[3] = html_autowrap($logline[3]);

			    		echo "<tr>\n";
			        	echo "<td class=\"listlr\" nowrap>{$logline[0]}</td>\n";
			        	echo "<td class=\"listr\" nowrap>{$logline[1]}</td>\n";
					echo "<td class=\"listr\">{$logline[2]}</td>\n";
			        	echo "<td class=\"listr\">{$logline[3]}</td>\n";
			        	echo "<td class=\"listr\">{$logline[4]}</td>\n";
			        	echo "<td class=\"listr\">{$logline[5]}</td>\n";
			        	echo "</tr>\n";
			    	}
			break;			
    }
}

# ------------------------------------------------------------------------------
# Functions
# ------------------------------------------------------------------------------

// From SquidGuard Package
function html_autowrap($cont)
{
	# split strings
	$p     = 0;
	$pstep = 25;
	$str   = $cont;
	$cont = '';
	for ( $p = 0; $p < strlen($str); $p += $pstep ) {
		$s = substr( $str, $p, $pstep );
		if ( !$s ) break;
			$cont .= $s . "<wbr/>";
	}
	return $cont;
}


// Show Squid Logs
function fetch_log($log){
	global $filter,$program;
	// Get Data from form post
    $lines = $_POST['maxlines'];
    if (preg_match("/!/",htmlspecialchars($_POST['strfilter'])))
    	$grep_arg="-iv";
    else
    	$grep_arg="-i";

    //Check program to execute or no the parser
	if($program == "squid")
		$parser = "| php -q squid_log_parser.php";
	else
		$parser = "";

    // Get logs based in filter expression
    if($filter != "") {
        exec("tail -2000 {$log} | /usr/bin/grep {$grep_arg} " . escapeshellarg($filter). " | tail -r -n {$lines} {$parser} " , $logarr);
    }
    else {
        exec("tail -r -n {$lines} {$log} {$parser}", $logarr);
    }
    // return logs
    return $logarr;
};

function show_tds($tds){
	echo "<tr valign='top'>\n";
	foreach ($tds as $td){
		echo "<td class='listhdrr'>".gettext($td)."</td>\n";
		}
	echo "</tr>\n";
}

?>
