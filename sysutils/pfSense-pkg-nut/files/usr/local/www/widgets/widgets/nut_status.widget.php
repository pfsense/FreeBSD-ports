<?php
/*
    nut_status.widget.php

    part of pfSense (http://www.pfSense.org/)
    Copyright (C) 2015 ESF, LLC
    Copyright (C) 2016 Denny Page
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


require_once("/usr/local/www/widgets/include/nut_status.inc");
require_once("/usr/local/pkg/nut/nut.inc");


if ($_REQUEST && $_REQUEST['ajax']) {
	print_table();
	exit;
}


function print_row($desc, $value, $raw_data = false) {
	print '<tr>';
	print '  <td style="width:35%"><b>' . $desc . ':</b></td>';
	print '  <td>' . ($raw_data ? $value : htmlspecialchars($value)) . '</td>';
	print '</tr>';
}

function print_row_pct($desc, $value, $raw_data = false) {
	print '<tr>';
	print '  <td style="width:35%"><b>' . $desc . ':</b></td>';
	print '  <td>';
	print '    <div class="progress" style="max-width:300px">';
	print '      <div class="progress-bar progress-bar-striped" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . $value . '" style="width: ' . $value . '%">';
	print '      </div>';
	print '    </div>';
	print      ($raw_data ? $value : htmlspecialchars($value)) . '%';
	print '  </td>';
	print '</tr>';
}

function print_table() {
	$status = nut_ups_status();

	//Get connected remote clients (for local UPS only)
	global $config;
	$nut_config  = &$config['installedpackages']['nut']['config'][0];
	$clients_IPs = '';
	if (strpos($nut_config['type'], 'local') !== false) {
		//get connected ips
		exec("pfctl -s states | grep ':3493 ' | grep 'ESTABLISHED:ESTABLISHED' | cut -d ' ' -f 6 | cut -d ':' -f 1", $out_strings);
		foreach ($out_strings as &$client) {
			//trying to get client name
			$client .= ' (' . exec("nslookup {$client} | grep '=' | cut -d '=' -f 2 | cut -d '.' -f 1") . ' )';
		}
		$clients_IPs = join("<br />", $out_strings);
	}

	if ($status['_alert']) {
		print '<tr>';
		print '<td class="danger"><b>' . gettext("Alert") . ':</b></td>';
		print '<td class="danger"><b>' . gettext("The UPS requires attention") . '</b></td>';
		print '</tr>';
	}

	print_row(gettext("Summary status"), $status['_summary']);
	if (isset($status['ups.alarm'])) {
		print_row(gettext("Alarm"), $status['ups.alarm']);
	}
	if (isset($status['_hms'])) {
		print_row(gettext("Runtime (H:M:S)"), $status['_hms']);
	}
	if (isset($status['ups.load'])) {
		print_row_pct(gettext("UPS load"), $status['ups.load']);
	}
	if (isset($status['battery.charge'])) {
		print_row_pct(gettext("Battery charge"), $status['battery.charge']);
	}
	if ($clients_IPs !== '') {
		print_row(gettext("Remote clients"), $clients_IPs, true);
	}
}
?>


<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">
		<tbody id="nuttable">
			<?php print_table(); ?>
		</tbody>
	</table>
</div>


<script type="text/javascript">
//<![CDATA[
    function update_nut() {
        var ajaxRequest;
    
        ajaxRequest = $.ajax({
                url: "/widgets/widgets/nut_status.widget.php",
                type: "post",
                data: { ajax: "ajax"}
            });

        // Deal with the results of the above ajax call
        ajaxRequest.done(function (response, textStatus, jqXHR) {
            $('#nuttable').html(response);
            // and do it again
            setTimeout(update_nut, 10000);
        });
    }

    events.push(function(){
        update_nut();
    });
//]]>
</script>
