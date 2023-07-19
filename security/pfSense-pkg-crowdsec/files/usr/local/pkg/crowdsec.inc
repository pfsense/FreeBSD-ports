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



function crowdsec_validate_form($post, &$input_errors) {

    if (!empty($post['lapi_port'])) {
        $lapi_port = $post['lapi_port'];
        if(!is_numeric($lapi_port)){
            $input_errors[] = 'LAPI port must be a numeric value.';
        }
    } else {
        $input_errors[] = 'LAPI port can not be empty.';
    }

    if (empty($post['lapi_url'])) {
        $input_errors[] = 'LAPI url can not be empty.';
    }

    // Use external LAPI
    if(!empty($post['lapi_is_remote'])){
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

function crowdsec_write_config()
{

}

function crowdsec_generate_rules($type)
{
    $rules = "";
    switch ($type) {
        case 'filter':
            $rules .= "block in log quick on wan from crowdsec_blacklist to any keep state label\n";
            $rules .= "block in log quick on wan from crowdsec6_blacklist to any keep state label\n";
            break;
    }

    return $rules;
}

