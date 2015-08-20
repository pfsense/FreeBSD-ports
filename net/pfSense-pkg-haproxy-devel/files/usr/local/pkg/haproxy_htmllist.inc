<?php
/*
	haproxy_htmllist.php
	part of pfSense (https://www.pfsense.org/)
	Copyright (C) 2013 PiBa-NL
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
	AUTHOR BE LIABLE FOR ANY DIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
/*
  This file contains functions which are NOT specific to HAProxy and may/could/should 
  be moved to the general pfSense php library for possible easy use by other parts of pfSense
*/

require_once("config.inc");
require_once("haproxy_utils.inc");

class HaproxyHtmlList
{
	/*
		javascript 'events':
			<tableId>_row_added(tableId, rowNr)
			<tableId>_field_changed(tableId, rowNr, fieldId, field)
	*/

	private $tablename = "";
	private $fields = array();
	public $editmode = false;
	public $fields_details = null;
	public $keyfield = "";
	
	public function HaproxyHtmlList($tablename, $fields){
		$this->tablename = $tablename;
		$this->fields = $fields;
	}
	
	public function Draw($data){
		$this->haproxy_htmllist($data, $this->fields, $this->editmode, $this->fields_details);
	}
	
	function haproxy_htmllist_get_values(){
		$values = array();
		for($x=0; $x<99; $x++) {
			$value = array();
			$add_item = false;
			foreach($this->fields as $item){
				$itemname = $item['name'];
				$value[$itemname] = $_POST[$itemname.$x];
				if ($item['type'] == 'textarea')
					$value[$itemname] = base64_encode($value[$itemname]);
				$add_item |= isset($_POST[$itemname.$x]);
			}
			if ($add_item) {
				if ($this->keyfield != "") {
					if (isset($_POST[$this->tablename."_key".$x]))
						$key = $_POST[$this->tablename."_key".$x];
					else
						$key = $_POST[$this->keyfield.$x];
						
				} else 
					$key = "";
					
				if (isset($values[$key]))
					$values[] = $value;
				else
					$values[$key] = $value;
			}
		}
		return $values;
	}

	private function haproxy_htmllist_drawcell($item, $itemvalue, $editable, $itemname, $counter) {
		$itemnamenr = $itemname . $counter;
		$itemtype = $item['type'];
		if ($editable) {
			$itemtype = $item['type'];
			if ($itemtype == "select"){
				echo_html_select($itemnamenr, $item['items'], $itemvalue,"","html_listitem_change(\"{$this->tablename}\",\"{$itemname}\",\"{$counter}\",this);", "width:{$item['size']}");
			} else
			if ($itemtype == "checkbox"){
				$checked = $itemvalue=='yes' ? " checked" : "";
				echo "<input onclick='html_listitem_change(\"{$this->tablename}\",\"{$itemname}\",\"{$counter}\",this);' name='$itemnamenr' id='$itemnamenr' type='checkbox'$checked value='yes' size='{$item['size']}' />";
			}  else
			if ($itemtype == "textarea"){
				echo "<textarea name='$itemnamenr' id='$itemnamenr' type='text' cols='{$item['size']}' rows='10'>";
				echo htmlspecialchars(base64_decode($itemvalue));
				echo "</textarea>";
			}  else
				echo "<input name='$itemnamenr' id='$itemnamenr' type='text' value='{$itemvalue}' size='{$item['size']}' />";
		} else {
			if ($itemtype == "select"){
				echo $item['items'][$itemvalue]['name'];
			} else
			if ($itemtype == "checkbox"){
				echo $itemvalue=='yes' ? gettext('yes') : gettext('no');
			} else
			if ($itemtype == "textarea"){
				echo htmlspecialchars(base64_decode($itemvalue));
			} else
				echo htmlspecialchars($itemvalue);
		}
	}

	function haproxy_htmllist($rowvalues,$items,$editstate=false,$itemdetails=null){
		$tablename = $this->tablename;
		global $g, $counter;
		echo "<table class='' width='100%' cellpadding='0' cellspacing='0' id='$tablename'>
		<tr>";
		foreach($items as $item){
			echo "<td width='{$item['colwidth']}' class='listhdrr'>{$item['columnheader']}</td>";
		}
		echo "<td width='5%' class=''></td>
		</tr>";
		if (is_array($rowvalues)){
			foreach($rowvalues as $keyid => $value){
				if ($this->keyfield != "") {
					if (preg_match("/[^0-9]/", $keyid))
						$itemvalue = $keyid;
					else
						$itemvalue = $value[$this->keyfield];
					$key = "<input name='{$tablename}_key{$counter}' id='{$tablename}_key{$counter}' type='hidden' value='{$itemvalue}'>";
				} else
					$key = "";
				
				if (!$editstate) {
					echo "<tr id='tr_view_$counter' ondblclick='editRow($counter); return false;' >";
					$leftitem = true;
					foreach($items as $item) {
						$tdclass = $leftitem ? "vtable listlr" : "vtable listr";
						echo "<td class='$tdclass'>";
						$itemname = $item['name'];
						$itemvalue = $value[$itemname];
						if (isset($item['customdrawcell'])) {
							$item['customdrawcell']($item, $itemvalue, false);
						} else
							$this->haproxy_htmllist_drawcell($item, $itemvalue, false, $itemname, $counter);
						echo "</td>";
						$leftitem = false;
						
					}
					echo "
			<td class='list'>
				<table border='0' cellspacing='0' cellpadding='1'><tr>
				<td valign='middle'>
				<img src='/themes/{$g['theme']}/images/icons/icon_e.gif' title='edit entry' width='17' height='17' border='0' onclick='editRow($counter); return false;' />
				</td>
				<td valign='middle'>
				<img src='/themes/{$g['theme']}/images/icons/icon_x.gif' title='delete entry' width='17' height='17' border='0' onclick='deleteRow($counter, \"$tablename\"); return false;' />
				</td>
				<td valign='middle'>
				<img src='/themes/{$g['theme']}/images/icons/icon_plus.gif' title='duplicate entry' width='17' height='17' border='0' onclick='dupRow($counter, \"$tablename\"); return false;' />
				</td></tr></table>
			</td>";
					echo "</tr>";
				}
				$displaystyle = $editstate ? "" : "display: none;";
				echo "<tr id='tr_edit_$counter' style='$displaystyle'>";
				foreach($items as $item){
					$itemname = $item['name'];
					$itemvalue = $value[$itemname];					
					echo "<td class='vtable'>".$key;
					if (isset($item['customdrawcell'])) {
						$item['customdrawcell']($item, $itemvalue, true, $item['name'].$counter);
					} else
						$this->haproxy_htmllist_drawcell($item, $itemvalue, true, $itemname, $counter);
					echo "</td>";
					$key = "";
				}
				echo "
		<td class='list'>
			<table border='0' cellspacing='0' cellpadding='1'><tr>
			<td valign='middle'>
			<img src='/themes/{$g['theme']}/images/icons/icon_x.gif' title='delete entry' width='17' height='17' border='0' onclick='removeRow(this); return false;' />
			</td>
			<td valign='middle'>
			<img src='/themes/{$g['theme']}/images/icons/icon_plus.gif' title='duplicate entry' width='17' height='17' border='0' onclick='dupRow($counter, \"$tablename\"); return false;' />
			</td></tr></table>
		</td>";
				echo "</tr>";
					if (isset($itemdetails)) {
						$colspan = count($items)-1;
						echo "<tr id='tr_viewdetail_$counter'>";
						?>
							<td class='vtable listlr' style='border-bottom-width: medium;vertical-align:top;'>
							<div style="position:relative;float:right;width:11px;height:11px;">
							<a onclick="htmltable_toggle_details('<?="htmltable_{$tablename}_{$counter}_details"?>')">
								<img id="htmltable_<?="{$tablename}_{$counter}"?>_details_off" alt="Expand advanced server settings" 
								src="tree/plus.gif" style="clip:rect(19px 13px 30px 2px); top:-19px;position:absolute;"/>
							</a>
							</div>
							</td>
						<?
						echo "<td class='vtable listr' colspan='$colspan' style='border-bottom-width: medium;'>";
						$itemnr = 0;
						echo "<div id='htmltable_{$tablename}_{$counter}_details_view'>";
						$itemcount = count($itemdetails);
						foreach($itemdetails as $item) {
							echo "<div style='float: left;padding-right: 2px;'>";
							$tdclass = "";//$leftitem ? "vtable listlr" : "vtable listr";
							echo $item['columnheader'] . ": ";
							$itemname = $item['name'];
							$itemvalue = $value[$itemname];
							if (isset($item['customdrawcell'])) {
								$item['customdrawcell']($item, $itemvalue, false);
							} else
								$this->haproxy_htmllist_drawcell($item, $itemvalue, false, $itemname, $counter);
							$leftitem = false;
							$itemnr++;
							if ($itemcount != $itemnr)
								echo ", ";
							echo "</div>";
						}
						echo "</div>";
						echo "<div id='htmltable_{$tablename}_{$counter}_details_edit' style='display:none;'>";
						echo "<table class='tabcont' style='border-collapse:collapse' border='1' cellspacing='0' >";
						$leftitem = true;
						foreach($itemdetails as $item) {
						echo "<tr id='tr_edititemdetails_$counter' ondblclick='editRow($counter); return false;'>";
							$tdclass = "";//$leftitem ? "vtable listlr" : "vtable listr";
							echo "<td style='border-right:0' class='$tdclass'>";
							echo "{$item['columnheader']}: ";
							echo "</td>";
							echo "<td style='border-left:0' class='$tdclass'>";
							$itemname = $item['name'];
							$itemvalue = $value[$itemname];
							echo "{$item['description']}<br/>";
							if (isset($item['customdrawcell'])) {
								$item['customdrawcell']($item, $itemvalue, true, $itemname . $counter);
							} else
								$this->haproxy_htmllist_drawcell($item, $itemvalue, true, $itemname, $counter);
							echo "</td>";
							$leftitem = false;
						echo "</tr>";
						}
						echo "</table>";
						echo "</div>";
						echo "</td>";
						echo "</tr>";
					}
					if (isset($itemdetails)) {
						$colspan = count($items)-1;
						echo "<tr id='htmltable_{$tablename}_{$counter}_details' style='$displaystyle' >";
						echo "<td class='vtable listlr' style='border-bottom-width: medium;'> </td>";
						echo "<td class='vtable listr' colspan='$colspan' style='border-bottom-width: medium;'>";
						echo "</td>";
						echo "</tr>";
					}
				
				$counter++;
			}
		}
		echo "</table>
			<a onclick='javascript:addRowTo(\"$tablename\"); return false;' href='#'>
			<img border='0' src='/themes/{$g['theme']}/images/icons/icon_plus.gif' alt='' title='add another entry' />
			</a>";
	}
}

function haproxy_htmllist($tablename,$rowvalues,$items,$editstate=false,$itemdetails=null){
	$list = new HaproxyHtmlList($tablename, $items);
	$list->haproxy_htmllist($rowvalues, $items, $editstate, $itemdetails);
}

function haproxy_htmllist_get_values($html_list){
	$list = new HaproxyHtmlList("-", $html_list);
	return $list->haproxy_htmllist_get_values();
}

function haproxy_htmllist_js(){
?><script type="text/javascript">
	function html_listitem_change(tableId, fieldId, rowNr, field) {
		javascript_event = tableId + "_listitem_change";
		var fn = window[javascript_event];
		if (typeof fn === 'function'){
			fn(tableId, fieldId, rowNr, field);
		}
	}
	
	function htmllist_get_select_items(prefix,tableId) {
		var items;
		var i = tableId.lastIndexOf('_');
		var items_name = prefix+"_"+tableId.substr(i+1);
		items = eval("typeof "+items_name+" !== 'undefined' ? "+items_name+" : {}");
		return items;
	}

	var addRowTo = (function() {
	return (function (tableId) {
		var d, tbody, tr, td, bgc, i, ii, j, type, seltext, items;
		var btable, btbody, btr, btd;
		d = document;
		
		items = htmllist_get_select_items('fields',tableId);

		tbody = d.getElementById(tableId).getElementsByTagName("tbody").item(0);
		tr = d.createElement("tr");
		totalrows++;
		tr.setAttribute("id","aclrow" + totalrows);
		
		for (var i in items) {
			td = d.createElement("td");
			if(items[i]['type'] == 'textbox') {
				td.innerHTML="<input size='" + items[i]['size'] + "' name='" + items[i]['name'] + totalrows +
				"' id='" + items[i]['name'] + totalrows +
				"'><\/input> ";
			} else if(items[i]['type'] == 'textarea') {
				td.innerHTML="<textarea cols='" + items[i]['size'] + "' rows='30' name='" + items[i]['name'] + totalrows +
				"' id='" + items[i]['name'] + totalrows +
				"'><\/textarea> ";
			} else if(items[i]['type'] == 'select') {
				seltext = htmllist_get_select_options(tableId, items[i]['name']);
				td.innerHTML="<select style='width:" + items[i]['size'] + "'  name='" + items[i]['name'] + totalrows + 
				"' id='" + items[i]['name'] + totalrows + "' "+
				"onchange='html_listitem_change(\""+tableId+"\",\""+items[i]['name']+"\",\""+totalrows+"\",this);' " +
				">" + seltext + "<\/select> ";
			} else {
				td.innerHTML="<input type='checkbox' name='" + items[i]['name'] + totalrows +"'"+
				"id='" + items[i]['name'] + totalrows + "' "+
				"onclick='html_listitem_change(\""+tableId+"\",\""+items[i]['name']+"\",\""+totalrows+"\",this);' " +
				"value='yes'><\/input> ";
			}
			tr.appendChild(td);
		}
		td = d.createElement("td");
		td.rowSpan = "1";
		td.setAttribute("class","list");

		items = htmllist_get_select_items('fields_details',tableId);
		for (var i in items) {
			td.innerHTML=td.innerHTML+"<input type='hidden' name='" + items[i]['name'] + totalrows +
				"' id='" + items[i]['name'] + totalrows +
				"'><\/input> ";
		}
		
		// Recreate the button table.
		btable = document.createElement("table");
		btable.setAttribute("border", "0");
		btable.setAttribute("cellspacing", "0");
		btable.setAttribute("cellpadding", "1");
		btbody = document.createElement("tbody");
		btr = document.createElement("tr");
		btd = document.createElement("td");
		btd.setAttribute("valign", "middle");
		btd.innerHTML = '<img src="/themes/' + theme + '/images/icons/icon_x.gif" title="delete entry" width="17" height="17" border="0" onclick="removeRow(this); return false;" />';
		btr.appendChild(btd);
		btd = document.createElement("td");
		btd.setAttribute("valign", "middle");
		btd.innerHTML = '<img src="/themes/' + theme + "/images/icons/icon_plus.gif\" title=\"duplicate entry\" width=\"17\" height=\"17\" border=\"0\" onclick=\"dupRow(" + totalrows + ", '" + tableId + "'); return false;\" />";
		btr.appendChild(btd);
		btbody.appendChild(btr);
		btable.appendChild(btbody);
		td.appendChild(btable);
		tr.appendChild(td);
		tbody.appendChild(tr);

		javascript_row_added = tableId + "_row_added";
		var fn = window[javascript_row_added];
		if (typeof fn === 'function'){
			fn(tableId, totalrows);
		}
	});
	})();
	function dupRow(rowId, tableId) {
		var dupEl;
		var newEl;
		addRowTo(tableId);
		items = htmllist_get_select_items('fields',tableId);
		for (var i in items) {
			dupEl = document.getElementById(items[i]['name'] + rowId);
			newEl = document.getElementById(items[i]['name'] + totalrows);
			if (dupEl && newEl)
				if(items[i]['type'] == 'checkbox')
					newEl.checked = dupEl.checked;
				else
					newEl.value = dupEl.value;
		}
		items = htmllist_get_select_items('fields_details',tableId);
		for (var i in items) {
			dupEl = document.getElementById(items[i]['name'] + rowId);
			newEl = document.getElementById(items[i]['name'] + totalrows);
			if (dupEl && newEl)
				if(items[i]['type'] == 'checkbox')
					newEl.value = dupEl.checked ? 'yes' : '';
				else
					newEl.value = dupEl.value;
		}
		javascript_row_added = tableId + "_row_added";
		var fn = window[javascript_row_added];
		if (typeof fn === 'function'){
			fn(tableId, totalrows);
		}
	}
	function editRow(num) {
		var trview = document.getElementById('tr_view_' + num);
		var tredit = document.getElementById('tr_edit_' + num);
		trview.style.display='none';
		tredit.style.display='';
	}
	function deleteRow(rowId, tableId) {
		var view = document.getElementById("tr_view_" + rowId);
		var viewdetail = document.getElementById("tr_viewdetail_" + rowId);
		var edit = document.getElementById("tr_edit_" + rowId);
		if (view)
			view.parentNode.removeChild(view);
		if (viewdetail)
			viewdetail.parentNode.removeChild(viewdetail);
		if (edit)
			edit.parentNode.removeChild(edit);
	}
	function removeRow(el) {
		var cel;
		// Break out of one table first
		while (el && el.nodeName.toLowerCase() != "table")
			el = el.parentNode;
		while (el && el.nodeName.toLowerCase() != "tr")
			el = el.parentNode;

		if (el && el.parentNode) {
			cel = el.getElementsByTagName("td").item(0);
			el.parentNode.removeChild(el);
		}
	}
	
	function htmltable_toggle_details(table_row_detail_id) {
		tredit = document.getElementById(table_row_detail_id+'_off');
		trviewdetail = document.getElementById(table_row_detail_id+'_edit');
		treditdetail = document.getElementById(table_row_detail_id+'_view');
		current_on = tredit.style.display == 'none';
		tredit.style.display=current_on ? '' : 'none';
		trviewdetail.style.display=current_on ? 'none' : '';
		treditdetail.style.display=current_on ? '' : 'none';
	}
</script><?
}

?>
