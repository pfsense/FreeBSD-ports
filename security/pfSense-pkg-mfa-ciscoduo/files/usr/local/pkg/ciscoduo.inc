<?php
require_once("globals.inc");
require_once("config.inc");

function ciscoduo_install_command() {
    global $config;

    if (isset($config['installedpackages']['menu']) && is_array($config['installedpackages']['menu'])) {
        foreach ($config['installedpackages']['menu'] as $key => $menu) {
            if ($menu['name'] == 'Cisco Duo MFA') {
                unset($config['installedpackages']['menu'][$key]);
            }
        }
    }

    $config['installedpackages']['menu'][] = [
        'name' => 'Cisco Duo MFA',
        'section' => 'Services',
        'configfile' => 'ciscoduo.xml',
        'url' => '/pkg_edit.php?xml=ciscoduo.xml',
        'tooltiptext' => 'Cisco Duo MFA Settings'
    ];
    $config['installedpackages']['menu'] = array_values($config['installedpackages']['menu']);

    if (isset($config['mfa_providers']['ciscoduo'])) {
        unset($config['mfa_providers']['ciscoduo']);
    }

    ciscoduo_sync();
    write_config("Cisco Duo MFA: Package installation completed.");
}

function ciscoduo_uninstall_command() {
    global $config;
    $changed = false;

    if (isset($config['installedpackages']['menu']) && is_array($config['installedpackages']['menu'])) {
        foreach ($config['installedpackages']['menu'] as $key => $menu) {
            if (isset($menu['name']) && $menu['name'] == 'Cisco Duo MFA') {
                unset($config['installedpackages']['menu'][$key]);
                $changed = true;
            }
        }
        if ($changed) {
            $config['installedpackages']['menu'] = array_values($config['installedpackages']['menu']);
        }
    }

    if (isset($config['installedpackages']['ciscoduo'])) {
        unset($config['installedpackages']['ciscoduo']);
        $changed = true;
    }

    if (isset($config['mfa_providers']['ciscoduo'])) {
        unset($config['mfa_providers']['ciscoduo']);
        $changed = true;
    }

    if (isset($config['openvpn']['openvpn-server']) && is_array($config['openvpn']['openvpn-server'])) {
        foreach ($config['openvpn']['openvpn-server'] as &$server) {
            if (isset($server['mfa_enabled']) && $server['mfa_provider'] === 'ciscoduo') {
                unset($server['mfa_enabled']);
                unset($server['mfa_provider']);
                $changed = true;
            }
        }
    }

    if ($changed) {
        write_config("Cisco Duo MFA: Package uninstalled and config cleaned.");
    }
}

function ciscoduo_sync() {
    global $config;

    $duo_conf = isset($config['installedpackages']['ciscoduo']['config'][0]) ? $config['installedpackages']['ciscoduo']['config'][0] : array();
    $is_enabled = (isset($duo_conf['duo_enable']) && $duo_conf['duo_enable'] === 'on');

    if (!isset($config['mfa_providers']) || !is_array($config['mfa_providers'])) {
        $config['mfa_providers'] = array();
    }

    if ($is_enabled) {
        $config['mfa_providers']['ciscoduo'] = array(
            'name' => 'Cisco Duo MFA',
            'include_file' => '/usr/local/pkg/ciscoduo.inc',
            'function' => 'duo_execute_mfa'
        );
    } else {
        if (isset($config['mfa_providers']['ciscoduo'])) {
            unset($config['mfa_providers']['ciscoduo']);
        }
    }
    write_config("Cisco Duo MFA: Sync provider registration");
}


function ciscoduo_test($post, &$input_errors) {
    $test_user = trim($post['test_username']);

    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $result = duo_execute_mfa($test_user, "pfSense GUI Test", $client_ip);
    
    if(!empty($test_user)){
        if ($result) {
        $msg = urlencode("TEST SUCCESSFUL: Authentication for user '{$test_user}' was successful.");
            pfSenseHeader("pkg_edit.php?xml=ciscoduo_test.xml&savemsg={$msg}");
            exit;
        } else {
            $input_errors[] = "TEST FAILED: Authentication for user '{$test_user}' failed.";
        }
    } else {
        $input_errors[] = "Error: Please enter a username to test.";
    }
}

function ciscoduo_validate_input($post, &$input_errors) {
    global $config;

    if (isset($post['duo_timeout'])) {
        $t = $post['duo_timeout'];
        if (!is_numeric($t) || $t < 15 || $t > 55) {
            $input_errors[] = "Error: Push Timeout has to be a numeric value between 15 and 55 seconds.";
        }
    }

    $disabling = empty($post['duo_enable']);

    if ($disabling) {
        $blocking_servers = [];

        if (isset($config['openvpn']['openvpn-server']) && is_array($config['openvpn']['openvpn-server'])) {
            foreach ($config['openvpn']['openvpn-server'] as $server) {
                if (isset($server['mfa_enabled']) && $server['mfa_provider'] === 'ciscoduo') {
                    $server_name = !empty($server['description']) ? $server['description'] : "ID: " . $server['vpnid'];
                    $blocking_servers[] = "'{$server_name}'";
                }
            }
        }

        if (!empty($blocking_servers)) {
            $server_list = implode(', ', $blocking_servers);
            $input_errors[] = "Cisco Duo cannot be globally deactivated while 2FA is active on the following OpenVPN server(s): {$server_list}.";
            return;
        }
    }

    if (isset($post['duo_enable']) && ($post['duo_enable']==='on')) {
        $ikey = trim($post['duo_ikey']);
        $skey = trim($post['duo_skey']);
        $host = trim($post['duo_host']);
        
        if (empty($ikey) || empty($skey) || empty($host)) {
            $input_errors[] = "Error: All Cisco Duo API fields must be filled before enabling MFA.";
            return;
        }

        require_once("/usr/local/pkg/ciscoduo.inc");
        $check_result = duo_api_call('GET', $host, '/auth/v2/check', array(), $ikey, $skey, 5);

        if (!$check_result || !isset($check_result['stat'])) {
            $input_errors[] = "Error: Cannot connect to Cisco Duo API. Check the API Hostname and firewall rules.";
        } elseif ($check_result['stat'] !== 'OK') {
            $msg = isset($check_result['message']) ? $check_result['message'] : "Invalid credentials";
            $input_errors[] = "Error: {$msg}.";
        }
    }
}


function duo_api_call($method, $host, $path, $params, $ikey, $skey, $curl_timeout) {
    $date = date("r"); 
    ksort($params);
    $args = array();
    foreach ($params as $key => $val) {
        $args[] = rawurlencode($key). '='. rawurlencode($val);
    }
    $args_string = implode('&', $args);
    
    $canon = $date. "\n". strtoupper($method). "\n". strtolower($host). "\n". $path. "\n". $args_string;
    $sig = hash_hmac("sha1", $canon, $skey);
    $auth = base64_encode($ikey. ':'. $sig);

    $headers = array(
        "Date: $date",
        "Authorization: Basic $auth",
        "Content-Type: application/x-www-form-urlencoded"
    );

    $url = "https://". $host. $path;
    if ($method == 'GET') {
        $url.= "?". $args_string;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, $curl_timeout); 

    curl_setopt($ch, CURLOPT_CAINFO, "/usr/local/pkg/duo_root_ca.pem"); // z githubu Cisco Duo openvpn
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    if ($method == 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $args_string);
    }

    $result = curl_exec($ch);
    if ($result === false) {
        $curl_error = curl_error($ch);
        syslog(LOG_ERR, "OpenVPN Duo cURL CRITICAL ERROR: " . $curl_error);
    }
    curl_close($ch); 
    return json_decode($result, true);
}


function duo_execute_mfa($username, $service_name, $client_ip="") {
    global $config;

    if (!isset($config['installedpackages']['ciscoduo']['config'][0])) {
        syslog(LOG_ERR, "OpenVPN Duo: Package Cisco Duo is not configured.");
        return false;
    }

    $pkg_conf = $config['installedpackages']['ciscoduo']['config'][0];
    $ikey = $pkg_conf['duo_ikey'];
    $skey = base64_decode($pkg_conf['duo_skey']);
    $host = $pkg_conf['duo_host'];

    $raw_timeout = (isset($pkg_conf['duo_timeout']) && is_numeric($pkg_conf['duo_timeout'])) ? (int)$pkg_conf['duo_timeout'] : 45;
    $timeout = max(15, min(55, $raw_timeout));//cisco duo push max 60s

    if ($ikey && $skey && $host) {
        set_time_limit($timeout + 5);

        $api_params = [
            'username' => $username,
            'factor'   => 'push',
            'device'   => 'auto',
            'async'    => '1',
            'hostname' => $config['system']['hostname']
        ];

        if (!empty($client_ip)) {
            $api_params['ipaddr'] = $client_ip;
        }

        $api_params['type'] = $service_name;

        $resp = duo_api_call('POST', $host, '/auth/v2/auth', $api_params, $ikey, $skey, 10);

        if ($resp['stat'] === 'OK' && isset($resp['response']['txid'])) {
            $txid = $resp['response']['txid'];
            $start_time = time();
            
            while (time() - $start_time < $timeout) { 
                
                sleep(2);

                $elapsed = time() - $start_time;
                $time_left = $timeout - $elapsed;
                if ($time_left <= 0) {
                    break;
                }
                
                $status = duo_api_call('GET', $host, '/auth/v2/auth_status', ['txid' => $txid], $ikey, $skey, $time_left);
                
                if ($status['stat'] === 'OK') {
                    if ($status['response']['result'] === 'allow') {
                        syslog(LOG_NOTICE, "OpenVPN Duo: User '$username' APPROVED request. (IP: $client_ip)");
                        return true;
                    } else if ($status['response']['result'] === 'deny') {
                        syslog(LOG_WARNING, "OpenVPN Duo: User '$username' DENIED request. (IP: $client_ip)");
                        return false;
                    }
                }
            }
            syslog(LOG_WARNING, "OpenVPN Duo: User '$username' timed out.");
        } else {
            syslog(LOG_ERR, "OpenVPN Duo: API Error or No TXID.");
        }
    } else {
        syslog(LOG_ERR, "OpenVPN Duo: Required keys are not set in the Cisco Duo package configuration.");
    }
    return false; 
}
?>