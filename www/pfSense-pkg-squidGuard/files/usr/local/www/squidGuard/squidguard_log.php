<?php
/* $Id$ */
/*
	squidguard_log.php
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

$pgtitle = "Proxy filter SquidGuard: Log page";

require_once('guiconfig.inc');
require_once('notices.inc');
if (file_exists("/usr/local/pkg/squidguard.inc")) {
   require_once("/usr/local/pkg/squidguard.inc");
}

# ------------------------------------------------------------------------------
# defines
# ------------------------------------------------------------------------------
$selfpath = "/squidGuard/squidguard_log.php";

# ------------------------------------------------------------------------------
# Requests
# ------------------------------------------------------------------------------
if ($_REQUEST['getactivity'])
{
    header("Content-type: text/javascript");
    echo squidguard_log_AJAX_response( $_REQUEST );
    exit;
}

# ------------------------------------------------------------------------------
# Functions
# ------------------------------------------------------------------------------

function squidguard_log_AJAX_response( $request )
{
    $res = '';
    $offset   = $request['offset']  ? $request['offset'] : 0;
    $reverse  = $request['reverse'] == 'yes'? true : false;
    $pcaption = '&nbsp;';

    # Actions
    switch($request['rep']) {
        case 'filterconf': 
              if (function_exists("squidguard_conflist")) 
                   $cont = squidguard_conflist( );
              else $cont = "Function 'squidguard_conflist' not found.";
              $res = squidguard_prep_textareacont($cont);
              break;
        case 'proxyconf':              
              if (function_exists("squidguard_squid_conflist")) 
                   $cont = squidguard_squid_conflist( );
              else $cont = "Function 'squidguard_squid_conflist' not found.";
              $res = squidguard_prep_textareacont($cont);
              break;
        case 'guilog':
              $res = squidguard_logrep(squidguard_guidump( $offset, 50, true));
              break;
        case 'filterlog':
              $res = squidguard_logrep(squidguard_filterdump( $offset, 50, true));
              break;
        case "blocked":
        default:
              $res = squidguard_logrep(squidguard_blockdump( $offset, 50, true));
              break;
    }

    $res .= "el('offset').value = {$offset};";
    $res .= "el('showoffset').innerHTML = {$offset};";
    return $res;
}

function squidguard_logrep( &$dump )
{
    $res  = '';

    if (!empty($dump)) {
        if (is_array($dump)) {
            $acount = count($dump[0]) ? count($dump[0]) : 1;
            $res = "<table class=\'tabcont\' width=\'100%\' border=\'0\' cellpadding=\'0\' cellspacing=\'0\'>";
            $res .= "<tr><td class=\'listtopic\' colspan=\'$acount\' nowrap>Show top 50 entries. List from the line:&nbsp;" .
                    "<span style=\'cursor: pointer;\' onclick=\'report_down();\'>&lt;&lt;</span>" .
                    "&nbsp;<span id='showoffset' >0</span>&nbsp;" .
                    "<span style=\'cursor: pointer;\' onclick=\'report_up();\'>&gt;&gt;</span>&nbsp;" .
                    "</td></tr>";

            foreach($dump as $dm) {
                if (!$dm[0] || !$dm[1]) continue;
                # datetime
                $dm[0] = date("d.m.Y H:i:s", strtotime($dm[0]));
                $res  .= "<tr><td class=\'listlr\' nowrap>{$dm[0]}</td>";

                # col 1
                $dm[1] = htmlentities($dm[1]);
                $dm[1] = squidguard_html_autowrap($dm[1]);
                $res  .= "<td class=\'listr\'>{$dm[1]}</td>";

                # for blocked rep
                if (count($dm) > 2) {
                    $dm[2] = htmlentities($dm[2]);
                    $dm[2] = squidguard_html_autowrap($dm[2]);
                    $res .= "<td class=\'listr\' width=\'*\'>{$dm[2]}</td>";
                    $res .= "<td class=\'listr\'>{$dm[3]}</td>";
                }
                $res  .= "</tr>";
            }
            $res .= "</table>";
       }
       else $res = "{$dump}";
    } else {
        $res = "No data.";
    }

    $res  = "el(\"reportarea\").innerHTML = \"{$res}\";";
    return $res;
}

function squidguard_prepfor_JS($cont)
{
    # replace for JS
    $cont = str_replace("\n", "\\n", $cont);
    $cont = str_replace("\r", "\\r", $cont);
    $cont = str_replace("\t", "\\t", $cont);
    $cont = str_replace("\"", "\'",  $cont);
    return $cont;
}

function squidguard_prep_textareacont($cont)
{
    $cont = squidguard_prepfor_JS($cont);
    return
        "el('reportarea').innerHTML = \"<br><center><textarea rows=25 cols=70 id='pconf' name='pconf' wrap='hard' readonly></textarea></center>\";" .
        "el('pconf').innerHTML = '$cont';";
}

function squidguard_html_autowrap($cont)
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

# ------------------------------------------------------------------------------
# HTML Page
# ------------------------------------------------------------------------------

include("head.inc");
echo "\t<script type=\"text/javascript\" src=\"/javascript/scriptaculous/prototype.js\"></script>\n";
?>

<!-- Ajax Script -->
<script type="text/javascript">

function el(id) {
    return document.getElementById(id);
}

function getactivity(action) {
    var url  = "./squidguard_log.php";
    var pars = 'getactivity=yes';
    var act  = action;
    var offset  = 0;
    var reverse = 'yes';

    if (action == 'report_up') {
        act    = el('reptype').value;
        offset = parseInt(el('offset').value);
        offset = offset + 50;
    } else
    if (action == 'report_down') {
        act    = el('reptype').value;
        offset = parseInt(el('offset').value);
        offset = offset - 50;
        offset = offset >= 0 ? offset : 0;
    } else {
        el('reptype').value = action ? action : 'blocklog';
        el('offset').value  = 0;
        offset = 0;
    }   

    pars = pars + '&rep=' + act + '&reverse=' + reverse + '&offset=' + offset;

    var myAjax = new Ajax.Request( url,
        {
            method:    'get',
            parameters: pars,
            onComplete: activitycallback
        });
}

function activitycallback(transport) {

    if (200 == transport.status) {
        result = transport.responseText;
    } else {
        el('reportarea').innerHTML = 'Error! Returned code ' + transport.status + ' ' + transport.responseText;
    }
    sethdtab_selected();
}

function report_up()
{
    getactivity('report_up');
}

function report_down()
{
    getactivity('report_down');
}

function sethdtab_selected()
{
    var sel = "hd_" + el('reptype').value;

    el('hd_blocklog').style.fontWeight   = (sel == 'hd_blocklog')   ? 'bold' : '';
    el('hd_guilog').style.fontWeight     = (sel == 'hd_guilog')     ? 'bold' : '';
    el('hd_filterlog').style.fontWeight  = (sel == 'hd_filterlog')  ? 'bold' : '';
    el('hd_proxyconf').style.fontWeight  = (sel == 'hd_proxyconf')  ? 'bold' : '';
    el('hd_filterconf').style.fontWeight = (sel == 'hd_filterconf') ? 'bold' : '';
}

window.setTimeout('getactivity()', 150);

</script>

<!-- HTML -->
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="sg_log.php" method="post">
<input type="hidden" id="reptype" val="">
<input type="hidden" id="offset"  val="0">
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
        $tab_array[] = array(gettext("Blacklist"),        false, "/squidGuard/squidguard_blacklist.php");
        $tab_array[] = array(gettext("Log"),              true,  "$selfpath");
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

<?php
        # Subtabs
        $mode = $mode ? $mode : "blocked";
        $tab_array = array();
        $tab_array[] = array(gettext("Blocked"),        ($mode == "blocked"), "blocklog");
        $tab_array[] = array(gettext("Filter GUI log"), ($mode == "fgui"),    "guilog");
        $tab_array[] = array(gettext("Filter log"),     ($mode == "flog"),    "filterlog");
        $tab_array[] = array(gettext("Proxy config"),   ($mode == "pconf"),   "proxyconf");
        $tab_array[] = array(gettext("Filter config"),  ($mode == "fconf"),   "filterconf");

        echo "<big>| ";
        foreach ($tab_array as $ta) {
            $id = "hd_{$ta[2]}";
            $bb = $ta[1] ? "font-weight: bold;" : '';
            echo "<span id='{$id}' style='cursor: pointer; {$bb}' onclick=\"getactivity('{$ta[2]}');\">{$ta[0]}</span> | ";
        }
        echo "</big>";
?>
            </td>
          </tr>
          <tr>
            <td id="reportarea" name="reportarea"></td>
          </tr>
        </table>
      </div>
    </td>
  </tr>
</table>
</form>

<?php include("fend.inc"); ?>

<!--script type="text/javascript"> 
  NiftyCheck(); 
  Rounded("div#mainarea","bl br","#FFF","#eeeeee","smooth");
</script-->
</body>
</html>
