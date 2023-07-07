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
require_once("util.inc");
require_once("pkg-utils.inc");

function crowdsec_install() {
}

function crowdsec_write_config() {
}

function crowdsec_generate_rules($type) {
    log_warn("crowdsec_generate_rules: $type");
    $rules = "";
    switch ($type) {
        case 'filter':
            $rules .= "block in log quick on wan from crowdsec_blacklist to any keep state label\n";
            $rules .= "block in log quick on wan from crowdsec6_blacklist to any keep state label\n";
            break;
    }
    log.warn("crowdsec_generate_rules: $rules");

    return $rules;
}

?>
