<?php
/* $Id$ */
/*
 * snort_download_rules.php
 *
 * Copyright (C) 2006 Scott Ullrich
 * Copyright (C) 2009 Robert Zelaya
 * Copyright (C) 2011-2012 Ermal Luci
 * Copyright (C) 2015 Bill Meeks
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
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

