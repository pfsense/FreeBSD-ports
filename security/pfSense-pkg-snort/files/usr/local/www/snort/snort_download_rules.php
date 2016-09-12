<?php
/*
 * snort_download_rules.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2009 Robert Zelaya
 * Copyright (c) 2015 Bill Meeks
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

require("guiconfig.inc");
require_once("functions.inc");
require_once("/usr/local/pkg/snort/snort.inc");

global $g;

$pgtitle = array(gettext("Services: Snort"), gettext("Update Rules"));
include_once("head.inc");
if ($input_errors)
	print_input_errors($input_errors);

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

?>

<form action="/snort/snort_download_updates.php" method="post" class="form-horizontal">
<div class="panel panel-default">
	<div class="panel-body">
		<br />
			<div class="progress">
				<div id="progressbar" class="progress-bar progress-bar-striped" role="progressbar" aria-valuemin="0" aria-valuemax="100" style="width: 1%"></div>
			</div>
			<div class="panel-heading">
				<div id="status"></div>
			</div>
			<div class="content">
				<textarea rows="15" class="form-control" id="output"></textarea>
			</div>
	</div>
	<div class="panel-footer">
		<input type="submit" class="btn btn-info" name="btnReturn" id="btnReturn" value="Return"/>
	</div>
</div>
</form>

<?php

$snort_gui_include = true;
include("/usr/local/pkg/snort/snort_check_for_rule_updates.php");

/* hide progress bar and lets end this party */
echo "\n<script type=\"text/javascript\">document.getElementById('progressbar').style.visibility='hidden';\n</script>";
?>
<?php include("foot.inc"); ?>

