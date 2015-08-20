<?php 
/* $Id$ */
/*

	shellcmd_edit.php
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


$id = $_GET['id'];
if (strlen($_POST['id'])>0) {
	$id = $_POST['id'];
}

$type = $_GET['t'];
if (strlen($_POST['t'])>0) {
	$type = $_POST['t'];
}

if ($_GET['act'] == "del") {
	if ($_GET['type'] == 'cmd') {

		switch (htmlspecialchars($type)) {
		case "earlyshellcmd":
			$a_earlyshellcmd = &$config['system']['earlyshellcmd'];
			unset($a_earlyshellcmd[$_GET['id']]);
			write_config();
			shellcmd_sync_package();
			header("Location: shellcmd.php");
			exit;
			break;
		case "shellcmd":
			$a_shellcmd = &$config['system']['shellcmd'];
			unset($a_shellcmd[$_GET['id']]);
			write_config();
			shellcmd_sync_package();
			header("Location: shellcmd.php");
			exit;
			break;
		case "afterfilterchangeshellcmd":
			//  $a_afterfilterchangeshellcmd = &$config['system']['afterfilterchangeshellcmd'];
			//	unset($a_afterfilterchangeshellcmd[$_GET['id']]);
			//	write_config();
			//	shellcmd_sync_package();
			//	header("Location: shellcmd.php");
			//	exit;
			break;					
		default:
			break;	
		}

	}
}

//get value for the form edit value
if (strlen($id) > 0) {

	switch (htmlspecialchars($type)) {
	case "earlyshellcmd":
		$a_earlyshellcmd = &$config['system']['earlyshellcmd'];
		if ($a_earlyshellcmd[$id]) {
			$pconfig['command'] = $a_earlyshellcmd[$id];
		}
		break;
	case "shellcmd":
		$a_shellcmd = &$config['system']['shellcmd'];
		if ($a_shellcmd[$id]) {
			$pconfig['command'] = $a_shellcmd[$id];
		}
		break;
	case "afterfilterchangeshellcmd":
		//$a_afterfilterchangeshellcmd = &$config['system']['afterfilterchangeshellcmd'];
		//if ($a_afterfilterchangeshellcmd[$id]) {
		//	$pconfig['command'] = $a_afterfilterchangeshellcmd[$id];
		//}
		break;					
	default:
		break;	
	}	
	
	// previous version of shellcmd wrapped all commands in a <command>-xmltag, unnesting this for backwards compatibility
	if (is_array($pconfig['command'])) $pconfig['command'] = $pconfig['command']['command'];

}

if ($_POST) {

	unset($input_errors);
  
	if (!$input_errors) {
		if (strlen($_POST['command']) > 0) {
			
			$ent = $_POST['command'];

			if (strlen($id)>0) {
				//update
				
				switch (htmlspecialchars($type)) {
				case "earlyshellcmd":
					$a_earlyshellcmd = &$config['system']['earlyshellcmd'];
					if ($a_earlyshellcmd[$id]) {
						$a_earlyshellcmd[$id] = $ent;
					}
					break;
				case "shellcmd":
					$a_shellcmd = &$config['system']['shellcmd'];
					if ($a_shellcmd[$id]) {
						$a_shellcmd[$id] = $ent;
					}
					break;
				case "afterfilterchangeshellcmd":
					//$a_afterfilterchangeshellcmd = &$config['system']['afterfilterchangeshellcmd'];
					//if ($a_afterfilterchangeshellcmd[$id]) {
					//	$a_afterfilterchangeshellcmd[$id] = $ent;
					//}
					break;					
				default:
					break;	
				}
					
			}			
			else {
				//add			
				switch (htmlspecialchars($type)) {
				case "earlyshellcmd":
					$a_earlyshellcmd = &$config['system']['earlyshellcmd'];
					$a_earlyshellcmd[] = $ent;
					break;
				case "shellcmd":
					$a_shellcmd = &$config['system']['shellcmd'];
					$a_shellcmd[] = $ent;
					break;
				case "afterfilterchangeshellcmd":
					//$a_afterfilterchangeshellcmd = &$config['system']['afterfilterchangeshellcmd'];
					//$a_afterfilterchangeshellcmd[] = $ent;
					break;					
				default:
					break;	
				}

			}

			write_config();
			shellcmd_sync_package();
		}

		header("Location: shellcmd.php");
		exit;
	}
}

include("head.inc");

?>

<script type="text/javascript" language="JavaScript">

function show_advanced_config() {
	document.getElementById("showadvancedbox").innerHTML='';
	aodiv = document.getElementById('showadvanced');
	aodiv.style.display = "block";
</script>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Shellcmd: Edit</p>
<?php if ($input_errors) print_input_errors($input_errors); ?>

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

	<!--
  	<table width="100%" border="0" cellpadding="6" cellspacing="0">              
      <tr>
        <td><p><span class="vexpl"><span class="red"><strong>shellcmd<br>
            </strong></span>
            </p></td>
      </tr>
    </table>
    -->
    <br />


        <form action="shellcmd_edit.php" method="post" name="iform" id="iform">
            <table width="100%" border="0" cellpadding="6" cellspacing="0">		         
            <tr>
              <td width="25%" valign="top" class="vncellreq">Command</td>
              <td width="75%" class="vtable">
                <input name="command" type="text" class="formfld" id="command" size="40" value="<?=htmlspecialchars($pconfig['command']);?>">
              </td>
            </tr>

			<tr>
              <td width="25%" valign="top" class="vncellreq">Type</td>
              <td width="75%" class="vtable">
				<?php                
				echo "              <select name='t' class='formfld'>\n";
				echo "                <option></option>\n";
				switch (htmlspecialchars($type)) {
				case "earlyshellcmd":
					echo "              <option value='earlyshellcmd' selected='yes'>earlyshellcmd</option>\n";
					echo "              <option value='shellcmd'>shellcmd</option>\n";
					//echo "              <option value='afterfilterchangeshellcmd'>afterfilterchangeshellcmd</option>\n";
					break;
				case "shellcmd":
					echo "              <option value='earlyshellcmd'>earlyshellcmd</option>\n";
					echo "              <option value='shellcmd' selected='yes'>shellcmd</option>\n";
					//echo "              <option value='afterfilterchangeshellcmd'>afterfilterchangeshellcmd</option>\n";
					break;
				case "afterfilterchangeshellcmd":
					//echo "              <option value='earlyshellcmd'>earlyshellcmd</option>\n";
					//echo "              <option value='shellcmd'>shellcmd</option>\n";
					//echo "              <option value='afterfilterchangeshellcmd' selected='yes'>afterfilterchangeshellcmd</option>\n";
					break;					
				default:
					echo "              <option value=''></option>\n";				
					echo "              <option value='earlyshellcmd'>earlyshellcmd</option>\n";
					echo "              <option value='shellcmd'>shellcmd</option>\n";
					//echo "              <option value='afterfilterchangeshellcmd'>afterfilterchangeshellcmd</option>\n";
					break;	
				}
				echo "              </select>\n";
				?>
              </td>
            </tr>
			

            <!--
            <tr>
              <td width="25%" valign="top" class="vncellreq">Description</td>
              <td width="75%" class="vtable"> 
                <input name="description" type="text" class="formfld" id="description" size="40" value="<?=htmlspecialchars($pconfig['description']);?>">
                <br><span class="vexpl">Enter the description here.<br></span>
              </td>
            </tr>
            -->
            
            <tr>
              <td valign="top">&nbsp;</td>
              <td>
                <?php if (strlen($id)>0) { ?>
                  <input name="id" type="hidden" value="<?=$id;?>">
                <?php }; ?>			  
                <input name="Submit" type="submit" class="formbtn" value="Save"> <input class="formbtn" type="button" value="Cancel" onclick="history.back()">
              </td>
            </tr>
            </table>
        </form>

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
