<?php
/* $Id$ */
/* ========================================================================== */
/*
	e2guardian.php
	Copyright (C) 2015-2017 Marcello Coutinho
	part of pfSense (http://www.pfSense.com)
	All rights reserved.
*/
/* ========================================================================== */
/*
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
/* ========================================================================== */

require_once("/etc/inc/util.inc");
require_once("/etc/inc/functions.inc");
require_once("/etc/inc/pkg-utils.inc");
require_once("/etc/inc/globals.inc");
require_once("/usr/local/pkg/e2guardian.inc");

function fetch_blacklist($log_notice = true, $install_process = false) {
	global $config, $g;
	if (is_array($config['installedpackages']['e2guardianblacklist']) && is_array($config['installedpackages']['e2guardianblacklist']['config'])) {
		$url = $config['installedpackages']['e2guardianblacklist']['config'][0]['url'];
		$uw = "Found a previous install, checking Blacklist config...";
	} else {
		$uw = "Found a clean install, reading default access lists...";
	}
	if ($install_process == true) {
		update_output_window($uw);
	}
	if (isset($url) && is_url($url)) {
		if ($log_notice == true) {
			print "file download start..";
			unlink_if_exists("/usr/local/pkg/blacklist.tgz");
			exec("/usr/bin/fetch -o /usr/local/pkg/blacklist.tgz " . escapeshellarg($url), $output, $return);
		} else {
			//install process
			if (file_exists("/usr/local/pkg/blacklist.tgz")) {
				update_output_window("Found previous blacklist database, skipping download...");
				$return = 0;
			} else {
				update_output_window("Fetching blacklist");
				download_file_with_progress_bar($url, "/usr/local/pkg/blacklist.tgz");
				if (file_exists("/usr/local/pkg/blacklist.tgz")) {
					$return = 0;
				}
			}
		}
		if ($return == 0) {
			extract_black_list($log_notice);
		} else {
			file_notice("E2guardian",$error,"E2guardian" . gettext("Could not fetch blacklists from url"), "");
		}
	} else {
		if ($install_process == true) {
			read_lists(false, $uw);
		} elseif (!empty($url)) {
			file_notice("E2guardian",$error,"E2guardian" . gettext("Blacklist url is invalid."), "");
		}
	}
}
function extract_black_list($log_notice=true) {
	if (!file_exists("/usr/local/pkg/blacklist.tgz")) {
		file_notice("E2guardian",$error,"E2guardian" . gettext("Downloaded blacklists not found"), "");
		return;
	}
	chdir ("/usr/local/etc/e2guardian/lists");
	if (is_dir ("blacklists.old")) {
        	exec ('rm -rf blacklists.old');
	}
	if (is_dir ("blacklists")) {
		rename("blacklists", "blacklists.old");
	}
	exec('/usr/bin/tar -xvzf /usr/local/pkg/blacklist.tgz 2>&1', $output, $return);
	if (preg_match("/x\W+(\w+)/", $output[1], $matches)) {
		if ($matches[1] != "blacklists") {
			rename("./" . $matches[1], "blacklists");
		}
		read_lists($log_notice);
	} else {
		file_notice("E2guardian",$error,"E2guardian - " .  gettext("Could not determine Blacklist extract dir. Categories not updated"),"");
	}
}

function read_lists($log_notice=true, $uw="") {
	global $config, $g;
	$group_type = array();
	$dir = "/usr/local/etc/e2guardian/lists";
	// Read e2guardian lists dirs
	$groups = array("phraselists", "blacklists", "whitelists");
	// Assigns know list files
	$types = array('domains', 'urls', 'banned', 'weighted', 'exception', 'expression');

	// Clean previous xml config for e2guardian lists
	foreach ($config['installedpackages'] as $key => $values) {
		if (preg_match("/e2guardian(phrase|black|white)lists/", $key)) {
			unset ($config['installedpackages'][$key]);
		}
	}
	//find lists
	foreach ($groups as $group) {
		if (is_dir("$dir/$group/")) {
			//read dir content and find lists
			$lists = scandir("$dir/$group/");
			foreach ($lists as $list) {
				if (!preg_match ("/^\./", $list) && is_dir("$dir/$group/$list/")) {
					$category = scandir("$dir/$group/$list/");
					foreach ($category as $file) {
						if (!preg_match ("/^\./", $file)) {
							if (is_dir("$dir/$group/$list/$file")) {
								$subdir = $file;
								$subcategory = scandir("$dir/$group/$list/$subdir/");
								foreach ($subcategory as $file) {
									if (!preg_match ("/^\./", $file)) {
										//add category to file https://github.com/e2guardian/e2guardian/issues/244
										system("echo '#listcategory: \"{$list}_{$subdir}\"' >> $dir/$group/$list/$subdir/$file");
										//assign list to array
										$type = explode("_", $file);
										if (preg_match("/(\w+)/", $type[0], $matches)) {
											$xml_type = $matches[1];
										}
										if ($config['installedpackages']['e2guardianblacklist']['config'][0]["liston"] == "both" && $group == "blacklists") {
											$config['installedpackages']['e2guardianwhitelists'.$xml_type]['config'][] = array("descr" => "{$list}_{$subdir} {$file}", "list" => "{$list}_{$subdir}", "file" => "$dir/$group/$list/$subdir/$file");
										}
										$config['installedpackages']['e2guardian' . $group . $xml_type]['config'][] = array("descr" => "{$list}_{$subdir} {$file}", "list" => "{$list}_{$subdir}", "file" => "$dir/$group/$list/$subdir/$file");
									}
								}
							} else {
								//add category to file https://github.com/e2guardian/e2guardian/issues/244
								system("echo '#listcategory: \"{$list}\"' >> $dir/$group/$list/$file");
								//assign list to array
								$type = explode("_", $file);
								if (preg_match("/(\w+)/", $type[0], $matches)) {
									$xml_type=$matches[1];
								}
								if ($config['installedpackages']['e2guardianblacklist']['config'][0]["liston"] == "both" && $group == "blacklists") {
									$config['installedpackages']['e2guardianwhitelists'.$xml_type]['config'][] = array("descr" => "$list $file", "list" => $list, "file" => "$dir/$group/$list/$file");
								}
								$config['installedpackages']['e2guardian' . $group . $xml_type]['config'][] = array("descr"=> "$list $file", "list" => $list, "file" => "$dir/$group/$list/$file");
							}
						}
					}
				}
			}
		}
	}
	$files = array("site", "url");
	foreach ($files as $edit_xml) {
		$edit_file=file_get_contents("/usr/local/pkg/e2guardian_".$edit_xml."_acl.xml");
		if (count($config['installedpackages']['e2guardianblacklistsdomains']['config']) > 18) {
			$edit_file=preg_replace('/size.6/', 'size>20', $edit_file);
			if ($config['installedpackages']['e2guardianblacklist']['config'][0]["liston"] == "both") {
				$edit_file=preg_replace('/size.5/', 'size>19', $edit_file);
			}
		} else {
			$edit_file=preg_replace('/size.20/', 'size>6', $edit_file);
		}
		if ($config['installedpackages']['e2guardianblacklist']['config'][0]["liston"] != "both") {
			$edit_file=preg_replace('/size.19/', 'size>5', $edit_file);
		}
		file_put_contents("/usr/local/pkg/e2guardian_" . $edit_xml . "_acl.xml", $edit_file, LOCK_EX);
	}
	write_config();
	if ($log_notice == true && $uw == "") {
		file_notice("E2guardian",$error,"E2guardian" . gettext("Blacklist applied, check site and URL access lists for categories"), "");
	} else {
		$uw .= "done\n";
		update_output_window($uw);
	}
}

if ($argv[1] == "update_lists") {
	extract_black_list();
}

if ($argv[1] == "fetch_blacklist") {
	fetch_blacklist();
}

?>
