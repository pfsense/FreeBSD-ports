<?php
/* $Id$ */
/*
	shellcmd.php
	Copyright (C) 2008 Mark J Crane
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

require("guiconfig.inc");
require("/usr/local/pkg/shellcmd.inc");

$a_earlyshellcmd = &$config['system']['earlyshellcmd'];
$a_shellcmd = &$config['system']['shellcmd'];
//$a_afterfilterchangeshellcmd = &$config['system']['afterfilterchangeshellcmd'];

include("head.inc");

?>


<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Shellcmd: Settings</p>

<div id="mainlevel">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td class="tabnavtbl">
<?php
			
	$tab_array = array();
	$tab_array[] = array(gettext("Settings"), false, "/packages/shellcmd/shellcmd.php");
 	display_top_tabs($tab_array);
 	
?>
</td></tr>
</table>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
   <tr>
     <td class="tabcont" >

<form action="shellcmd.php" method="post" name="iform" id="iform">
<?php 

//if ($savemsg) print_info_box($savemsg); 
//if (file_exists($d_hostsdirty_path)): echo"<p>";
//print_info_box_np("This is an info box.");
//echo"<br />";
//endif; 

?>
  	<table width="100%" border="0" cellpadding="6" cellspacing="0">              
      <tr>
        <td><p><!--<span class="vexpl"><span class="red"><strong>shellcmd<br></strong></span>-->
            The shellcmd utility is used to manage commands on system startup. 
            <br /><br />
            <!--For more information see: <a href='http://www.' target='_blank'>http://www.</a>-->
            </p></td>
      </tr>
    </table>
    <br />
    
    <table width="100%" border="0" cellpadding="0" cellspacing="0">
    <tr>
      <td width="50%" class="listhdrr">Command</td>
      <td width="30%" class="listhdrr">Type</td>
      <td width="10%" class="list">

        <table border="0" cellspacing="0" cellpadding="1">
          <tr>
            <td width="17"></td>
            <td valign="middle"><a href="shellcmd_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
          </tr>
        </table>

      </td>
	</tr>


<?php 
    
    $categories = array("earlyshellcmd","shellcmd");
    //$categories = array("earlyshellcmd","shellcmd","afterfilterchangeshellcmd");
	
	foreach ($categories as $category) {
		$i = 0;
		// dynamically create the category config name
		$category_config = "a_".$category;
		if (count($$category_config) > 0) {
			foreach ($$category_config as $ent) { 
				// previous versions of shellcmd stored the command in an additional <command>-xmltag, this unnests this for backwards compatibility
				if (is_array($ent)) { $ent = $ent['command']; }

				echo "  <tr>\n";
				echo "	<td class=\"listr\" ondblclick=\"document.location='shellcmd_edit.php?t=".$category."&id=".$i."';\">\n";
				echo "	  ".$ent."\n";
				echo "	</td>\n";
				echo "	<td class=\"listbg\" ondblclick=\"document.location='shellcmd_edit.php?t=".$category."&id=".$i."';\">\n";
				echo "	  ".$category."\n";
				echo "	</td>\n";
				echo "	<td valign=\"middle\" nowrap class=\"list\">\n";
				echo "	  <table border=\"0\" cellspacing=\"0\" cellpadding=\"1\">\n";
				echo "		<tr>\n";
				echo "		  <td valign=\"middle\"><a href=\"shellcmd_edit.php?t=".$category."&id=".$i."\"><img src=\"/themes/".$g['theme']."/images/icons/icon_e.gif\" width=\"17\" height=\"17\" border=\"0\"></a></td>\n";
				echo "		  <td><a href=\"shellcmd_edit.php?t=".$category."&type=cmd&act=del&id=".$i."\" onclick=\"return confirm('Do you really want to delete this?')\"><img src=\"/themes/".$g['theme']."/images/icons/icon_x.gif\" width=\"17\" height=\"17\" border=\"0\"></a></td>\n";
				echo "		</tr>\n";
				echo "	 </table>\n";
				echo "	</td>\n";
				echo "  </tr>";
				$i++; 
			}
		}
	}


?>

    <tr>
      <td class="list" colspan="2"></td>
      <td class="list">          
        <table border="0" cellspacing="0" cellpadding="1">
          <tr>
            <td width="17"></td>
            <td valign="middle"><a href="shellcmd_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
          </tr>
        </table>
		  </td>
     </tr>


     <tr>
       <td class="list" colspan="3"></td>
       <td class="list"></td>
     </tr>
     </table>
     
</form>


<br>
<br>
<br>
<br>
<br>
<br>
<br>
<br>

</td>
</tr>
</table>

</div>


<?php include("fend.inc"); ?>
</body>
</html>
