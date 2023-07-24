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
require_once("/usr/local/pkg/crowdsec/vendor/autoload.php");

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
    crowdsec_generate_rules('filter');
    filter_configure();
}

function crowdsec_sync_config()
{
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
    parse_config(true);
    config_init_path(implode('/', ['aliases']));
    $exist_aliases = config_get_path('aliases/alias', []);

    $exist_aliases_names = array_column($exist_aliases, 'name');

    $final_aliases = $exist_aliases;

    // Add Crowdsec alias if not exist
    foreach ($crowdsec_aliases as $crowdsec_alias) {
        if (!in_array($crowdsec_alias['name'], $exist_aliases_names)) {
            $final_aliases[] = $crowdsec_alias;
        }
    }
    // Update config.xml, if changes required
    if ($exist_aliases !== $final_aliases) {
        config_set_path('aliases/alias', $final_aliases);
        write_config('pfsense_crowdsec: saving Aliases');
    }
    unset($final_aliases, $exist_aliases);
    // Generate rules depending on settings
    crowdsec_generate_rules('filter');
    // Reload filter
    filter_configure();
}

function crowdsec_generate_rules($type)
{
    $rules = "";
    switch ($type) {
        case 'filter':
            global $config;
            $cf = $config['installedpackages']['crowdsec']['config'][0] ?? array();

            if(!empty($cf['enable_block_v4'])){
                $rules .= "block drop";
                $direction = !empty($cf['block_v4_direction']) && $cf['block_v4_direction'] !== 'any'? $cf['block_v4_direction'] : '';
                $log = !empty($cf['block_v4_log']) ? "log" : '';
                $tag = !empty($cf['block_v4_tag']) ? "tag ". $cf['block_v4_tag'] : '';

                $rules .= ' ' . $direction . ' ' . $log . ' quick inet from <crowdsec_blacklists> to any label "Crowdsec IPv4"';
                if(!empty($tag)){
                    $rules .= ' '. $tag;
                }
                $rules .= "\n";
            }
            if(!empty($cf['enable_block_v6'])){
                $rules .= "block drop";
                $direction = !empty($cf['block_v6_direction']) && $cf['block_v6_direction'] !== 'any'? $cf['block_v6_direction'] : '';
                $log = !empty($cf['block_v6_log']) ? "log" : '';
                $tag = !empty($cf['block_v6_tag']) ? "tag ". $cf['block_v6_tag'] : '';

                $rules .= ' ' . $direction . ' ' . $log  .' quick inet6 from <crowdsec6_blacklists> to any label "Crowdsec IPv6"';
                if(!empty($tag)){
                    $rules .= ' '. $tag;
                }
                $rules .= "\n";
            }
            break;
    }

    return $rules;
}

