<?php
/*
 * squidguard.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2006-2011 Serg Dvoriancev
 * Copyright (c) 2013 Alexander Wilke <nachtfalkeaw@web.de>
 * Copyright (c) 2013 Marcello Coutinho
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
require_once('config.inc');
require_once('filter.inc');
require_once('globals.inc');
require_once("notices.inc");
require_once('pfsense-utils.inc');
require_once('pkg-utils.inc');
require_once('service-utils.inc');
require_once('util.inc');
require_once("xmlrpc_client.inc");
require_once('squidguard_configurator.inc');

# ------------------------------------------------------------------------------
# fields
define('F_NAME',             'name');
define('F_DEST',             'dest');
define('F_SOURCE',           'source');
define('F_DESTINATION',      'dest');
define('F_REWRITE',          'rewrite');
define('F_REDIRECT',         'redirect');
define('F_TIME',             'time');
define('F_OVERDESTINATION',  'overdestination');
define('F_OVERREWRITE',      'overrewrite');
define('F_OVERREDIRECT',     'overredirect');
define('F_TARGETURL',        'targeturl');
define('F_REPLACETO',        'replaceto');
define('F_TIMETYPE',         'timetype');
define('F_TIMEDAYS',         'timedays');
define('F_DATERANGE',        'daterange');
define('F_TIMERANGE',        'sg_timerange');
define('F_IPLIST',           'iplist');
define('F_DESCRIPTION',      'description');
define('F_EXPRESSIONS',      'expressions');
define('F_DOMAINS',          'domains');
define('F_URLS',             'urls');
define('F_DISABLED',         'disabled');
define('F_SQUIDGUARDENABLE', 'squidguard_enable');
define('F_BLACKLIST',        'blacklist');

# prefixes
define('PREF_UPTIME',        'uptime_');
define('PREF_UPTIME_DENY',   'uptimedeny_');
define('PREF_OVERTIME',      'overtime_');
define('PREF_OVERTIME_DENY', 'overtimedeny_');
# modules
define('MODULE_GENERAL',         'squidguardgeneral');
define('MODULE_DEFAULT',         'squidguarddefault');
define('MODULE_ACL',             'squidguardacl');
define('MODULE_DESTINATION',     'squidguarddest');
define('MODULE_REWRITE',         'squidguardrewrite');
define('MODULE_SOURCE',          'squidguardsrc');
define('MODULE_TIME',            'squidguardtime');
define('MODULE_LOG',             'squidguardlog');
# blacklist
define('BLACKLIST_DEFAULT_URL',  'http://squidguard.mesd.k12.or.us/blacklists.tgz'); # 5Mb
define('BLACKLIST_DEFAULT_URL1', 'http://www.shallalist.de/Downloads/shallalist.tar.gz'); # ~7Mb
define('BLACKLIST_TMP_FILE',     '/var/tmp/blacklists.tar.gz');
define('BLACKLIST_BTN_URL',      'Upload Url');
define('BLACKLIST_BTN_DEFAULT',  'Restore default');
define('BLACKLIST_LOGFILE',      'blacklist.log');
#
define('APPLY_BTN',              'Apply');
define('SAFESEARCH',             'safesearch');

# ==============================================================================
# Initialization
# ==============================================================================
# use global variable $squidguard_config, defined in squidguard_configurator.inc
sg_init(convert_pfxml_to_sgxml());

# ==============================================================================
# Validations
# ==============================================================================
function squidguard_validate(&$post, &$input_errors)
{
    global $config, $g;
    $submit = isset($_GET['submit']) ? $_GET['submit'] : $_POST['submit'];

    # check config if 'Apply'
    if ($submit === APPLY_BTN) {
    	sg_check_config_data($input_errors);
    }
}

# ------------------------------------------------------------------------------
# validate default
# ------------------------------------------------------------------------------
function squidguard_validate_default(&$post, &$input_errors)
{
    squidguard_validate_acl($post, $input_errors);
}

# ------------------------------------------------------------------------------
# validate acl
# ------------------------------------------------------------------------------
function squidguard_validate_acl(&$post, &$input_errors)
{
    $pass_up = array();
    $deny_up = array();
    $pass_up_val = '';
    $pass_over = array();
    $deny_over = array();
    $pass_over_val = '';
    $id = get_item_id();

    # check name ('source')
    $name = trim($post[F_NAME]);
    if(!empty($name)) {
        # validate name format
        check_name_format($name, $input_errors);

        # check unique name
        if (!sg_check_unique_name(F_ACLS, $name))
            $input_errors[] = "Name '$name' already exists.";

        # check reserved
        if (!sg_check_reserved_name($name))
             $input_errors[] = "Name '$name' is reserved.";

        # check source
        $sgx = array();
        $sgx[F_NAME] = $post[F_NAME];
        $sgx[F_SOURCE] = $post[F_SOURCE];
        sg_check_src($sgx, $input_errors);
    }

    # store destinations to 'dest' value
    foreach ($post as $key => $val) {
        if (substr_count($key, PREF_UPTIME) != 0) {
            $name = str_replace(PREF_UPTIME, '', $key);
            if ($name) {
                switch($val) {
                    case "allow": $pass_up_val .= " $name";  break;
                    case "white": $pass_up_val .= " ^$name"; break;
                    case "deny" : $pass_up_val .= " !$name"; break;
                }
            }
        }
        elseif (substr_count($key, PREF_OVERTIME) != 0) {
            $name = str_replace(PREF_OVERTIME, '', $key);
            if ($name) {
                switch($val) {
                    case "allow": $pass_over_val .= " $name";  break;
                    case "white": $pass_over_val .= " ^$name"; break;
                    case "deny" : $pass_over_val .= " !$name"; break;
                }
            }
        }
    }

    # !ATTENTION! on pfSense XML config must be must(shell) be '!all' instead of 'none' - it is a must for correct work GUI

    # if not exists key 'all', then add 'none' - default 'deny all'
    if ((substr_count($pass_up_val, 'all') == 0)) {
         $pass_up_val .= ' !all';
    }

    if (!empty($pass_over_val) && (substr_count($pass_over_val, 'all') == 0)) {
         $pass_over_val .= ' !all';
    }

    if (empty($pass_over_val))
         $post[F_DEST] = "$pass_up_val";
    else $post[F_DEST] = "$pass_up_val [$pass_over_val]";

    # check redirect
    $errmsg = '';
    if (!sg_check_redirect($post[F_RMOD], $post[F_REDIRECT], $errmsg)) {
         $input_errors[] = "Redirect info error. $errmsg";
    }
}

# ------------------------------------------------------------------------------
# validate times
# Format:
#    date:   <date(or range)><time (or range)> -- days not parsed (reset to *)
#    weekly: <day or *><time or range>         -- dates not parsed (reset to '')
# ------------------------------------------------------------------------------
function squidguard_validate_times(&$post, &$input_errors)
{
    $id = get_item_id();

    # check name
    $name = trim($post[F_NAME]);
    if(!empty($name)) {
        check_name_format($name, $input_errors);

        # check unique name
        if (!sg_check_unique_name(F_TIMES, $name))
             $input_errors[] = "Name '$name' already exists";

        # check reserved
        if (!sg_check_reserved_name($name))
             $input_errors[] = "Name '$name' is reserved.";
    }

    # --- check format ---
    $sgx = array();
    $sgx[F_NAME] = $post[F_NAME];
    $sgx[F_DESCRIPTION] = $post[F_DESCRIPTION];
    # fields of $post have 'fnameX' format
    for ($i=0; isset($post[F_TIMETYPE."$i"]); $i++) {
        # correct and update
        if (strtolower($post[F_TIMETYPE."$i"]) === "date") {
             $post[F_TIMEDAYS."$i"] = '*';
             # date cant be empty
             if (trim($post[F_DATERANGE."$i"]) == '') $post[F_DATERANGE."$i"] = "*.*.*";
        }
        else $post[F_DATERANGE."$i"] = '';

        if (trim($post[F_TIMERANGE."$i"]) == '') $post[F_TIMERANGE."$i"] = "00:00-23:59";

        # $post->xml
        $sgx_row = array();
        $sgx_row[F_TIMETYPE]  = $post[F_TIMETYPE."$i"];
        $sgx_row[F_TIMEDAYS]  = $post[F_TIMEDAYS."$i"];
        $sgx_row[F_DATERANGE] = $post[F_DATERANGE."$i"];
        $sgx_row[F_TIMERANGE] = $post[F_TIMERANGE."$i"];
        $sgx[F_ITEM][] = $sgx_row;
    }
    #
    sg_check_time($sgx, $input_errors);

}

# ------------------------------------------------------------------------------
# validate destinations
# ------------------------------------------------------------------------------
function squidguard_validate_destination($post, &$input_errors) {
    # check name
    $name = trim($post[F_NAME]);
    if(!empty($name)) {
        check_name_format($name, $input_errors);

        # check unique name
        if (!sg_check_unique_name(F_DESTINATIONS, $name))
             $input_errors[] = "Name '$name' already exists";

        # check reserved
        if (!sg_check_reserved_name($name))
             $input_errors[] = "Name '$name' is reserved.";
    }

    # --- check format ---
    $sgx = array();
    $sgx[F_NAME]        = $post[F_NAME];
    $sgx[F_URLS]        = $post[F_URLS];
    $sgx[F_DOMAINS]     = $post[F_DOMAINS];
    $sgx[F_EXPRESSIONS] = $post[F_EXPRESSIONS];
    $sgx[F_RMOD]        = $post[F_RMOD];
    $sgx[F_REDIRECT]    = $post[F_REDIRECT];
    #
    sg_check_dest($sgx, $input_errors);
}

# ------------------------------------------------------------------------------
# validate rewrites
# ------------------------------------------------------------------------------
function squidguard_validate_rewrite($post, &$input_errors) {
         # check name
         $name = trim($post[F_NAME]);
         if(!empty($name)) {
            # check name format <char><symbols without space> - Ab123
            check_name_format($name, $input_errors);

            # check unique name
            if (!sg_check_unique_name(F_REWRITES, $name))
                 $input_errors[] = "Name '$name' already exists";

            # check reserved
            if (!sg_check_reserved_name($name))
                 $input_errors[] = "Name '$name' is reserved.";
         }
}

# -----------------------------------------------------------------------------
# squidguard_resync
# -----------------------------------------------------------------------------
function squidguard_resync() {
    $upload_file = '';
    $submit = isset($_REQUEST['submit'])         ? $_REQUEST['submit']          : '';
    $url    = isset($_REQUEST[F_BLACKLISTURL])   ? $_REQUEST[F_BLACKLISTURL]    : '';
    $proxy  = isset($_REQUEST['blacklist_proxy'])? $_REQUEST['blacklist_proxy'] : '';

    sg_init(convert_pfxml_to_sgxml());

    # blacklist upload
    if ($submit == BLACKLIST_BTN_URL) {
        if ($url)
            sg_reconfigure_blacklist($url, $proxy);
    }

    # blacklist restore last (if exists)
#   if ($submit == BLACKLIST_BTN_DEFAULT) {
#       restore_arc_blacklist();
#   }

    # apply changes
    //if ($submit == APPLY_BTN) {
#           write_config('Update squidGuard options.'); # store, if not 'Save' button


        sg_reconfigure();
    //}

    squidguard_cron_install();
    
    //Sync only with apply button to avoid multiples reloads on backup server while editing master config
    if ($submit == APPLY_BTN) 
		squidguard_sync_on_changes();
}

# -----------------------------------------------------------------------------
# squidguard_resync_acl
# -----------------------------------------------------------------------------

function squidguard_resync_acl() {
    global $config; # !!! ORDER !!!

    $conf = $config['installedpackages'][MODULE_ACL]['config'];
    $id = isset($_POST['id']) ? $_POST['id'] : $_GET['id'];

    # --- sources part ---
    # move current id by order
    if (($id !== '') and is_array($conf)) {
        $src_new = array();

        foreach ($conf as $key => $src) {
            $order = $src[F_ORDER];
            # n_key: no_move=$key+$order or move=$order+$key
            $n_key = is_numeric($order) ? sprintf("%04d%04d", $order, $key) : sprintf("%04d%04d", $key, 9999);
            unset($src[F_ORDER]); # ! must be unset for display correct default position in 'select'!
            $src_new[$n_key] = $src;
        }
        # sort by key
        ksort($src_new);
        reset($src_new);

        $src_new = array_values($src_new); # make keys '0, 1, 2, ...'

        # renew config
        unset ($config['installedpackages'][MODULE_ACL]['config']);
        $config['installedpackages'][MODULE_ACL]['config'] = $src_new;
        write_config('Update squidguardacl config');

        # renew global $squidguard_config
        sg_init(convert_pfxml_to_sgxml());
    }
}

# -----------------------------------------------------------------------------
# squidguard_resync_dest
# -----------------------------------------------------------------------------

function squidguard_resync_dest() {
	global $config; # !!! ORDER !!!

	$conf = $config['installedpackages'][MODULE_DESTINATION]['config'];
	$id = isset($_POST['id']) ? $_POST['id'] : $_GET['id'];

	# --- sources part ---
	# move current id by order
	if (($id !== '') and is_array($conf)) {
		$src_new = array();

		foreach ($conf as $key => $src) {
			$order = $src[F_ORDER];
			# n_key: no_move=$key+$order or move=$order+$key
			$n_key = is_numeric($order) ? sprintf("%04d%04d", $order, $key) : sprintf("%04d%04d", $key, 9999);
			unset($src[F_ORDER]); # ! must be unset for display correct default position in 'select'!
			$src_new[$n_key] = $src;
		}
		# sort by key
		ksort($src_new);
		reset($src_new);

		$src_new = array_values($src_new); # make keys '0, 1, 2, ...'

		# renew config
		unset ($config['installedpackages'][MODULE_DESTINATION]['config']);
		$config['installedpackages'][MODULE_DESTINATION]['config'] = $src_new;
		write_config('Update squidguarddest config');

		# renew global $squidguard_config
		sg_init(convert_pfxml_to_sgxml());
	}
}

# =============================================================================
# common functions
# =============================================================================

# -----------------------------------------------------------------------------
# get_pkgconf/sgconf_items_list
# -----------------------------------------------------------------------------
function get_pkgconf_items_list($pkg_gui_name, $fieldname) {
    global $config;
    $res = '';

    $conf = $config['installedpackages'][$pkg_gui_name]['config'];
    if (is_array($conf))
        foreach($conf as $cf) $res[] = $cf[$fieldname];

    return $res;
}

function get_sgconf_items_list($data_group, $fieldname) {
    global $squidguard_config;
    $res = '';

    $conf = $squidguard_config[$data_group]['item'];
    if (is_array($conf))
        foreach($conf as $cf) $res[] = $cf[$fieldname];

    return $res;
}

# ==============================================================================
# Before form
# ==============================================================================
# squidguard_before_form
# ------------------------------------------------------------------------------
function squidguard_before_form(&$pkg) {
    $i=0;

    foreach($pkg['fields']['field'] as $field) {
        # blacklist controls
        switch ($field['fieldname']) {
#            case F_BLACKLISTURL:
#                $fld = &$pkg['fields']['field'][$i];
#                $fld['description'] .= make_grid_blacklist();        # insert to description custom controls
#            break;
            # Apply button
            case 'squidguard_enable':
                $fld = &$pkg['fields']['field'][$i];
                $fld['sethelp'] .= make_grid_general_items();   # insert to description custom controls
            break;
        }
        $i++;
    }
}

# -----------------------------------------------------------------------------
# squidguard_before_form_acl
# -----------------------------------------------------------------------------
function squidguard_before_form_acl(&$pkg, $is_acl=true) {
    global $g;
    global $squidguard_config;

    $current_id   = '';
    $sources      = '';
    $source_items = '';
    $destinations = '';
    $dest_items   = '';
    $rewrites     = '';
    $rewr_names   = '';
    $times        = '';
    $time_names   = '';
    $acls_up      = '';
    $acls_over    = '';

    $current_id = isset($_POST['id']) ? $_POST['id'] : $_GET['id'];
    $current_id = ($current_id) ? $current_id : 0;

    # sources
    $source_items = get_sgconf_items_list(F_SOURCES, 'name');
    # generate sources list TODO: exclude used names from list, source name used in ACL unique
    $i=0;
    foreach($pkg['fields']['field'] as $field) {
        if ($field['fieldname'] == 'source') {
            $fld = &$pkg['fields']['field'][$i];
            if (is_array($source_items)) {
                foreach($source_items as $nm)
                    $fld['options']['option'][] = array('name'=>$nm, 'value'=>$nm);
            }
        }
        # order
        if ($field['fieldname'] == 'order') {
            $fld = &$pkg['fields']['field'][$i];
            if (is_array($source_items))
                foreach($source_items as $nmkey => $nm)
            	    $fld['options']['option'][] = array('name'=>$nm, 'value'=>$nmkey);
            $fld['options']['option'][] = array('name'=>'--- Last ---', 'value'=>'9999');
            $fld['options']['option'][] = array('name'=>'-----', 'value'=>''); # ! this is must be last !
        }
        $i++;
    }

    # destinations
    # acls pass ---> prepare data for destinations; dest format 'uptime_dests_list [overtime_dests_list]'
    $acl_dest = '';
    $acl_overdest = '';

    # acl & default
    if ($pkg['name'] !== MODULE_DEFAULT) {
         $acl_dest     = $squidguard_config[F_ACLS]['item'][$current_id][F_DESTINATIONNAME];
         $acl_overdest = $squidguard_config[F_ACLS]['item'][$current_id][F_OVERDESTINATIONNAME];
    }
    else $acl_dest = $squidguard_config[F_DEFAULT][F_DESTINATIONNAME];

    # acl dest ontime
    if ($acl_dest) {
        # 'none' > to '!all'
        $acl_dest = str_replace('none', '!all', $acl_dest);

        $pss = explode(' ', $acl_dest);
        foreach($pss as $val) {
            $name = $val;
            $name = str_replace('!', '', $name);
            $name = str_replace('^', '', $name);
            if (!empty($val)) {
                switch($val[0]) {
                    case '!': $acls_up[$name] = 'deny';  break;
                    case '^': $acls_up[$name] = 'white'; break;
                    default : $acls_up[$name] = 'allow'; break;
                }
            }
        }
    }

    # acl dest overtime
    if ($acl_overdest) {
        # 'none' > to '!all'
        $acl_overdest = str_replace('none', '!all', $acl_overdest);

        $pss = explode(' ', $acl_overdest);
        foreach($pss as $val) {
            $name = $val;
            $name = str_replace('!', '', $name);
            $name = str_replace('^', '', $name);
            if (!empty($val)) {
                switch($val[0]) {
                    case '!': $acls_over[$name] = 'deny';  break;
                    case '^': $acls_over[$name] = 'white'; break;
                    default : $acls_over[$name] = 'allow'; break;
                }
            }
        }
    }

    # --- Destinations ---
    # User destinations
    if ($squidguard_config[F_DESTINATIONS]) {
        foreach($squidguard_config[F_DESTINATIONS]['item'] as $dst) {
            $dest_items[] = array ('name'=>$dst[F_NAME],
                                   'upt_value'=>$acls_up[$dst[F_NAME]],
                                   'ovt_value'=>$acls_over[$dst[F_NAME]],
                                   'description'=>$dst[F_DESCRIPTION]);
        }
    }

    # Blacklist
    if ($squidguard_config[F_BLACKLISTENABLED] === 'on') {
        $blk_entries = sg_entries_blacklist();
        if (!empty($blk_entries)) {
            foreach($blk_entries as $dst) {
                $dest_items[] = array ('name'=>$dst,
                                       'upt_value'=>$acls_up[$dst],
                                       'ovt_value'=>$acls_over[$dst],
                                       'description'=>'');
            }
        }
    }

    # Default all
    $dest_items[] = array('name'=>FLT_DEFAULT_ALL,
                          'upt_value'=>$acls_up[FLT_DEFAULT_ALL],
                          'ovt_value'=>$acls_over[FLT_DEFAULT_ALL],
                          'description'=>'Default access');

    $i=0;
    foreach($pkg['fields']['field'] as $field) {
        if (($field['fieldname'] === 'dest')/* || ($field['fieldname'] == 'overdest')*/) {
            $fld = &$pkg['fields']['field'][$i];
            $fld['description'] .= make_grid_controls('', $dest_items, $is_acl);        # insert to description custom controls
        }
        $i++;
    }

    # rewrites
    $rewr_names = get_sgconf_items_list(F_REWRITES, 'name');
    $i=0;
    foreach($pkg['fields']['field'] as $field) {
        if (($field['fieldname'] == 'rewrite') || ($field['fieldname'] == 'overrewrite')) {
            $fld = &$pkg['fields']['field'][$i];
            $fld['options']['option'][] = array('name'=>'none (rewrite not defined)', 'value'=>'');
            if (is_array($rewr_names)) {
                foreach($rewr_names as $nm)
                    $fld['options']['option'][] = array('name'=>$nm, 'value'=>$nm);
            }
        }
        $i++;
    }

    # - set times field -
    $time_names = get_sgconf_items_list(F_TIMES, 'name');
    $i=0;
    foreach($pkg['fields']['field'] as $field) {
        if ($field['fieldname'] === 'time') {
            $fld = &$pkg['fields']['field'][$i];
            $fld['options']['option'][] = array('name'=>'none (time not defined)', 'value'=>'');
            if (is_array($time_names)) {
                foreach($time_names as $nm)
                    $fld['options']['option'][] = array('name'=>$nm, 'value'=>$nm);
            }
            break;
        }
        $i++;
    }
}

# -----------------------------------------------------------------------------
# squidguard_before_form_dest
# -----------------------------------------------------------------------------
function squidguard_before_form_dest(&$pkg) {
	global $g, $squidguard_config;
	$destination_items = get_sgconf_items_list(F_DESTINATIONS, 'name');
//var_dump($squidguard_config);
	$i=0;
	foreach($pkg['fields']['field'] as $field) {
		# order
		if ($field['fieldname'] == 'order') {
			$fld = &$pkg['fields']['field'][$i];
			if (is_array($destination_items))
				foreach($destination_items as $nmkey => $nm)
					$fld['options']['option'][] = array('name'=>$nm, 'value'=>$nmkey);
			$fld['options']['option'][] = array('name'=>'--- Last ---', 'value'=>'9999');
			$fld['options']['option'][] = array('name'=>'-----', 'value'=>''); # ! this is must be last !
		}
		$i++;
	}
}

# -----------------------------------------------------------------------------
# make_grid_general_items
# -----------------------------------------------------------------------------
function make_grid_general_items($id = '') {
	global $squidguard_config;

	$bg_color = 'bgcolor="#dddddd"';
	$res = '';
	$res .= '<table width="100%">';

	if ($id === '') {
		# Apply
		$res .= '<tr $bg_color><td><big>The Save button at the bottom of this page must be clicked to save configuration changes.</big></td></tr>';
		$res .= '<tr><td><big>To activate squidGuard configuration changes, <b><span style="color: #800000;">the Apply button must be clicked.</span></b></big></td></tr>';
		$res .= '<tr><td><button class="btn btn-success btn-sm" name="submit" type="submit" value="Apply"><i class="fa fa-check icon-embed-btn"></i>Apply</button><br/><br/></td></tr>';

		# service state
		$sgstate = '<span style="color: #800000;">STOPPED</span>';
		if (is_service_running("squidGuard")) {
			$sgstate = '<span style="color: #008000;">STARTED</span>';
		}
		if (is_blacklist_update_started()) {
			$sgstate .= '<br><span style="color: #800000;">Wait: Blacklists update started. New data will be available soon.<br>After upgrade, check the configuration.</span>';
		}
		$res .= "<tr {$bg_color}><td><big>SquidGuard service state: <b>{$sgstate}</b></big></td></tr>";
	}

	$res .= "</table>";
	return $res;
}

# -----------------------------------------------------------------------------
# make_grid_blacklist
# -----------------------------------------------------------------------------
function make_grid_blacklist() {
	$res = '';
	# button 'Upload URL' and button 'Restore last blacklist'
	$res = '<hr><button class="btn btn-primary btn-sm" name="submit" value="' . BLACKLIST_BTN_URL  . '" type="submit"><i class="fa fa-upload icon-embed-btn"></i>' . BLACKLIST_BTN_URL  . '</button>';
	$res .= '&nbsp;<button class="btn btn-info btn-sm" name="submit" value="' . BLACKLIST_BTN_DEFAULT  . '" type="submit"><i class="fa fa-undo icon-embed-btn"></i>' . BLACKLIST_BTN_DEFAULT  . '</button>';
	return $res;
}

# -----------------------------------------------------------------------------
# make_grid_controls
# -----------------------------------------------------------------------------
function make_grid_controls($type, $items, $enable_overtime = true) {
    global $g;

    $res = '';
    $tbl = '';
    $color = '';
    $color2 = '';
    $x = 0;

    foreach($items as $item) {
        if ($x === 0) {
            $color = '';
            $color2 = 'style="background-color: #dddddd;"';
            $x = 1;
        }
        else {
            $color = 'style="background-color: #dddddd;"';
            $color2 = '';
            $x = 0;
        }

        $name    = trim($item['name']);
        $upt_val = $item['upt_value'];
        $ovt_val = $item['ovt_value'];
        $description = $item['description'];

        if (!$name) continue; # skip empty

        $sel = "selected=\"selected\"";
        $upt_A = $upt_B = $upt_C = $upt_D = '';
        switch($upt_val) {
            case "allow": $upt_B = $sel; break;
            case "white": $upt_C = $sel; break;
            case "deny" : $upt_D = $sel; break;
            default:      $upt_A = $sel; break;
        }

        $ovt_A = $ovt_B = $ovt_C= $ovt_D = '';
        switch($ovt_val) {
            case "allow": $ovt_B = $sel; break;
            case "white": $ovt_C = $sel; break;
            case "deny" : $ovt_D = $sel; break;
            default:      $ovt_A = $sel; break;
        }
        unset($sel);

        $tbl .= "<tr>";
        # uptime table
        $tnm  = PREF_UPTIME . $name;
        $tbl .= "<td $color></td>";
        $tbl .= "<td $color>$description [$name]</td>";
        $tbl .= "<td $color>access</td>";
        $tbl .= "<td $color><select id=$tnm name=\"$tnm\">";
        if ($name !== "all"/*substr_count($name, "all") === 0*/) {
            $tbl .= "<option value=none  name=\"----\"  $upt_A>----</option>";
            $tbl .= "<option value=white name=\"white\" $upt_C>whitelist</option>";
            $tbl .= "<option value=deny  name=\"deny\"  $upt_D>deny </option>";
            $tbl .= "<option value=allow name=\"allow\" $upt_B>allow</option>";
        }
        else {
            $tbl .= "<option value=allow name=\"allow\" $upt_B>allow</option>";
            $tbl .= "<option value=deny  name=\"deny\"  $upt_D>deny </option>";
        }
        $tbl .= "</td>";

        # overtime table
        if ($enable_overtime) {
            $tnm  = PREF_OVERTIME . $name;
            $tbl .= "<td $color></td>";
            $tbl .= "<td $color>$description [$name]</td>";
            $tbl .= "<td $color>access</td>";
            $tbl .= "<td $color><select id=$tnm name=\"$tnm\">";
            if ($name !== "all"/*substr_count($name, "all") === 0*/) {
                $tbl .= "<option value=none  name=\"----\"  $ovt_A>----</option>";
                $tbl .= "<option value=white name=\"white\" $ovt_C>whitelist</option>";
                $tbl .= "<option value=deny  name=\"deny\"  $ovt_D>deny </option>";
                $tbl .= "<option value=allow name=\"allow\" $ovt_B>allow</option>";
            }
            else {
                $tbl .= "<option value=allow name=\"allow\" $ovt_B>allow</option>";
                $tbl .= "<option value=deny  name=\"deny\"  $ovt_D>deny </option>";
            }
            $tbl .= "</td>";
        }
        $tbl .= "</tr>";
    }

    # header
    if (!empty($tbl)) {
        $color = 'style="background-color: #dddddd;"';
        $thdr = '';
        $hdr1up = "<big>Target Categories</big>";
        $hdr1ov = "<big>Target Categories for off-time</big>";
        $hds3   = "ACCESS: 'whitelist' - always pass; 'deny' - block; 'allow' - pass, if not blocked.";
        if ($enable_overtime) {
            $thdr .= "<tr><td colspan='8' align=left>$hds3</td></tr>";
            $thdr .= "<tr $color><th colspan='4' align=middle>$hdr1up</th><th colspan='4' align=middle>$hdr1ov</th></tr>";
            $thdr .= "<tr $color><td colspan='4' align=middle></td><td colspan='4' align=middle>If <b>'Time'</b> not defined, this is column will be ignored.</td></tr>";
            # formatting
            $thdr .= "<tr><td/><td width='35%'/><td/><td/><td/><td width='35%'/><td/><td/></tr>";
        }
        else {
            $thdr .= "<tr><td colspan='4' align=left>$hds3<hr></tr>";
            $thdr .= "<tr $color><th colspan='4' align=middle>$hdr1up</th></tr>";
            # formatting
            $thdr .= "<tr><td width='5%'/><td/><td width='5%'/><td width='10%'/></tr>";
        }

        $res .= "<table cellspacing='0' width='100%'> $thdr $tbl </table>";

        $rstyle = "";
	$ha = <<<EOF
<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">
			<span onClick="document.getElementById('destrules').style.display = 'block';" style="cursor: pointer;">
				Target Rules List
				<i class="fa fa-plus-circle"></i>
			</span>
			<span style="cursor: pointer;">
				<i class="fa fa-minus-circle" onClick="document.getElementById('destrules').style.display = 'none';"></i>
			</span>
		</h2>
	</div>
</div>

EOF;
	$res = "<hr>{$ha}<div id=\"destrules\" style=\"DISPLAY: none\">{$res}</div>";

    }
    return $res;
}

# -----------------------------------------------------------------------------
# check unique name
# -----------------------------------------------------------------------------
function sg_check_unique_name($module_id, $name, $log='') {
    $res = true;
    $id = (isset($_GET['id'])) ? $_GET['id'] : $_POST['id'];

    $name_list = get_sgconf_items_list($module_id, 'name');
    $name_val = (is_array($name_list)) ? array_count_values($name_list) : array();
    $count_names = $name_val[$name];

    # if count names = 1, then check if add new record with this name(not valid) / or this is a self record(valid)
    # else if count names > 1 - not valid
    if ($count_names === 1) {
        $nm_key = array_search($name, $name_list);
        # if this new record
        if ($id >= count($name_list)) { $res = false; }
        # if not self record
        elseif ($nm_key && (intval($id) !== intval($nm_key))) { $res = false; }
    }
    elseif($count_names > 1) $res = false; # bad - not unique

    return $res;
}

# -----------------------------------------------------------------------------
# check unique name
# -----------------------------------------------------------------------------
function sg_check_reserved_name($name, $log='')
{
    $res = true;
    $reserved = array("acl", "all", "allow", "dbhome", "default", "dest", "in-addr", "log", "logdir", "none", "pass", "rew", "src", "url", "user");

    if (in_array(strtolower(trim($name)), $reserved)) {
        $res = false;
    }

    return $res;
}
# ------------------------------------------------------------------------------
# Install & deinstall
# ------------------------------------------------------------------------------

function squidguard_install_command() {
    if (!is_service_running("squidGuard")) {
        sg_init(convert_pfxml_to_sgxml());
        sg_check_system();

        # generate squidGuard blacklist entries file (check with squidGuard PORT)
#        conf_mount_rw();
        $blklist_file = SQUIDGUARD_BLK_FILELISTPATH;

     
        if (!file_exists($blklist_file)) {
        	# if blacklist not exists, then copy default db from samples
#            $entries = array("ads", "aggressive", "audio-video", "drugs", "gambling", "hacking", "mail", "porn", "proxy", "violence", "warez");
#            file_put_contents($blklist_file, implode("\n", $entries));
        }
        set_file_access(SQUIDGUARD_WORKDIR, OWNER_NAME, 0755);
        set_file_access(SQUIDGUARD_DBHOME, OWNER_NAME, 0755);
#        conf_mount_ro();

        sg_reconfigure();
    }
}

function squidguard_deinstall_command() {
    # remove entries from squid config
    squid_reconfigure('remove redirector options');

    # Note: When you reinstall should remain Database

    # remove package and his depends
    #mwexec("pkg_delete squidGuard-1.2.0_1");
    #mwexec("rm -rf " . SQUIDGUARD_WORKDIR);
    # i known't, really need delete blacklist base?
    #mwexec("rm -rf " . SQUIDGUARD_DBHOME);
    #mwexec("/bin/rm -f " . SQUIDGUARD_CONFBASE . "/squidGuard*");
}

# ------------------------------------------------------------------------------
# SquidGuard print JavaSrcript
# ------------------------------------------------------------------------------
function squidGuard_print_javascript() {
    $javascript = '';

    $xml = ($_GET["xml"] !== '') ? $_GET["xml"] : $_POST["xml"];

    # squidguard_default.xml
    if ($xml === "squidguard_default.xml") {
        $javascript .= "\n<script language='JavaScript'>";
        $javascript .= "\n<!--";
        $javascript .= "\n    document.iform.dest.disabled=1;";
        $javascript .= "\n//-->";
        $javascript .= "\n</script>";
    } # if

    # squidguard_acl.xml
    if ($xml === "squidguard_acl.xml") {
        $javascript .= "\n<script language='JavaScript'>";
        $javascript .= "\n<!--";
        $javascript .= "\n    document.iform.dest.disabled=1;";
        $javascript .= "\n//-->";
        $javascript .= "\n</script>";

    } # if

    if ($xml === "squidguard_time.xml") {
        $javascript .= "\n<script language='JavaScript'>";
        $javascript .= "\n<!--";
        $javascript .= "\n    function on_updatecontrols() {";
        $javascript .= "\n        for (var i=0; i<99; i++) {";
        $javascript .= "\n            var elm = document.iform.elements['timetype' + i];";
        $javascript .= "\n            if (elm) {";
        $javascript .= "\n                document.iform.elements['timetype' + i].onclick = on_updatecontrols;";
        $javascript .= "\n                if (document.iform.elements['timetype' + i].value == 'weekly') {";
        $javascript .= "\n                    document.iform.elements['timedays' + i].disabled = 0;";
        $javascript .= "\n                    document.iform.elements['daterange' + i].disabled = 1;";
        $javascript .= "\n                }";
        $javascript .= "\n                else {";
        $javascript .= "\n                    document.iform.elements['timedays' + i].disabled = 1;";
        $javascript .= "\n                    document.iform.elements['daterange' + i].disabled = 0;";
        $javascript .= "\n                }";
        $javascript .= "\n            }";
        $javascript .= "\n        }";
        $javascript .= "\n    }";
        $javascript .= "\n    on_updatecontrols();";
        $javascript .= "\n    ";
        $javascript .= "\n//-->";
        $javascript .= "\n</script>";
    }

    print($javascript);
}

# ==============================================================================
# Converter
# ==============================================================================
# convert_pfxml_to_sgxml
# -----------------------------------------------------------------
function convert_pfxml_to_sgxml() {

    capability_update_source();

    global $config;
    conf_mount_rw();
    $sgxml = array();
    $pfxml = $config['installedpackages'][MODULE_GENERAL]['config'][0];

    $sgxml[F_LOGDIR]           = SQUIDGUARD_LOGDIR;
    $sgxml[F_DBHOME]           = SQUIDGUARD_DBHOME;
    $sgxml[F_LDAPENABLE]       = $pfxml['ldap_enable'];
    $sgxml[F_LDAPBINDDN]       = $pfxml['ldapbinddn'];
    $sgxml[F_LDAPBINDPASS]     = $pfxml['ldapbindpass'];
    $sgxml[F_LDAPVERSION]      = $pfxml['ldapversion'];
    $sgxml[F_STRIPNTDOMAIN]    = $pfxml['stripntdomain'];
    $sgxml[F_STRIPREALM]       = $pfxml['striprealm'];
    $sgxml[F_BINPATH]          = SQUIDGUARD_BINPATH;
    $sgxml[F_WORKDIR]          = SQUIDGUARD_WORKDIR;
    $sgxml[F_SGCONF_XML]       = SQUIDGUARD_WORKDIR . SQUIDGUARD_CONFXML;
    $sgxml[F_ENABLED]          = $pfxml[F_SQUIDGUARDENABLE];
    $sgxml[F_BLACKLISTENABLED] = $pfxml[F_BLACKLIST];
    $sgxml[F_BLACKLISTURL]     = $pfxml[F_BLACKLISTURL];
    $sgxml[F_SOURCES]          = convert_pfxml_to_sgxml_source($config);
    $sgxml[F_DESTINATIONS]     = convert_pfxml_to_sgxml_destination($config);
    $sgxml[F_REWRITES]         = convert_pfxml_to_sgxml_rewrite($config);
    $sgxml[F_TIMES]            = convert_pfxml_to_sgxml_time($config);
    $sgxml[F_ACLS]             = convert_pfxml_to_sgxml_acl($config);
    $sgxml[F_DEFAULT]          = convert_pfxml_to_sgxml_default($config);

    # log
    $sgxml[F_ENABLELOG]    = $pfxml['enable_log']    == 'on' ? 'on' : 'off';
    $sgxml[F_ENABLEGUILOG] = $pfxml['enable_guilog'] == 'on' ? 'on' : 'off';
    $sgxml[F_LOGROTATION]  = $pfxml['log_rotation']  == 'on' ? 'on' : 'off';

    #Clean adversiting
    $sgxml[F_ADV_BLANKIMG] = $pfxml['adv_blankimg']  == 'on' ? 'on' : 'off';
	

    # other
    $lanip = $config['interfaces']['lan']['ipaddr'];
    $sgxml[F_CURRENT_LAN_IP] = $lanip;

    # transparent
    $squidxml = $config['installedpackages']['squid']['config'][0];
    if($squidxml['transparent_proxy'] == 'on') {
        $guiport = $config['system']['webgui']['port'];
        $guiprotocol = $config['system']['webgui']['protocol'];

        $sgxml[F_SQUID_TRANSPARENT_MODE] = 'on';
        $sgxml[F_CURRENT_GUI_PORT]  = $guiport;
        $sgxml[F_CURRENT_GUI_PROTO] = $guiprotocol;
    } else {
        unset($sgxml[F_SQUID_TRANSPARENT_MODE]);
        unset($sgxml[F_CURRENT_GUI_PORT]);
        unset($sgxml[F_CURRENT_GUI_PROTO]);
    }

    # store cfg cache
    $cfg_xml = dump_xml_config($sgxml, F_SQUIDGUARD);
    safe_mkdir(dirname($sgxml[F_SGCONF_XML]));
    file_put_contents($sgxml[F_SGCONF_XML], $cfg_xml);
    conf_mount_ro();

    return $sgxml;
}

# -----------------------------------------------------------------
# convert_pfxml_to_sgxml_source
# sgxml_source: [name][ip][desc][log]
# -----------------------------------------------------------------
# Changes 04-01-2008 :
# Source fields moved to ACL page. Source page - will remove
# But in XML internal config nothing to change
# -----------------------------------------------------------------
# Changes 21-07-2008 :
# Source IP and domain move to one field, added 'username'.
function convert_pfxml_to_sgxml_source($pfconfig) {
    $sgxml = array();
    $pfxml = $pfconfig['installedpackages'][MODULE_ACL]['config'];
    if (is_array($pfxml)) {
        foreach($pfxml as $pfx) {
            $sgx = array();
            $sgx[F_NAME]        = $pfx['name'];
            $sgx[F_SOURCE]      = $pfx[F_SOURCE];
            $sgx[F_LOG]         = $pfx[F_ENABLELOG];
            $sgx[F_DESCRIPTION] = $pfx['description'];
            $sgxml[F_ITEM][]    = $sgx;
         }
    }
    return $sgxml;
}

# -----------------------------------------------------------------
# convert_pfxml_to_sgxml_destination
# sgxml_destination: [name][domains][expr][urls][redir][desc][log]
# -----------------------------------------------------------------
function convert_pfxml_to_sgxml_destination($pfconfig) {
    $sgxml = array();
    $pfxml = $pfconfig['installedpackages'][MODULE_DESTINATION]['config'];
    if (is_array($pfxml)) {
        foreach($pfxml as $pfx) {
            $sgx = array();
            $sgx[F_NAME]         = $pfx['name'];
            $sgx[F_URLS]         = $pfx['urls'];
            $sgx[F_DOMAINS]      = $pfx[F_DOMAINS];
            $sgx[F_EXPRESSIONS]  = $pfx['expressions'];
            $sgx[F_RMOD]         = isset($pfx[F_RMOD]) ? $pfx[F_RMOD] : RMOD_NONE;
            $sgx[F_REDIRECT]     = $pfx[F_REDIRECT];
            $sgx[F_DESCRIPTION]  = $pfx['description'];
            $sgx[F_LOG]          = $pfx[F_ENABLELOG];
            $sgxml[F_ITEM][]     = $sgx;
        }
    }
    return $sgxml;
}

# -----------------------------------------------------------------
# convert_pfxml_to_sgxml_rewrite
# sgxml_rewrite: [name][desc][log][items(array): [targeturl][replaceto]]
# -----------------------------------------------------------------
function convert_pfxml_to_sgxml_rewrite($pfconfig) {
    $sgxml = array();

    $pfxml = $pfconfig['installedpackages'][MODULE_REWRITE]['config'];
    if (is_array($pfxml)) {
        foreach($pfxml as $pfx) {
            $sgx = array();
            $sgx[F_NAME]        = $pfx['name'];
            $sgx[F_DESCRIPTION] = $pfx['description'];
            $sgx[F_LOG]         = $pfx[F_ENABLELOG];

            if (is_array($pfx['row'])) {
                foreach($pfx['row'] as $pfx_row) {
                    $sgx_row = array();
                    $sgx_row[F_TARGETURL] = $pfx_row['targeturl'];
                    $sgx_row[F_REPLACETO] = $pfx_row['replaceto'];

                    $mode = '';
                    if (strpos($pfx_row[F_MODE], 'nocase') !== false) $mode .= 'i';
                    if (strpos($pfx_row[F_MODE], 'redirect') !== false) $mode .= 'r';
                    $sgx_row[F_MODE] = $mode; # ! sys options only  - not for GUI !

                    $sgx[F_ITEM][] = $sgx_row;
                 }
            }

            $sgxml[F_ITEM][] = $sgx;
        }
    }

    # additional: google safeserach
    $sgxml[F_ITEM][] = squidguard_adt_rewrite_safesrch();

    return $sgxml;
}

# -----------------------------------------------------------------
# convert_pfxml_to_sgxml_time
# sgxml_time: [name][desc][items(array): [timetype][timedays][daterange][timerange]]
# -----------------------------------------------------------------
function convert_pfxml_to_sgxml_time($pfconfig) {
    $sgxml = array();

    $pfxml = $pfconfig['installedpackages'][MODULE_TIME]['config'];
    if (is_array($pfxml)) {
        foreach($pfxml as $pfx) {
            $sgx = array();
            $sgx[F_NAME]                = $pfx[F_NAME];
            $sgx[F_DESCRIPTION]        = $pfx[F_DESCRIPTION];

            if (is_array($pfx['row'])) {
                foreach($pfx['row'] as $pfx_row) {
                    $sgx_row = array();
                    $sgx_row[F_TIMETYPE]  = $pfx_row[F_TIMETYPE];
                    $sgx_row[F_TIMEDAYS]  = $pfx_row[F_TIMEDAYS];
                    $sgx_row[F_DATERANGE] = $pfx_row[F_DATERANGE];
                    $sgx_row[F_TIMERANGE] = $pfx_row[F_TIMERANGE];
                    $sgx[F_ITEM][] = $sgx_row;
                }
            }

            $sgxml[F_ITEM][] = $sgx;
        }
    }

    return $sgxml;
}

# -----------------------------------------------------------------
# convert_pfxml_to_sgxml_acl
# sgxml_acl: [name][desc][disabled][timename][destname][redirect][rewritename][over_redirect][over_rewritename]
# -----------------------------------------------------------------
function convert_pfxml_to_sgxml_acl($pfconfig) {
    $sgxml = array();

    $pfxml = $pfconfig['installedpackages'][MODULE_ACL]['config'];
    if (is_array($pfxml)) {
        foreach($pfxml as $pfx) {
            $sgx = array();
            $sgx[F_NAME]          = $pfx[F_NAME];    # [04-01-2008] new ver
            $sgx[F_DESCRIPTION]   = $pfx[F_DESCRIPTION];
            $sgx[F_DISABLED]      = $pfx[F_DISABLED];
            $sgx[F_TIMENAME]      = $pfx[F_TIME];
            $sgx[F_REDIRECT]      = $pfx[F_REDIRECT];
            $sgx[F_RMOD]          = isset($pfx[F_RMOD]) ? $pfx[F_RMOD] : RMOD_NONE;
            $sgx[F_REWRITENAME]   = $pfx[F_REWRITE];
            $sgx[F_LOG]           = $pfx[F_ENABLELOG];
            $sgx[F_NOTALLOWINGIP] = $pfx[F_NOTALLOWINGIP];
            $sgx[F_ORDER]         = $pfx[F_ORDER];

            # for overtime
            $sgx[F_OVERREDIRECT]    = $pfx[F_REDIRECT]; # disabled ->- $pfx[F_OVERREDIRECT];
            $sgx[F_OVERREWRITENAME] = $pfx[F_OVERREWRITE];

            # destinations
            if (strpos($pfx['dest'], '[') === false) {
                $sgx[F_DESTINATIONNAME]     = trim($pfx['dest']);
                $sgx[F_OVERDESTINATIONNAME] = '';
            }
            else {
                $sgx[F_DESTINATIONNAME]     = trim( substr($pfx['dest'], 0, strpos($pfx['dest'], '[')) );
                $sgx[F_OVERDESTINATIONNAME] = trim( strstr($pfx['dest'], '[') );
                $sgx[F_OVERDESTINATIONNAME] = trim( str_replace(']', '', $sgx[F_OVERDESTINATIONNAME]) );
                $sgx[F_OVERDESTINATIONNAME] = trim( str_replace('[', '', $sgx[F_OVERDESTINATIONNAME]) );
            }

            # !ATTENTION! '!all' must be convert to 'none'
            $sgx[F_DESTINATIONNAME]     = str_replace("!all", "none", $sgx[F_DESTINATIONNAME]);
            $sgx[F_OVERDESTINATIONNAME] = str_replace("!all", "none", $sgx[F_OVERDESTINATIONNAME]);

            # if empty - adding 'none'
            if (!$sgx[F_DESTINATIONNAME])     $sgx[F_DESTINATIONNAME] = "none";
            if (!$sgx[F_OVERDESTINATIONNAME]) $sgx[F_OVERDESTINATIONNAME] = "none";

            # safesearch
           if ($pfx[SAFESEARCH] === 'on') {
               # assign safesearch rewrite
               $sgx[F_REWRITENAME] = SAFESEARCH;
               $sgx[F_OVERREWRITENAME] = SAFESEARCH;
           }

            $sgxml[F_ITEM][] = $sgx;
        }
    }
    return $sgxml;
}

# -----------------------------------------------------------------
# convert_pfxml_to_sgxml_default
# sgxml_acl: [name][desc][disabled][timename][destname][redirect][rewritename][over_redirect][over_rewritename]
# -----------------------------------------------------------------
function convert_pfxml_to_sgxml_default($pfconfig) {
    $pfxml = $pfconfig['installedpackages'][MODULE_DEFAULT]['config'];

    $pfx = $pfxml[0];
    $sgx = array();
    $sgx[F_NAME]        = 'default';
    $sgx[F_DESCRIPTION] = '';
    $sgx[F_DISABLED]    = '';
    $sgx[F_TIMENAME]    = $pfx[F_TIME];
    $sgx[F_RMOD]        = isset($pfx[F_RMOD]) ? $pfx[F_RMOD] : RMOD_INT_ERRORPAGE;
    $sgx[F_REDIRECT]    = $pfx[F_REDIRECT];
    $sgx[F_REWRITENAME] = $pfx[F_REWRITE];
    $sgx[F_LOG]         = $pfx[F_ENABLELOG];
    $sgx[F_NOTALLOWINGIP] = $pfx[F_NOTALLOWINGIP];

    # destinations
    if (strpos($pfx['dest'], '[') === false)
         $sgx[F_DESTINATIONNAME] = trim($pfx['dest']);
    else $sgx[F_DESTINATIONNAME] = trim( substr($pfx['dest'], 0, strpos($pfx['dest'], '[')) );

    # !ATTENTION! '!all' must be convert to 'none'
    $sgx[F_DESTINATIONNAME] = str_replace("!all", "none", $sgx[F_DESTINATIONNAME]);

    # if empty - adding 'none'
    if (!$sgx[F_DESTINATIONNAME]) $sgx[F_DESTINATIONNAME] = "none";

    # safesearch
    if ($pfx[SAFESEARCH] === 'on') {
        # assign safesearch rewrite
        $sgx[F_REWRITENAME] = SAFESEARCH;
    }

    return $sgx;
}

# =================================================================
# Capability
# =================================================================
# convert old ver. squidguard config.
function capability_update_source() {
	# ! use global var $config ONLY !
    global $config;
    $conf_changed = false;

    if (isset($config['installedpackages'][MODULE_ACL]['config'])) {
        $tconf = &$config['installedpackages'][MODULE_ACL]['config'];
        foreach($tconf as $key => $cfg) {
            if (isset($cfg['iplist'])) {
                $tconf[$key][F_SOURCE] .= " " . $cfg['iplist'];
                unset($tconf[$key]['iplist']);
                $conf_changed = true;
            }
            if (isset($cfg[F_DOMAINS])) {
                $tconf[$key][F_SOURCE] .= " " . $cfg[F_DOMAINS];
                unset($tconf[$key][F_DOMAINS]);
                $conf_changed = true;
            }
        }

        if ($conf_changed) write_config('Convert old ver. squidguard config.');
    }
}
# ------------------------------------------------------------------
# get_item_id - get item 'id' from get/post
# ------------------------------------------------------------------
function get_item_id()
{
    return  isset($_GET['id']) ? $_GET['id'] : $_POST['id'];
}

# ==================================================================
# additional
# ==================================================================
# safesearch rewrite
function squidguard_adt_rewrite_safesrch()
{
    $res = array();

    # safesearch
    $res[F_NAME] = SAFESEARCH;
    $res[F_DESCRIPTION] = "Google, Yandex safesearch";
    $res[F_LOG]         = 'on';
    squidguard_adt_safesrch_add($res[F_ITEM]);

    return $res;
}

function squidguard_adt_safesrch_add(&$rewrite_item)
{
    if (!is_array($rewrite_item)) $rewrite_item = array();

    # Google
    $rewrite_item[] = array(F_TARGETURL => '(google\..*/search?.*q=.*)', F_REPLACETO => '\1\&safe=active', F_MODE => 'i');
    $rewrite_item[] = array(F_TARGETURL => '(google\..*/images.*q=.*)',  F_REPLACETO => '\1\&safe=active', F_MODE => 'i');
    $rewrite_item[] = array(F_TARGETURL => '(google\..*/groups.*q=.*)',  F_REPLACETO => '\1\&safe=active', F_MODE => 'i');
    $rewrite_item[] = array(F_TARGETURL => '(google\..*/news.*q=.*)',    F_REPLACETO => '\1\&safe=active', F_MODE => 'i');

    # Yandex
    $rewrite_item[] = array(F_TARGETURL => '(yandex\..*/yandsearch?.*text=.*)', F_REPLACETO => '\1\&fyandex=1', F_MODE => 'i');

    # Yahoo
    $rewrite_item[] = array(F_TARGETURL => '(search\.yahoo\..*/search.*p=.*)', F_REPLACETO => '\1\&vm=r&v=1', F_MODE => 'i');

    # MSN Live search, Bing
    $rewrite_item[] = array(F_TARGETURL => '(search\.live\..*/.*q=.*)',  F_REPLACETO => '\1\&adlt=strict', F_MODE => 'i');
    $rewrite_item[] = array(F_TARGETURL => '(search\.msn\..*/.*q=.*)',   F_REPLACETO => '\1\&adlt=strict', F_MODE => 'i');
    $rewrite_item[] = array(F_TARGETURL => '(\.bing\..*/.*q=.*)',        F_REPLACETO => '\1\&adlt=strict', F_MODE => 'i');

    return $rewrite_item;
}

# log dump
function squidguard_logdump($filename, &$lnoffset, $lncount, $reverse)
{
    define('LOGSHOW_BUFSIZE', '262144');
    $cnt = '';

    if (file_exists($filename)) {
        $fh = fopen($filename, "r");
        if ($fh) {
            $fsize = filesize($filename);

            # take LOGSHOW_BUFSIZE bytes from end
            if ($fsize > LOGSHOW_BUFSIZE)
                   fseek($fh, -LOGSHOW_BUFSIZE, SEEK_END);
            $cnt = fread($fh,  LOGSHOW_BUFSIZE);

            fclose($fh);
        }
    }

    $cnt = explode( "\n", $cnt );

    # delete broken first element
    array_shift($cnt);

    # offset must be >= 0 and can't be > count($cnt)
    $lnoffset = $lnoffset >= 0 ? $lnoffset : 0;
    $lnoffset = ($lnoffset + $lncount) < count($cnt) ? $lnoffset : 0;

    $pos = ($lncount + $lnoffset);

    # take elements from end of array
    $cnt = array_slice($cnt, -$pos, $lncount);

    # reverse array order
    if ($reverse) $cnt = array_reverse( $cnt );

    return $cnt;
}

# dump SG log
function squidguard_filterdump($lnoffset, $lncount, $reverse)
{
    $res  = array();
    $cont = squidguard_logdump(SQUIDGUARD_LOGDIR . '/squidGuard.log', $lnoffset, $lncount, $reverse);

    foreach($cont as $cn) {
        $cn = explode(" ", trim($cn), 4);
        $res[] = array( "{$cn[0]} {$cn[1]}", $cn[3] );
    }

    return $res;
}

# dump SG Gui log
function squidguard_guidump($lnoffset, $lncount, $reverse)
{
    $res  = array();
    $cont = squidguard_logdump(SQUIDGUARD_LOGDIR . SQUIDGUARD_CONFLOGFILE, $lnoffset, $lncount, $reverse);

    foreach($cont as $cn) {
        $cn = explode(" ", trim($cn), 4);
        $res[] = array( "{$cn[0]} {$cn[1]}", $cn[3] );
    }

    return $res;
}

# dump SG blocked
function squidguard_blockdump($lnoffset, $lncount, $reverse)
{
    $res  = array();
    $cont = squidguard_logdump(SQUIDGUARD_LOGDIR . '/' . SQUIDGUARD_LOGFILE, $lnoffset, $lncount, $reverse);

    foreach($cont as $cn) {
        $cn = explode(" ", trim($cn), 9);
        $res[] = array( "{$cn[0]} {$cn[1]}", $cn[5], $cn[4], "{$cn[3]} {$cn[6]} {$cn[7]} {$cn[8]}");
    }

    return $res;
}

# get squid config list
function squidguard_squid_conflist( )
{
    $fname = SQUID_CONFIGFILE;
    $res   = "";

    if (file_exists( $fname ))
         $res = file_get_contents( $fname );
    else $res = "File '$fname' not found.";

    return $res;
}

# get squidguard config list
function squidguard_conflist( )
{
    $fname = SQUIDGUARD_CONFBASE . SQUIDGUARD_CONFIGFILE;
    $res   = "";

    if (file_exists( $fname ))
         $res = file_get_contents( $fname );
    else $res = "File '$fname' not found.";

    return $res;
}

# get blacklist list
function squidguard_blacklist_list()
{
    $res   = "";
    $fname = SQUIDGUARD_BLK_FILELISTPATH;

    $res .= "<table class='tabcont' width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
    $res .= "<tr><td class='listtopic'>Name</td><td class='listtopic'>Domains</td><td class='listtopic'>Urls</td><td class='listtopic'>Expressions</td></tr>\n";
    if (file_exists($fname)) {
        $cont = explode("\n", file_get_contents($fname));
        foreach($cont as $cn) {
            $ph = SQUIDGUARD_DBHOME . "/{$cn}";

            if (file_exists($ph)) {
                $dm = "&nbsp;";
                $ur = "&nbsp;";
                $ex = "&nbsp;";

                if (file_exists("{$ph}/domains"))     $dm = "domains";
                if (file_exists("{$ph}/urls"))        $ur = "urls";
                if (file_exists("{$ph}/expressions")) $ex = "expressions";

                $res .= "<tr><td class='listlr'>{$cn}</td><td class='listr'>{$dm}</td><td class='listr'>{$ur}</td><td class='listr'>{$ex}</td></tr>";
            }
        }
    }
    $res .= "</table>";

    return $res;
}

/* Uses XMLRPC to synchronize the changes to a remote node */
function squidguard_sync_on_changes() {
	global $config, $g;

	if (is_array($config['installedpackages']['squidguardsync'])) {
		$synconchanges = $config['installedpackages']['squidguardsync']['config'][0]['varsyncenablexmlrpc'];
		$varsynctimeout = $config['installedpackages']['squidguardsync']['config'][0]['varsynctimeout'] ?: '150';
	} else {
		return;
	}

	switch ($synconchanges) {
		case "manual":
			if (is_array($config['installedpackages']['squidguardsync']['config'][0]['row'])) {
				$rs = $config['installedpackages']['squidguardsync']['config'][0]['row'];
			} else {
				log_error("[Squidguard]  XMLRPC sync is enabled but there are no hosts configured as replication targets.");
				return;
			}
			break;
		case "auto":
			if (is_array($config['hasync'])) {
				$system_carp = $config['hasync'];
				$rs[0]['varsyncipaddress'] = $system_carp['synchronizetoip'];
				$rs[0]['varsyncusername'] = $system_carp['username'];
				$rs[0]['varsyncpassword'] = $system_carp['password'];
				$rs[0]['varsyncdestinenable'] = FALSE;

				// XMLRPC sync is currently only supported over connections using the same protocol and port as this system
				if ($config['system']['webgui']['protocol'] == 'http') {
					$rs[0]['varsyncprotocol'] = 'http';
					$rs[0]['varsyncport'] = $config['system']['webgui']['port'] ?: '80';
				} else {
					$rs[0]['varsyncprotocol'] = 'https';
					$rs[0]['varsyncport'] = $config['system']['webgui']['port'] ?: '443';
				}
				if ($system_carp['synchronizetoip'] == "") {
					log_error("[Squidguard] XMLRPC CARP/HA sync is enabled but there are no system backup hosts configured as replication targets.");
					return;
				} else {
					$rs[0]['varsyncdestinenable'] = TRUE;
				}
			} else {
				log_error("[Squidguard] XMLRPC CARP/HA sync is enabled but there are no system backup hosts configured as replication targets.");
				return;
			}
			break;
		default:
			return;
			break;
	}
	if (is_array($rs)) {
		log_error("[SquidGuard] XMLRPC sync is starting with timeout {$varsynctimeout} seconds.");
		foreach ($rs as $sh) {
			// Only sync enabled replication targets
			if ($sh['varsyncdestinenable']) {
				$varsyncprotocol = $sh['varsyncprotocol'];
				$sync_to_ip = $sh['varsyncipaddress'];
				$username = $sh['varsyncusername'] ?: 'admin';
				$password = $sh['varsyncpassword'];
				$varsyncport = $sh['varsyncport'];

				$error = '';
				$valid = TRUE;

				if ($password == "") {
					$error = "Password parameter is empty. ";
					$valid = FALSE;
				}
				if (!is_ipaddr($sync_to_ip) && !is_hostname($sync_to_ip) && !is_domain($sync_to_ip)) {
					$error .= "Misconfigured Replication Target IP Address or Hostname. ";
					$valid = FALSE;
				}
				if (!is_port($varsyncport)) {
					$error .= "Misconfigured Replication Target Port. ";
					$valid = FALSE;
				}
				if ($valid) {
					squidguard_do_xmlrpc_sync($sync_to_ip, $varsyncport, $varsyncprotocol, $username, $password, $varsynctimeout);
				} else {
					log_error("[SquidGuard] XMLRPC sync with '{$sync_to_ip}' aborted due to the following error(s): {$error}");
				}
			} else {
				log_error("[SquidGuard] XMLRPC Sync with {$sh['varsyncipaddress']} is disabled");
			}
		}
		log_error("[SquidGuard] XMLRPC sync is ending.");
	}
}

if(!function_exists('pf_version')) {
	function pf_version() {
		return substr(trim(file_get_contents("/etc/version")), 0, 3);
	}
}

/* Do the actual XMLRPC sync */
function squidguard_do_xmlrpc_sync($sync_to_ip, $varsyncport, $varsyncprotocol, $username, $password, $varsynctimeout) {
	global $config, $g;

	if ($username == "" || $password == "" || $sync_to_ip == "" || $varsyncport == "" || $varsyncprotocol == "") {
		log_error("[SquidGuard] A required XMLRPC sync parameter (username, password, replication target, port or protocol) is empty ... aborting pkg sync");
		return;
	}

	/* XML will hold the sections to sync. */
	$xml = array();
	$xml['squidguardgeneral'] = $config['installedpackages']['squidguardgeneral'];
	$xml['squidguardacl'] = $config['installedpackages']['squidguardacl'];
	$xml['squidguarddefault'] = $config['installedpackages']['squidguarddefault'];
	$xml['squidguarddest'] = $config['installedpackages']['squidguarddest'];
	$xml['squidguardrewrite'] = $config['installedpackages']['squidguardrewrite'];
	$xml['squidguardtime'] = $config['installedpackages']['squidguardtime'];

	$execcmd  = "require_once('/usr/local/pkg/squidguard.inc');\n";
	$execcmd .= "squidguard_all_after_XMLRPC_resync();";

	if (pf_version() >= "2.4") {
		// xmlrpc cannot encode NULL objects/arrays..
		foreach($xml as $xmlkey => $xmlvalue) {
			if (gettype($xmlvalue) == "NULL") {
				$xml[$xmlkey] = array();
			}
		}
		$synctimeout = intval($synctimeout);
		$rpc_client = new pfsense_xmlrpc_client();
		$rpc_client->setConnectionData($sync_to_ip, $varsyncport, $username, $password, $varsyncprotocol);
		$resp = $rpc_client->xmlrpc_method('merge_installedpackages_section', $xml, $synctimeout);
		$resp = $rpc_client->xmlrpc_exec_php($execcmd, $synctimeout);
	} else {
		// pfSense before 2.4
		require_once('xmlrpc.inc');

		// Take care of IPv6 literal address
		if (is_ipaddrv6($sync_to_ip)) {
			$sync_to_ip = "[{$sync_to_ip}]";
		}

		$url = "{$varsyncprotocol}://{$sync_to_ip}";
		$port = $varsyncport;
		/* Assemble XMLRPC payload. */
		$params = array(XML_RPC_encode($password), XML_RPC_encode($xml));

		/* Set a few variables needed for sync code */
		log_error("[SquidGuard] Beginning XMLRPC sync with {$url}:{$port}.");
		$method = 'pfsense.merge_installedpackages_section_xmlrpc';
		$msg = new XML_RPC_Message($method, $params);
		$cli = new XML_RPC_Client('/xmlrpc.php', $url, $port);
		$cli->setCredentials($username, $password);
		if ($g['debug']) {
			$cli->setDebug(1);
		}
		/* Send our XMLRPC message and timeout after $varsynctimeout seconds */
		$resp = $cli->send($msg, $varsynctimeout);
		if (!$resp) {
			$error = "A communications error occurred while attempting XMLRPC sync with {$url}:{$port}.";
			log_error("[SquidGuard] {$error}");
			file_notice("sync_settings", $error, "squidguard Settings Sync", "");
		} elseif ($resp->faultCode()) {
			$cli->setDebug(1);
			$resp = $cli->send($msg, $varsynctimeout);
			$error = "An error code was received while attempting XMLRPC sync with {$url}:{$port} - Code " . $resp->faultCode() . ": " . $resp->faultString();
			log_error("[SquidGuard] {$error}");
			file_notice("sync_settings", $error, "squidguard Settings Sync", "");
		} else {
			log_error("[SquidGuard] XMLRPC sync successfully completed with {$url}:{$port}.");
		}

		/* Tell Squidguard to reload our settings on the destionation sync host. */
		$method = 'pfsense.exec_php';

		/* Assemble XMLRPC payload. */
		$params = array(XML_RPC_encode($password), XML_RPC_encode($execcmd));

		log_error("[SquidGuard] XMLRPC is reloading data on {$url}:{$port}.");
		$msg = new XML_RPC_Message($method, $params);
		$cli = new XML_RPC_Client('/xmlrpc.php', $url, $port);
		$cli->setCredentials($username, $password);
		$resp = $cli->send($msg, $varsynctimeout);
		if (!$resp) {
			$error = "A communications error occurred while attempting XMLRPC sync with {$url}:{$port} (exec_php).";
			log_error("[SquidGuard] {$error}");
			file_notice("sync_settings", $error, "squidguard Settings Sync", "");
		} elseif ($resp->faultCode()) {
			$cli->setDebug(1);
			$resp = $cli->send($msg, $varsynctimeout);
			$error = "An error code was received while attempting XMLRPC sync with {$url}:{$port} - Code " . $resp->faultCode() . ": " . $resp->faultString();
			log_error("[SquidGuard] {$error}");
			file_notice("sync_settings", $error, "squidguard Settings Sync", "");
		} else {
			log_error("[SquidGuard] XMLRPC has reloaded data successfully on {$url}:{$port} (exec_php).");
		}
	}
}

// This function restarts all other needed functions after XMLRPC sync so that the content of .XML + .INC will be written in the files
// Adding more functions will increase the time to sync
function squidguard_all_after_XMLRPC_resync() {

	squidguard_resync_acl();
	squidguard_resync_dest();
	squidguard_resync();

	log_error("[SquidGuard] Finished XMLRPC process. It should be OK. For more information look at the host which started sync.");
}

?>
