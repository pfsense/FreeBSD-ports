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
	
	public function HaproxyHtmlList($tablename, $fields) {
		$this->tablename = $tablename;
		$this->fields = $fields;
	}
	
	public function Draw($data) {
		$this->haproxy_htmllist($data, $this->fields, $this->editmode, $this->fields_details);
	}
	
	public function outputjavascript() {
		$table_def = array();
		$table_def['keyfield'] = $this->keyfield;
		phparray_to_javascriptarray($table_def, "tabledefinition_".$this->tablename,Array('/*','/*/*'));
		phparray_to_javascriptarray($this->fields, "fields_".$this->tablename,Array('/*','/*/name','/*/type','/*/text','/*/size','/*/items','/*/items/*','/*/items/*/*','/*/items/*/*/name'));
		if (count($this->fields_details) != 0) {
			phparray_to_javascriptarray($this->fields_details,"fields_details_".$this->tablename,Array('/*','/*/name','/*/columnheader','/*/description','/*/type','/*/text','/*/size','/*/items','/*/items/*','/*/items/*/*','/*/items/*/*/name','/*/items/*/*/name'));
		}
	}

	// function retrieves all posted values and returns an array
	public function haproxy_htmllist_get_values() {
		$values = array();
		for($x = 0; $x < 99; $x ++) {
			$value = array();
			$add_item = false;
			if (is_array($this->fields_details)) {
				$fields = array_merge($this->fields, $this->fields_details);
			} else {
				$fields = $this->fields;
			}
			foreach($fields as $item) {
				$itemname = $item['name'];
				$value[$itemname] = $_POST[$this->tablename.$itemname.$x];
				if ($item['type'] == 'textarea') {
					$value[$itemname] = base64_encode($value[$itemname]);
				}
				$add_item |= isset($_POST[$this->tablename.$itemname.$x]);
			}
			if ($add_item) {
				if ($this->keyfield != "") {
					if (isset($_POST[$this->tablename."_key".$x])) {
						$key = $_POST[$this->tablename."_key".$x];
					} else {
						$key = $_POST[$this->tablename.$this->keyfield.$x];
					}
				} else {
					$key = "";
				}
				$index = $_POST[$this->tablename."_rowindex".$x];
				$value['_index'] = $index;
				if (isset($values[$key])) {
					$values[] = $value;
				} else {
					$values[$key] = $value;
				}
			}
		}
		usort($values, 'sort_index');
		
		return $values;
	}
	
	function haproxy_htmllist_drawcell($item, $itemvalue, $editable, $itemname, $counter) {
		$itemnamenr = $this->tablename . $itemname . $counter;
		$itemtype = $item['type'];
		if ($editable) {
			$itemtype = $item['type'];
			if ($itemtype == "select") {
				echo_html_select($itemnamenr, $item['items'], $itemvalue,"-none available-","html_listitem_change(\"{$this->tablename}\",\"{$itemname}\",\"{$counter}\",this);", "width:{$item['size']}");
			} elseif ($itemtype == "checkbox") {
				$checked = $itemvalue=='yes' ? " checked" : "";
				echo "<input onclick='html_listitem_change(\"{$this->tablename}\",\"{$itemname}\",\"{$counter}\",this);' name='$itemnamenr' id='$itemnamenr' type='checkbox'$checked value='yes' size='{$item['size']}' />";
			} elseif ($itemtype == "textarea") {
				echo "<textarea name='$itemnamenr' id='$itemnamenr' type='text' cols='{$item['size']}' rows='10'>";
				echo htmlspecialchars(base64_decode($itemvalue));
				echo "</textarea>";
			} elseif ($itemtype ==  "fixedtext") {
				echo $item['text'];
			} else {
				echo "<input name='$itemnamenr' id='$itemnamenr' type='text' value='{$itemvalue}' size='{$item['size']}' />";
			}
		} else {
			if ($itemtype == "select") {
				echo $item['items'][$itemvalue]['name'];
			} elseif ($itemtype == "checkbox") {
				echo $itemvalue=='yes' ? gettext('yes') : gettext('no');
			} elseif ($itemtype == "textarea") {
				echo "<div style='overlow:scroll;max-height:120px;max-width:{$item['colwidth']};overflow-y: scroll;'>";
				echo str_replace(" ","&nbsp;", str_replace("\n","<br/>", htmlspecialchars(base64_decode($itemvalue))));
				echo '</div>';
			} elseif ($itemtype ==  "fixedtext") {
				echo $item['text'];
			} else {
				echo htmlspecialchars($itemvalue);
			}
		}
	}

	function haproxy_htmllist($rowvalues,$items,$editstate=false,$itemdetails=null){
		$tablename = $this->tablename;
		global $g, $counter;
		echo "<table class='' width='100%' cellpadding='0' cellspacing='0' id='{$tablename}'>
		<thead>
		<tr>";
		foreach($items as $item){
			echo "<td width='{$item['colwidth']}' class='listhdrr'>{$item['columnheader']}</td>";
		}
		echo "<td width='5%' class=''></td>
		</tr>
		</thead>
		<tbody>";
		if (is_array($rowvalues)) {
			foreach($rowvalues as $keyid => $value) {
				if (!empty($this->keyfield)) {
					if (preg_match("/[^0-9]/", $keyid)) {
						$itemvalue = $keyid;
					} else {
						$itemvalue = $value[$this->keyfield];
					}
					$key = "<input name='{$tablename}_key{$counter}' id='{$tablename}_key{$counter}' type='hidden' value='{$itemvalue}'>";
				} else {
					$key = "";
				}
				
				if (!$editstate) {
					echo "<tr id='tr_view_$counter' ondblclick='editRow($counter); return false;' >";
					$leftitem = true;
					foreach($items as $item) {
						$tdclass = $leftitem ? "vtable listlr" : "vtable listr";
						echo "<td class='$tdclass'>";
						$itemname = $item['name'];
						$itemvalue = $value[$itemname];
						if (isset($item['customdrawcell'])) {
							$item['customdrawcell']($this, $item, $itemvalue, false, $itemname, $counter);
						} else {
							$this->haproxy_htmllist_drawcell($item, $itemvalue, false, $itemname, $counter);
						}
						echo "</td>";
						$leftitem = false;
						
					}
					echo "
					<td class='list'>
					<table border='0' cellspacing='0' cellpadding='1'><tr>
					<td valign='middle'>
					<img src='/themes/{$g['theme']}/images/icons/icon_e.gif' title='edit entry' width='17' height='17' border='0' onclick='editRow({$counter}); return false;' />
					</td>
					<td valign='middle'>
					<img src='/themes/{$g['theme']}/images/icons/icon_x.gif' title='delete entry' width='17' height='17' border='0' onclick='deleteRow({$counter}, \"{$tablename}\"); return false;' />
					</td>
					<td valign='middle'>
					<img src='/themes/{$g['theme']}/images/icons/icon_plus.gif' title='duplicate entry' width='17' height='17' border='0' onclick='dupRow({$counter}, \"{$tablename}\"); return false;' />
					</td>";
					if (empty($this->noindex)) {
						echo "<td valign='middle'>
						<img src='/themes/{$g['theme']}/images/icons/icon_up.gif' title='move row up' width='17' height='17' border='0' onclick='moveRowUp({$counter}, \"{$tablename}\"); return false;' />
						</td>
						<td valign='middle'>
						<img src='/themes/{$g['theme']}/images/icons/icon_down.gif' title='move row down' width='17' height='17' border='0' onclick='moveRowDown({$counter}, \"{$tablename}\"); return false;' />
						</td>";
					}
					echo "</tr></table>
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
						$item['customdrawcell']($this, $item, $itemvalue, true, $itemname, $counter);
					} else {
						$this->haproxy_htmllist_drawcell($item, $itemvalue, true, $itemname, $counter);
					}
					echo "</td>";
					$key = "";
				}
				echo "
		<td class='list'>
			<table border='0' cellspacing='0' cellpadding='1'><tr>
			<td valign='middle'>
			<input name='{$tablename}_rowindex{$counter}' id='{$tablename}_rowindex{$counter}' type='hidden' value='{$counter}' />
			<img src='/themes/{$g['theme']}/images/icons/icon_x.gif' title='delete entry' width='17' height='17' border='0' onclick='deleteRow({$counter}, \"{$tablename}\"); return false;' />
			</td>
			<td valign='middle'>
			<img src='/themes/{$g['theme']}/images/icons/icon_plus.gif' title='duplicate entry' width='17' height='17' border='0' onclick='dupRow({$counter}, \"{$tablename}\"); return false;' />
			</td>";
			if (empty($this->noindex)) {
				echo "<td valign='middle'>
				<img src='/themes/{$g['theme']}/images/icons/icon_up.gif' title='move row up' width='17' height='17' border='0' onclick='moveRowUp({$counter}, \"{$tablename}\"); return false;' />
				</td>
				<td valign='middle'>
				<img src='/themes/{$g['theme']}/images/icons/icon_down.gif' title='move row down' width='17' height='17' border='0' onclick='moveRowDown({$counter}, \"{$tablename}\"); return false;' />
				</td>";
			}
			echo "</tr></table>
		</td>";
				echo "</tr>";
					if (isset($itemdetails)) {
						$colspan = count($items)-1;
						echo "<tr id='tr_viewdetail_$counter'>";
						?>
							<td class='vtable listlr' style='border-bottom-width: medium;vertical-align:top;'>
							<div style="position:relative;float:right;width:11px;height:11px;">
							<a onclick="htmltable_toggle_details('<?=$tablename?>','<?=$counter?>','<?="htmltable_{$tablename}_{$counter}_details"?>')">
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
						$leftitem = true;
						foreach($itemdetails as $item) {
							$itemname = $item['name'];
							$itemvalue = $value[$itemname];
							//TODO don't filter empty items, filter context un-related items through customizable function..
							if (empty($itemvalue)) {
								continue;
							}
							echo "<div style='float: left;padding-right: 2px;'>";
							$tdclass = "";
							if (!$leftitem) {
								echo ", ";
							}
							$leftitem = false;
							echo $item['columnheader'] . ": ";
							if (isset($item['customdrawcell'])) {
								$item['customdrawcell']($this, $item, $itemvalue, false, $itemname, $counter);
							} else {
								$this->haproxy_htmllist_drawcell($item, $itemvalue, false, $itemname, $counter);
							}
							$itemnr++;
							echo "</div>";
						}
						echo "</div>";
						echo "<div id='htmltable_{$tablename}_{$counter}_details_edit' style='display:none;'>";
						echo "<table class='tabcont' style='border-collapse:collapse' border='1' cellspacing='0' >";
						foreach($itemdetails as $item) {
							$itemname = $item['name'];
							echo "<tr id='tr_edititemdetails_{$counter}_{$itemname}'>";
							echo "<td style='border-right:0'>";
							echo "{$item['columnheader']}: ";
							echo "</td>";
							echo "<td style='border-left:0'>";
							$itemvalue = $value[$itemname];
							
							if (!empty($item['description'])) {
								echo "{$item['description']}<br/>";
							}
							if (isset($item['customdrawcell'])) {
								$item['customdrawcell']($this, $item, $itemvalue, true, $itemname, $counter);
							} else {
								$this->haproxy_htmllist_drawcell($item, $itemvalue, true, $itemname, $counter);
							}
							echo "</td>";
							echo "</tr>";
						}
						echo "</table>";
						echo "</div>";
						echo "</td>";
						echo "</tr>";
					}
				$counter++;
			}
		}
		echo "</tbody>
		</table>
			<a onclick='javascript:addRowTo(\"{$tablename}\"); return false;' href='#'>
			<img border='0' src='/themes/{$g['theme']}/images/icons/icon_plus.gif' alt='' title='add another entry' />
			</a>";
	}
}

function sort_index(&$a, &$b) {
	// sort callback function, cannot be inside the object.
	if ($a['_index'] != $b['_index']) {
		return $a['_index'] > $b['_index'] ? 1 : -1;
	}
	return 0;
}

function haproxy_htmllist($tablename,$rowvalues,$items,$editstate=false,$itemdetails=null){
	$list = new HaproxyHtmlList($tablename, $items);
	$list->haproxy_htmllist($rowvalues, $items, $editstate, $itemdetails);
}

function haproxy_htmllist_get_values($tablename, $html_list){
	$list = new HaproxyHtmlList($tablename, $html_list);
	return $list->haproxy_htmllist_get_values();
}

function haproxy_htmllist_js(){
	global $g;
?><script type="text/javascript">

	var theme = "<?=$g['theme']?>";
	
	function html_listitem_change(tableId, fieldId, rowNr, field) {
		javascript_event = tableId + "_listitem_change";
		var fn = window[javascript_event];
		if (typeof fn === 'function'){
			fn(tableId, fieldId, rowNr, field);
		}
	}
	
	function htmllist_get_select_items(prefix, tableId) {
		var items;
		var items_name = prefix+"_"+tableId;
		items = eval("typeof "+items_name+" !== 'undefined' ? "+items_name+" : null");
		return items;
	}

	function createFieldHtml(tableId, field, rowId) {
		var result = "";
		if(field['type'] == 'textbox') {
			result="<input size='" + field['size'] + "' name='" + tableId + field['name'] + rowId +
			"' id='" + tableId + field['name'] + rowId +
			"'><\/input> ";
		} else if(field['type'] == 'textarea') {
			result="<textarea cols='" + field['size'] + "' rows='30' name='" + tableId + field['name'] + rowId +
			"' id='" + tableId + field['name'] + rowId +
			"'><\/textarea> ";
		} else if(field['type'] == 'select') {
			var seltext = "";
			var fieldid = field['name'];
			var fn = window["htmllist_get_select_options"];
			fielditems = field['items'];
			if (typeof fn === 'function'){
				fielditems = htmllist_get_select_options(tableId, field['name'], fielditems);
			}
			for (var fieldvalueid in fielditems) {
				var fieldvalue = fielditems[fieldvalueid]
				seltext += "<option value='"+fieldvalueid+"'>"+fieldvalue['name']+"<\/option>";
			}
			
			result="<select style='width:" + field['size'] + "'  name='" + tableId + field['name'] + rowId + 
			"' id='" + tableId + field['name'] + rowId + "' "+
			"onchange='html_listitem_change(\""+tableId+"\",\""+field['name']+"\",\""+rowId+"\",this);' " +
			">" + seltext + "<\/select> ";
		} else if(field['type'] == 'fixedtext') {
			result=field['text'];
		} else {
			result="<input type='checkbox' name='" + tableId + field['name'] + rowId +"'"+
			"id='" + tableId + field['name'] + rowId + "' "+
			"onclick='html_listitem_change(\""+tableId+"\",\""+field['name']+"\",\""+rowId+"\",this);' " +
			"value='yes'><\/input> ";
		}
		return result;
	}
	
	var addRowTo = (function() {
	return (function (tableId) {
		var d, tbody, tr, td, bgc, i, ii, j, type, seltext, items;
		var btable, btbody, btr, btd;
		d = document;
		
		tbody = d.getElementById(tableId).getElementsByTagName("tbody").item(0);
		totalrows++;

		// create edit row fields
		items = htmllist_get_select_items('fields',tableId);
		tr = d.createElement("tr");
		tr.setAttribute("id","tr_edit_" + totalrows);
		for (var i in items) {
			fieldhtml = createFieldHtml(tableId, items[i], totalrows);
			td = d.createElement("td");
			td.innerHTML = fieldhtml;
			tr.appendChild(td);
		}
		td = d.createElement("td");
		td.rowSpan = "1";
		td.setAttribute("class","list");
		
		// Recreate the button table.
		btable = document.createElement("table");
		btable.setAttribute("border", "0");
		btable.setAttribute("cellspacing", "0");
		btable.setAttribute("cellpadding", "1");
		btbody = document.createElement("tbody");
		btr = document.createElement("tr");
		
		btd = document.createElement("td");
		btd.setAttribute("valign", "middle");
		btd.innerHTML =
			'<input name="'+tableId+'_rowindex'+totalrows+'" id="'+tableId+'_rowindex'+totalrows+'" type="hidden" value="'+totalrows+'" />' +
			'<img src="/themes/' + theme + '/images/icons/icon_x.gif" title="delete entry" width="17" height="17" border="0" onclick="deleteRow(' + totalrows + ", '" + tableId + "'); return false;\" />";
		btr.appendChild(btd);
		
		btd = document.createElement("td");
		btd.setAttribute("valign", "middle");
		btd.innerHTML = '<img src="/themes/' + theme + "/images/icons/icon_plus.gif\" title=\"duplicate entry\" width=\"17\" height=\"17\" border=\"0\" onclick=\"dupRow(" + totalrows + ", '" + tableId + "'); return false;\" />";
		btr.appendChild(btd);

		
		var tabledefinition = htmllist_get_select_items("tabledefinition", tableId);
		if (tabledefinition && tabledefinition['keyfield'] == "") {
			btd = document.createElement("td");
			btd.setAttribute("valign", "middle");
			btd.innerHTML = '<img src="/themes/' + theme + "/images/icons/icon_up.gif\" title=\"move entry up\" width=\"17\" height=\"17\" border=\"0\" onclick=\"moveRowUp(" + totalrows + ", '" + tableId + "'); return false;\" />";
			btr.appendChild(btd);
			
			btd = document.createElement("td");
			btd.setAttribute("valign", "middle");
			btd.innerHTML = '<img src="/themes/' + theme + "/images/icons/icon_down.gif\" title=\"move entry down\" width=\"17\" height=\"17\" border=\"0\" onclick=\"moveRowDown(" + totalrows + ", '" + tableId + "'); return false;\" />";
			btr.appendChild(btd);
		}
		
		btbody.appendChild(btr);
		btable.appendChild(btbody);
		td.appendChild(btable);
		tr.appendChild(td);
		tbody.appendChild(tr); // add the edit row to the table
		
		// create viewdetail row
		items = htmllist_get_select_items('fields_details',tableId);
		if (items) {
			tr = d.createElement("tr");
			tr.setAttribute("id","tr_viewdetail_" + totalrows);
			td = d.createElement("td");
			tr.appendChild(td);
			td = d.createElement("td");
			table = d.createElement("table");
			table.setAttribute("cellspacing","0");
			for (var i in items) {
				field = items[i];
				fieldhtml = createFieldHtml(tableId, field, totalrows);
				subtr = d.createElement("tr");
				subtr.setAttribute("id","tr_edititemdetails_" + totalrows + "_" + field['name']);
				subtd = d.createElement("td");
				subtd.setAttribute("class","vncell");
				subtd.innerHTML = field['columnheader'] + ": ";
				subtr.appendChild(subtd);
				subtd = d.createElement("td");
				subtd.setAttribute("class","vncell");
				subtd.innerHTML = field['description'] + "<br/>" + fieldhtml;
				subtr.appendChild(subtd);
				table.appendChild(subtr);
			}
			td.appendChild(table);
			tr.appendChild(td);
			tbody.appendChild(tr);  // add the viewdetail row to the table
		}
		// show/hide conditional fields if applicable using a custom function.
		javascript_event = tableId + "_listitem_change";
		var fn = window[javascript_event];
		if (typeof fn === 'function'){
			fn(tableId, "toggle_details", totalrows, null);
		}
		
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
			dupEl = document.getElementById(tableId + items[i]['name'] + rowId);
			newEl = document.getElementById(tableId + items[i]['name'] + totalrows);
			if (dupEl && newEl)
				if(items[i]['type'] == 'checkbox')
					newEl.checked = dupEl.checked;
				else
					newEl.value = dupEl.value;
		}
		items = htmllist_get_select_items('fields_details', tableId);
		for (var i in items) {
			dupEl = document.getElementById(tableId + items[i]['name'] + rowId);
			newEl = document.getElementById(tableId + items[i]['name'] + totalrows);
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

	function moveRowUp(rowId, tableId) {
	  moveRow(rowId, tableId, true);
	}
	function moveRowDown(rowId, tableId) {
	  moveRow(rowId, tableId, false);
	}
	function moveRow(rowId, tableId, up) {
		var rowview = document.getElementById("tr_view_" + rowId);
		var rowedit = document.getElementById("tr_edit_" + rowId);
		var rowviewdetail = document.getElementById("tr_viewdetail_" + rowId);
		
		var parent = rowedit.parentNode;
		var swapid;
		var swaprowedit;
		if (up){
			//move current rows before the previous row
			var prevtr;
			if (rowview) {
				prevtr = rowview.previousElementSibling;
			} else {
				prevtr = rowedit.previousElementSibling;
			}
			if (!prevtr)
				return; // was already top element.
			var swapid = prevtr['id'];
			var i = swapid.lastIndexOf('_');
			swapid = swapid.substr(i+1);
			var prevrowview = document.getElementById("tr_view_" + swapid);
			swaprowedit = document.getElementById("tr_edit_" + swapid);
			if (prevrowview){
				firstprevrow = prevrowview;
			} else {
				firstprevrow = swaprowedit;
			}
			// move the 3 rows
			if (rowview) {
				parent.insertBefore(rowview, firstprevrow);
			}
			parent.insertBefore(rowedit, firstprevrow);
			if (rowviewdetail) {
				parent.insertBefore(rowviewdetail, firstprevrow);
			}
		} else {
			//move next row before the current row
			var nexttr;
			if (rowviewdetail) {
				nexttr = rowviewdetail.nextElementSibling;
			} else {
				nexttr = rowedit.nextElementSibling;
			}
			if (!nexttr) {
				return; // was already bottom element.
			}
			var swapid = nexttr['id'];
			var i = swapid.lastIndexOf('_');
			swapid = swapid.substr(i+1);
			var prevrowview = document.getElementById("tr_view_" + swapid);
			swaprowedit = document.getElementById("tr_edit_" + swapid);
			var prevrowviewdetail = document.getElementById("tr_viewdetail_" + swapid);
			if (rowview){
				firstrow = rowview;
			} else {
				firstrow = rowedit;
			}
			// move the 3 rows
			if (prevrowview) {
				parent.insertBefore(prevrowview, firstrow);
			}
			parent.insertBefore(swaprowedit, firstrow);
			if (prevrowviewdetail) {
				parent.insertBefore(prevrowviewdetail, firstrow);
			}
		}
		
		var id_a = document.getElementById(tableId+'_rowindex' + swapid);
		var id_b = document.getElementById(tableId+'_rowindex' + rowId);
		temp = id_a.value;
		id_a.value = id_b.value;
		id_b.value = temp;
	}
	
	function htmltable_toggle_details(tableId, rowNr, table_row_detail_id) {
		tredit = document.getElementById(table_row_detail_id+'_off');
		trviewdetail = document.getElementById(table_row_detail_id+'_edit');
		treditdetail = document.getElementById(table_row_detail_id+'_view');
		current_on = tredit.style.display == 'none';
		tredit.style.display=current_on ? '' : 'none';
		trviewdetail.style.display=current_on ? 'none' : '';
		treditdetail.style.display=current_on ? '' : 'none';

		// show/hide conditional fields if applicable using a custom function.
		javascript_event = tableId + "_listitem_change";
		var fn = window[javascript_event];
		if (typeof fn === 'function'){
			fn(tableId, "toggle_details", rowNr, null);
		}
	}
</script><?
}

?>
