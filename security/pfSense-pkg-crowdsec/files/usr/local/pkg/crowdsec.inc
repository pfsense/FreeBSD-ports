<?php
/*
 * crowdsec.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2020-2023 Crowdsec
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

require_once("config.inc");
require_once("globals.inc");
require_once("config.lib.inc");
require_once("filter.inc");

$g['disablehelpicon'] = true;

function crowdsec_validate_form($post, &$input_errors)
{
    if (!empty($post['lapi_port'])) {
        $lapi_port = $post['lapi_port'];
        if (!is_numeric($lapi_port)) {
            $input_errors[] = 'LAPI port must be a numeric value.';
        }
    }

    // Use external LAPI
    if (!empty($post['lapi_is_remote'])) {
        if (empty($post['agent_user'])) {
            $input_errors[] = 'Agent user is required for external LAPI usage.';
        }
        if (empty($post['agent_password'])) {
            $input_errors[] = 'Agent password is required for external LAPI usage.';
        }
        if (empty($post['fw_bouncer_api_key'])) {
            $input_errors[] = 'Firewall bouncer API key is required for external LAPI usage.';
        }
    }
}

function crowdsec_install()
{
}

function crowdsec_sync_config()
{
    global $config;

    #########################################
    #	UPDATE pfSense ALIAS TABLES	#
    #########################################
    $crowdsec_aliases = array(
        array(
            'name' => 'crowdsec_blacklists',
            'address' => "",
            'descr' => 'Crowdsec blacklists IPv4 (DO NOT EDIT)',
            'type' => 'host',
            'detail' => ''
        ),
        array(
            'name' => 'crowdsec6_blacklists',
            'address' => "",
            'descr' => 'Crowdsec blacklists IPv6 (DO NOT EDIT)',
            'type' => 'host',
            'detail' => ''
        ),

    );
    $config = parse_config(true);
    config_init_path(implode('/', ['aliases']));
    $exist_aliases = config_get_path('aliases/alias', []);

    $exist_aliases_names = array_column($exist_aliases, 'name');

    $final_aliases = $exist_aliases;

    // Add Crowdsec alias if not exist
    foreach ($crowdsec_aliases as $crowdsec_alias){
            if(!in_array($crowdsec_alias['name'], $exist_aliases_names)){
                $final_aliases[] = $crowdsec_alias;
            }
    }
    // Update config.xml, if changes required
    if ($exist_aliases !== $final_aliases) {
        config_set_path('aliases/alias', $final_aliases);
        write_config('pfsense_crowdsec: saving Aliases');
    }
    unset($final_aliases, $exist_aliases);
    #########################################
    #	UPDATE pfSense RULES TABLES	#
    #########################################
    $currentTime = (int)microtime(true);
    $crowdsec_rules = array(
        array(
            'tracker' => $currentTime,
            'type' => 'block',
            'ipprotocol' => 'inet',
            'direction' => 'any',
            'floating' => 'yes',
            'interface' => 'any',
            'statetype' => 'keep state',
            'source' => array('address' => $crowdsec_aliases[0]['name']),//crowdsec_blacklists
            'destination' => array('any' => ''),
            'descr' => 'Crowdsec IPv4 (DO NOT EDIT)',
            'created' => array('time' => $currentTime, 'username' => 'Auto pfsense_crowdsec')
        ),
        array(
            'tracker' => $currentTime+1,
            'type' => 'block',
            'ipprotocol' => 'inet6',
            'direction' => 'any',
            'floating' => 'yes',
            'interface' => 'any',
            'statetype' => 'keep state',
            'source' => array('address' => $crowdsec_aliases[1]['name']),//crowdsec6_blacklists
            'destination' => array('any' => ''),
            'descr' => 'Crowdsec IPv6 (DO NOT EDIT)',
            'created' => array('time' => $currentTime, 'username' => 'Auto pfsense_crowdsec')
        ),

    );
    // Reload config.xml to get any recent changes
    $config = parse_config(true);
    // Collect all existing rules
    config_init_path(implode('/', array('filter', 'rule')));
    $exist_rules = config_get_path('filter/rule', []);
    $exist_rules_source_address = array();
    foreach ($exist_rules as $exist_rule){
        if(!empty($exist_rule['source']['address'])){
            $exist_rules_source_address[] = $exist_rule['source']['address'];
        }
    }
    $final_rules = $exist_rules;
    // Add Crowdsec rule if not exist
    foreach ($crowdsec_rules as $crowdsec_rule){
        if(!in_array($crowdsec_rule['source']['address'], $exist_rules_source_address)) {
            $final_rules[] = $crowdsec_rule;
        }
    }
    // Update config.xml, if changes required
    if ($final_rules != $exist_rules) {
        config_set_path('filter/rule', $final_rules);
        write_config('pfsense_crowdsec: saving Rules');
        // Reload filter
        filter_configure();
    }
    unset($final_rules, $exist_rules);
}

