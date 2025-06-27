<?php
/*
 * netbird_auth.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2022-2025 Rubicon Communications, LLC (Netgate)
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


require_once('netbird/netbird.inc');


function netbird_auth_apply_config()
{
    if (!empty($_POST['disconnect'])) {
        netbird_disconnect();
        return;
    }

    if (netbird_is_connected() && !netbird_disconnect()) {
        return;
    }

    $cmd = sprintf(
        '%s up -m %s -k %s',
        escapeshellcmd(NETBIRD_BIN),
        escapeshellarg($_POST['managementurl']),
        escapeshellarg($_POST['setupkey'])
    );
    exec($cmd);
}


function netbird_auth_validate_input($post, &$input_errors)
{
    if (!empty($post['disconnect'])) {
        return;
    }

    $managementurl = $post['managementurl'];
    $setupkey = $post['setupkey'];

    if (!empty($managementurl) && !is_URL($managementurl)) {
        $input_errors[] = sprintf(gettext('Management URL (%s) is not a valid URL.'), $managementurl);
    }
    if (empty($setupkey)) {
        $input_errors[] = gettext('Setup Key is required.');
    }
}

?>

