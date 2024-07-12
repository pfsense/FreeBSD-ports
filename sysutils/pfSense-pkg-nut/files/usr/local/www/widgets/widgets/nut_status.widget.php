<?php
/*
 * nut_status.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2016 Denny Page
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */


require_once("/usr/local/www/widgets/include/nut_status.inc");
require_once("/usr/local/pkg/nut/nut.inc");


if ($_REQUEST && $_REQUEST['ajax']) {
	print_table();
	exit;
}


function print_row($desc, $value) {
	print '<tr>';
	print '  <td style="width:35%"><b>' . $desc . ':</b></td>';
	print '  <td>' . htmlspecialchars($value) . '</td>';
	print '</tr>';
}

function print_row_pct($desc, $value) {
	print '<tr>';
	print '  <td style="width:35%"><b>' . $desc . ':</b></td>';
	print '  <td>';
	print '    <div class="progress" style="max-width:300px">';
	print '      <div class="progress-bar progress-bar-striped" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . $value . '" style="width: ' . $value . '%">';
	print '      </div>';
	print '    </div>';
	print      htmlspecialchars($value) . '%';
	print '  </td>';
	print '</tr>';
}

function print_table() {
	$status = nut_ups_status();

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
