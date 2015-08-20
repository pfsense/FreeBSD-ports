<?php
/* $Id$ */
/*
	squidguard_blacklist.php
       2006-2011 Serg Dvoriancev

       part of pfSense (www.pfSense.com)

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

require_once("guiconfig.inc");
require_once("notices.inc");
if (file_exists("/usr/local/pkg/squidguard.inc")) {
   require_once("/usr/local/pkg/squidguard.inc");
}

# ------------------------------------------------------------------------------
# defines
# ------------------------------------------------------------------------------
define("SGCURL_STATUS",  "/tmp/squidguard_download.log");
define("SGUPD_STATFILE", "/tmp/squidguard_download.stat");
define("SGBAR_SIZE",     "450");
define("DEBUG_AJAX",     "false");
# ------------------------------------------------------------------------------
# Requests
# ------------------------------------------------------------------------------
if ($_REQUEST['getactivity'])
{
    header("Content-type: text/javascript");
    echo squidguard_blacklist_AJAX_response( $_REQUEST );
    exit;
}

# ------------------------------------------------------------------------------
# Functions
# ------------------------------------------------------------------------------

function squidguard_blacklist_AJAX_response( $request )
{
    $res = '';
    $sz  = 0;
    $pcaption = '&nbsp;';

    # Actions
    if     ($request['blacklist_download_start'])  squidguard_blacklist_update_start( $request['blacklist_url'] ); # update start
    elseif ($request['blacklist_download_cancel']) squidguard_blacklist_update_cancel();                           # update cancel
    elseif ($request['blacklist_restore_default']) squidguard_blacklist_restore_arcdb();                           # restore default db
    elseif ($request['blacklist_clear_log'])       squidguard_blacklist_update_clearlog();                         # clear log
 
    # Activity
    # Rebuild progress /check SG rebuild process/
    if (is_squidGuardProcess_rebuild_started()) {
        $pcaption = 'Blacklist DB rebuild progress';
        $sz = squidguar_blacklist_rebuild_progress();
    }
    elseif (squidguard_blacklist_update_IsStarted()) {
        $pcaption = 'Blacklist download progress';
        $sz = squidguard_blacklist_update_progress();
    }

    # progress status
    $szleft  = $sz * SGBAR_SIZE / 100;
    $szright = SGBAR_SIZE - $szleft;

    if ($sz < 0) {
        # nothing to show
        $sz = 0;
        $pcaption = '';
    }
    $res .= "el('progress_caption').innerHTML = '{$pcaption}';";
    $res .= "el('widtha').width = {$szleft};";
    $res .= "el('widthb').width = {$szright};";
    $res .= "el('progress_text').innerHTML = '{$sz} %';";

    $status = '';
    if (file_exists(SGUPD_STATFILE)) {
        $status = file_get_contents(SGUPD_STATFILE);
        if ($sz && $sz != 100) $status .= "Completed {$sz} %";
    }
    if ($status) {
        $status = str_replace("\n", "\\r\\n", trim($status));
        $res .= "el('update_state').innerHTML = '{$status}';";
        $res .= "el('update_state_cls').style.display='';";
        $res .= "el('update_state_row').style.display='';";
    } else {
        $res .= "el('update_state').innerHTML = '';";
        $res .= "el('update_state_cls').style.display='none';";
        $res .= "el('update_state_row').style.display='none';";
    }

    return $res;
}

function squidguard_blacklist_update_progress()
{
    $p = -1;

    if (file_exists(SGCURL_STATUS)) {
        $cn = file_get_contents(SGCURL_STATUS);
        if ($cn) {
            $cn = explode("\r", $cn);
            $cn = array_pop($cn);
            $cn = explode(" ", trim($cn));
            $p = intval( $cn[0] );
        }
    }

    return $p;
}

function squidguar_blacklist_rebuild_progress()
{
    $arcdb   = "/tmp/squidGuard/arcdb";
    $blfiles = "{$arcdb}/blacklist.files";

    if (file_exists($arcdb) && file_exists($blfiles)) {
        $dirlist = explode("\n", file_get_contents($blfiles));
        for ($i = 0; $i < count($dirlist); $i++) {
             if ( !file_exists("$arcdb/{$dirlist[$i]}/domains.db") &&
                  !file_exists("$arcdb/{$dirlist[$i]}/urls.db") )
             {
                 return intval( $i * 100 / count($dirlist) );
             }
        }
    }

    return 0;
}

function is_squidGuardProcess_rebuild_started()
{
    # memo: 'ps -auxw' used 132 columns; 'ps -auxww' used 264 columns
    # if cmd more then 132 need use 'ww..' key
    return exec("ps -auxwwww | grep 'squidGuard -c .* -C all' | grep -v grep | awk '{print $2}' | wc -l | awk '{ print $1 }'");
}

# ------------------------------------------------------------------------------
# HTML Page
# ------------------------------------------------------------------------------

$pgtitle       = "Proxy filter SquidGuard: Blacklist page";
$selfpath      = "./squidguard_blacklist.php";
$blacklist_url = '';

# get squidGuard config
if (function_exists(sg_init)) {
    sg_init(convert_pfxml_to_sgxml());
    $blacklist_url = $squidguard_config[F_BLACKLISTURL];
}

include("head.inc");

echo "\t<script type=\"text/javascript\" src=\"/javascript/scriptaculous/prototype.js\"></script>\n";

?>

<!-- Ajax Script -->
<script type="text/javascript">

function el(id) {
    return document.getElementById(id);
}

function getactivity(action) {
    var url  = "./squidguard_blacklist.php";
    var pars = 'getactivity=yes';

    if (action == 'download')          pars = pars + '&blacklist_download_start=yes&blacklist_url=' + encodeURIComponent(el('blacklist_url').value);
    if (action == 'cancel')            pars = pars + '&blacklist_download_cancel=yes';
    if (action == 'restore_default')   pars = pars + '&blacklist_restore_default=yes';
    if (action == 'clear_log')         pars = pars + '&blacklist_clear_log=yes';

    var myAjax = new Ajax.Request( url,
        {
            method:    'get',
            parameters: pars,
            onComplete: activitycallback
        });
}

function activitycallback(transport) {

<?php    if (DEBUG_AJAX == "true") echo "el('debug_textarea').innerHTML = transport.responseText;"; ?>

    if (200 == transport.status) {
        result = transport.responseText;
    }

    // refresh 3 sec
    setTimeout('getactivity()', 3100);
    //alert(transport.responseText);
}

window.setTimeout('getactivity()', 150);

</script>

<!-- HTML -->

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<form action="./squidguard_blacklist.php" method="post">

<?php include("fbegin.inc"); ?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
<!-- Tabs -->
  <tr>
    <td>
<?php
        $tab_array = array();
        $tab_array[] = array(gettext("General settings"), false, "/pkg_edit.php?xml=squidguard.xml&amp;id=0");
        $tab_array[] = array(gettext("Common ACL"),       false, "/pkg_edit.php?xml=squidguard_default.xml&amp;id=0");
        $tab_array[] = array(gettext("Groups ACL"),       false, "/pkg.php?xml=squidguard_acl.xml");
        $tab_array[] = array(gettext("Target categories"),false, "/pkg.php?xml=squidguard_dest.xml");
        $tab_array[] = array(gettext("Times"),            false, "/pkg.php?xml=squidguard_time.xml");
        $tab_array[] = array(gettext("Rewrites"),         false, "/pkg.php?xml=squidguard_rewr.xml");
        $tab_array[] = array(gettext("Blacklist"),        true,  "/squidGuard/squidguard_blacklist.php");
        $tab_array[] = array(gettext("Log"),              false, "/squidGuard/squidguard_log.php");
		$tab_array[] = array(gettext("XMLRPC Sync"),      false, "/pkg_edit.php?xml=squidguard_sync.xml&amp;id=0");
        display_top_tabs($tab_array);
?>
    </td>
  </tr>
  <tr>
    <td>
      <div id="mainarea">
        <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
          <tr>
            <td>
              <table class="tabcont" width="100%">
                <tr>
                  <td class="vncell" width="22%">Blacklist Update</td>
                  <td class="vtable">
                    &nbsp;<big id='progress_caption' name='progress_caption'>&nbsp;</big><br>
<?php
                    echo "<nobr>";
                    echo "<img src='/themes/".$g['theme']."/images/misc/bar_left.gif'  height='10' width='5' border='0' align='absmiddle'>";
                    echo "<img src='/themes/".$g['theme']."/images/misc/bar_blue.gif'  height='10' name='widtha' id='widtha' width='" . 0   . "' border='0' align='absmiddle'>";
                    echo "<img src='/themes/".$g['theme']."/images/misc/bar_gray.gif'  height='10' name='widthb' id='widthb' width='" . SGBAR_SIZE . "' border='0' align='absmiddle'>";
                    echo "<img src='/themes/".$g['theme']."/images/misc/bar_right.gif' height='10' width='5' border='0' align='absmiddle'> ";
                    echo "&nbsp;&nbsp;&nbsp;<u id='progress_text' name='progress_text'>0 %</u>";
                    echo "</nobr>";
                    echo "<br><br>";
?>
                    <nobr>
                    <input class="formfld unknown" size="70" id="blacklist_url" name="blacklist_url" value= '<?php echo "$blacklist_url"; ?>' > &nbsp
                    <!--input size='70' id='blacklist_download_start' name='blacklist_download_start' value='Download' type='button' onclick="getactivity('download');">&nbsp
                    <input size='70' id='blacklist_download_cancel' name='blacklist_download_cancel' value='Cancel' type='button' onclick="getactivity('cancel');"-->
                    </nobr><br>
                    <input size='70' id='blacklist_download_start' name='blacklist_download_start' value='Download' type='button' onclick="getactivity('download');">
                    <input size='70' id='blacklist_download_cancel' name='blacklist_download_cancel' value='Cancel' type='button' onclick="getactivity('cancel');">
                    &nbsp;&nbsp;
                    <input size='70' id='blacklist_restore_default' name='blacklist_restore_default' value='Restore default' type='button' onclick="getactivity('restore_default');">
                    <br><br>
                    Enter FTP or HTTP path to the blacklist archive here.
                    <br><br>
                  </td>
                </tr>
                <tr id='update_state_cls' name='update_state_cls' style='display:none;'>
                  <td>&nbsp;</td>
                  <td>
                    <span  style="cursor: pointer;">
                      <img src=<?php echo "'/themes/{$g['theme']}/images/icons/icon_block.gif'" ?> onClick="getactivity('clear_log');" title='Clear Log and Close'>
                    </span>
                    &nbsp; <big><b>Blacklist update Log</b></big>
                  </td>
                </tr>
                <tr id='update_state_row' name='update_state_row' style='display:none;'>
                  <td>&nbsp;</td>
                   <td>
                     <textarea rows='15' cols='55' name='update_state' id='update_state' wrap='hard' readonly>&nbsp;</textarea>
                   </td>
                </tr>
<?php
# debug
if (DEBUG_AJAX !== "false") {
echo <<<EOD
                <tr id='debug_row' name='debug_row'>
                  <td>&nbsp;</td>
                   <td>
                     <textarea rows='15' cols='55' name='debug_textarea' id='debug_textarea' wrap='hard' readonly>&nbsp;</textarea>
                   </td>
                </tr>
EOD;
}
?>
              </table>
            </td>
          </tr>
          <tr>
            <td>
<?php
#blacklist table
#echo squidguard_blacklist_list();
?>
            </td>
          </tr>
        </table>
      </div>
    </td>
  </tr>
</table>

<?php include("fend.inc"); ?>

</form>
</body>
</html>

