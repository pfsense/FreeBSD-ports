<?php
/*
 * crowdsec.inc
 *
 * part of pfSense (https://www.pfSense.org/)
 * Copyright (c) 2023 CrowdSec
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
require_once("interfaces.inc");
require_once("util.inc");
require_once("/usr/local/pkg/crowdsec/vendor/autoload.php");

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Exception\DumpException;

define('CROWDSEC_LAPI_CREDENTIALS', '/usr/local/etc/crowdsec/local_api_credentials.yaml');
define('CROWDSEC_CONF', '/usr/local/etc/crowdsec/config.yaml');
define('CROWDSEC_FIREWALL_CONF', '/usr/local/etc/crowdsec/bouncers/crowdsec-firewall-bouncer.yaml');
define('CROWDSEC_PFSENSE_COLLECTION', '/usr/local/etc/crowdsec/collections/pfsense.yaml');

$g['disablehelpicon'] = true;

global $crowdsec_aliases;
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


/**
 * update the XML configuration: convert old options, drop deprecated, etc.
 *
 * @return void
 */
function crowdsec_config_migrate()
{
    parse_config(true);
    $cf = config_get_path('installedpackages/crowdsec/config/0', []);

    // noop on fresh install
    if (!$cf) {
        return;
    }

    // for backward compatibility, convert remote_lapi_host and remote_lapi_port to remote_lapi_url
    if (empty($cf['remote_lapi_url']) && !empty($cf['remote_lapi_host'])) {
        $cf['remote_lapi_url'] = 'http://' . $cf['remote_lapi_host'] . ':' . $cf['remote_lapi_port'];
        unset($cf['remote_lapi_host']);
        unset($cf['remote_lapi_port']);
        config_set_path('installedpackages/crowdsec/config/0', $cf);
    }
    write_config('pfsense_crowdsec: configuration updated.');
}

/**
 * custom_php_validation_command hook (setting edition before submit)
 *
 * @param $post
 * @param $input_errors
 * @return void
 */
function crowdsec_validate_form($post, &$input_errors)
{
    if (!empty($post['metrics_port'])) {
        $metrics_port = $post['metrics_port'];
        if (!is_numeric($metrics_port)) {
            $input_errors[] = 'Metrics port (crowdsec) must be a numeric value.';
        }
    }
    // Use external LAPI
    if (empty($post['enable_lapi'])) {
        if (empty($post['remote_lapi_url'])) {
            $input_errors[] = 'An URL is required for remote LAPI.';
        } else {
            if (filter_var($post['remote_lapi_url'], FILTER_VALIDATE_URL) === FALSE) {
                $input_errors[] = 'Remote LAPI URL is not valid.';
            }
        }
        if (empty($post['remote_agent_user'])) {
            $input_errors[] = 'Agent user is required for external LAPI usage.';
        }
        if (empty($post['remote_agent_password'])) {
            $input_errors[] = 'Agent password is required for external LAPI usage.';
        }
        if (empty($post['remote_fw_bouncer_api_key'])) {
            $input_errors[] = 'Firewall bouncer API key is required for external LAPI usage.';
        }
    } else {
        // Use Local API
        if (empty($post['lapi_port'])) {
            $input_errors[] = 'LAPI port is required for LAPI.';
        } else {
            $lapi_port = $post['lapi_port'];
            if (!is_numeric($lapi_port)) {
                $input_errors[] = 'LAPI port must be a numeric value.';
            }
        }
        if (empty($post['lapi_host'])) {
            $input_errors[] = 'LAPI host is required for LAPI.';
        }
    }
    // Apply rules on specifics interfaces
    if (empty($post['rules_all_interfaces'])) {
        if (empty($post['rules_interfaces_select'])) {
            $input_errors[] = 'Please select at least one interface for rules.';
        }
    }
    // Validate tags
    if ($post['rules_tag'] && !is_validaliasname($post['rules_tag'])) {
        $input_errors[] = gettext("Invalid tag value.");
    }
}

/**
 * custom_php_install_command hook (package installation)
 *
 * @return void
 */
function crowdsec_install()
{
    crowdsec_config_migrate();
    $candidate_log_dir = '/var/log/crowdsec/';
    $candidate_log_max_size = 999999;
    global $crowdsec_aliases;
    // Prepare rc.conf.d variables
    mwexec('sysrc -f /usr/local/etc/rc.conf.d/crowdsec crowdsec_machine_name=pfsense');
    mwexec('sysrc -f /usr/local/etc/rc.conf.d/crowdsec_firewall crowdsec_firewall_name=pfsense-firewall');
    // UPDATE pfSense ALIAS TABLES
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
    // Update rules
    crowdsec_generate_rules('pfearly');
    filter_configure();
    // Modify bouncer yaml config
    $fw_conf = get_yaml_content(CROWDSEC_FIREWALL_CONF);
    if (!empty($fw_conf)) {
        $fw_conf['blacklists_ipv4'] = $crowdsec_aliases[0]['name'];
        $fw_conf['blacklists_ipv6'] = $crowdsec_aliases[1]['name'];
        $fw_conf['log_dir'] = $candidate_log_dir;
        $fw_conf['log_max_size'] = $candidate_log_max_size;
        $fw_conf['prometheus']['enabled'] = false;
        set_yaml_content($fw_conf, CROWDSEC_FIREWALL_CONF);
    }
    // Modify agent yaml config
    $crowdsec_conf = get_yaml_content(CROWDSEC_CONF);
    if (!empty($crowdsec_conf)) {
        $crowdsec_conf['common']['log_dir'] = $candidate_log_dir;
        $crowdsec_conf['common']['log_max_size'] = $candidate_log_max_size;
        $crowdsec_conf['db_config']['use_wal'] = true;
        set_yaml_content($crowdsec_conf, CROWDSEC_CONF);
    }
}

/**
 * Find aliases array index
 * @param $name
 * @return int|string
 */
function get_alias_index($name)
{
    config_init_path(implode('/', ['aliases']));
    foreach (config_get_path('aliases/alias', []) as $idx => $alias) {
        if ($alias['name'] == $name) {
            return $idx;
        }
    }

    return -1;
}

/**
 * custom_php_pre_deinstall_command hook (package deinstallation)
 *
 * @return void
 */
function crowdsec_deinstall()
{
    // Delete aliases
    global $crowdsec_aliases;
    $delete_flag = false;
    parse_config(true);
    foreach ($crowdsec_aliases as $crowdsec_alias) {
        $crowdsec_index = get_alias_index($crowdsec_alias['name']);
        if ($crowdsec_index !== -1) {
            config_del_path("aliases/alias/{$crowdsec_index}");
            $delete_flag = true;
        }
    }
    if ($delete_flag) {
        write_config('pfsense_crowdsec: deleting aliases');
    }
    // Delete rules
    crowdsec_generate_rules('deinstall');
    filter_configure();
}

/**
 * Retrieve yaml content as an array
 *
 * @param $filepath
 * @return array|mixed
 */
function get_yaml_content($filepath)
{
    $result = [];
    try {
        $result = Yaml::parseFile($filepath);
    } catch (ParseException $exception) {
        syslog(LOG_ERR, 'Unable to parse ' . $filepath . ': ' . $exception->getMessage());
    }

    return $result;
}

/**
 * Write content in a yaml file
 *
 * @param $content
 * @param $filepath
 * @return void
 */
function set_yaml_content($content, $filepath)
{
    try {
        $yaml = Yaml::dump($content, 4);
        file_put_contents($filepath, $yaml);
    } catch (DumpException $exception) {
        syslog(LOG_ERR, 'Unable to dump ' . $filepath . ': ' . $exception->getMessage());
    }
}

function is_cs_service_running($name)
{
    $onestatus = trim(shell_exec("service $name onestatus"));

    return strpos($onestatus, "is running") > 0;
}

/**
 * custom_php_resync_config_command hook (install, setting edition, ...)
 *
 * @return void
 */
function crowdsec_resync_config()
{
    $cf = config_get_path('installedpackages/crowdsec/config/0', []);
    // Do not resync on first fresh install
    if (!$cf) {
        return;
    }

    // Init some flags to handle services management
    $should_use_crowdsec = false;
    $should_use_firewall = false;
    // Retrieve yaml contents
    $lapi_credentials = get_yaml_content(CROWDSEC_LAPI_CREDENTIALS);
    $lapi_credentials_orig = $lapi_credentials;
    $crowdsec_conf = get_yaml_content(CROWDSEC_CONF);
    $crowdsec_conf_orig = $crowdsec_conf;
    $fw_conf = get_yaml_content(CROWDSEC_FIREWALL_CONF);
    $fw_conf_orig = $fw_conf;

    // end of beta, use mainstream branch
    unset($crowdsec_conf['cscli']['hub_branch']);

    // Handle LAPI ON
    if (!empty($cf['enable_lapi'])) {
        $should_use_crowdsec = true;
        // Modify LAPI settings
        if (!empty($lapi_credentials)) {
            if (!empty($cf['lapi_port']) && !empty($cf['lapi_host'])) {
                $lapi_credentials['url'] = 'http://' . $cf['lapi_host'] . ':' . $cf['lapi_port'];
            }
        }
        // Modify crowdsec configuration
        if (!empty($crowdsec_conf)) {
            $crowdsec_conf['api']['server']['enable'] = true;
        }
        // Modify Firewall bouncer configuration
        if (!empty($fw_conf)) {
            $fw_conf['api_key'] = '${API_KEY}';
            if (!empty($cf['lapi_port']) && !empty($cf['lapi_host'])) {
                $fw_conf['api_url'] = 'http://' . $cf['lapi_host'] . ':' . $cf['lapi_port'];
            }
        }

        // update config immediately because cscli needs .api.server.enable = true
        set_yaml_content($crowdsec_conf, CROWDSEC_CONF);

        /**
         * Machine and bouncer will be created (and registered) again on next service start; so we delete them here.
         * @see /usr/local/etc/rc.d/crowdsec
         * @see /usr/local/etc/rc.d/crowdsec_firewall
         */
        // there is no --force option, we ignore errors if the machine or bouncer don't exist
        mwexec('cscli bouncers delete pfsense-firewall --ignore-missing');
        mwexec('cscli machines delete pfsense --ignore-missing');
    } else { // Handle Remote LAPI ON (e.g. LAPI OFF)
        // Modify Agent yaml config
        if (!empty($crowdsec_conf)) {
            $crowdsec_conf['api']['server']['enable'] = false;
        }
        // Modify LAPI yaml config
        if (!empty($lapi_credentials)) {
            if (!empty($cf['remote_agent_user']) && !empty($cf['remote_agent_password'])) {
                $lapi_credentials['login'] = $cf['remote_agent_user'];
                $lapi_credentials['password'] = $cf['remote_agent_password'];
            }
            if (!empty($cf['remote_lapi_url'])) {
                // TODO: clean up the url??
                $lapi_credentials['url'] = $cf['remote_lapi_url'];
            }
        }
        // Modify Firewall bouncer yaml config
        if (!empty($fw_conf)) {
            if (!empty($cf['remote_fw_bouncer_api_key'])) {
                $fw_conf['api_key'] = $cf['remote_fw_bouncer_api_key'];
            }
            if (!empty($cf['remote_lapi_url'])) {
                $fw_conf['api_url'] = $cf['remote_lapi_url'];
            }
        }
    }

    // Handle Agent ON
    if (!empty($cf['enable_agent'])) {
        $should_use_crowdsec = true;
        // Modify Agent yaml config
        if (!empty($crowdsec_conf)) {
            $crowdsec_conf['crowdsec_service']['enable'] = true;
            if (!empty($cf['lapi_port']) && !empty($cf['lapi_host'])) {
                $crowdsec_conf['api']['server']['listen_uri'] = $cf['lapi_host'] . ':' . $cf['lapi_port'];
            }
            if (!empty($cf['agent_log_level'])) {
                $crowdsec_conf['common']['log_level'] = $cf['agent_log_level'];
            }
            if (!empty($cf['metrics_port'])) {
                $crowdsec_conf['prometheus']['listen_port'] = (int)$cf['metrics_port'];
            }
        }
    } else {
        // Handle Agent OFF
        // Modify Agent yaml config
        if (!empty($crowdsec_conf)) {
            $crowdsec_conf['crowdsec_service']['enable'] = false;
        }
    }
    // Handle Firewall Bouncer ON
    if (!empty($cf['enable_fw_bouncer'])) {
        $should_use_firewall = true;
        if (!empty($fw_conf)) {
            if (!empty($cf['firewall_bouncer_log_level'])) {
                $fw_conf['log_level'] = $cf['firewall_bouncer_log_level'];
            }
        }
    }
    // Override yaml contents
    if ($lapi_credentials != $lapi_credentials_orig) {
        set_yaml_content($lapi_credentials, CROWDSEC_LAPI_CREDENTIALS);
    }
    if ($fw_conf != $fw_conf_orig) {
        set_yaml_content($fw_conf, CROWDSEC_FIREWALL_CONF);
    }
    if ($crowdsec_conf != $crowdsec_conf_orig) {
        set_yaml_content($crowdsec_conf, CROWDSEC_CONF);
    }
    // Handle services management, will also stop a service if disabled
    mwexec('service crowdsec.sh restart');
    mwexec('service crowdsec_firewall.sh restart');

    // Check service statuses and notify user
    $flash_class = 'success';
    $flash_message = 'Settings saved.';
    if ($should_use_crowdsec || $should_use_firewall) {
        sleep(2);
        // Handle crowdsec service
        if ($should_use_crowdsec) {
            if (!is_cs_service_running('crowdsec')) {
                $flash_class = 'warning';
                $flash_message .= ' Crowdsec service is not running. Please check /var/log/crowdsec/crowdsec.log.';
            } else {
                // Handle pfsense collection
                if (!file_exists(CROWDSEC_PFSENSE_COLLECTION)) {
                    if (!empty($crowdsec_conf)) {
                        shell_exec("cscli hub update");
                        shell_exec("cscli hub upgrade");
                        shell_exec("cscli --error collections install crowdsecurity/pfsense");
                        // We assume reloading won't fail
                        shell_exec("service crowdsec reload");
                    }
                }
            }
        }
        // Handle firewall bouncer
        if ($should_use_firewall) {
            if (!is_cs_service_running('crowdsec_firewall')) {
                $flash_class = 'warning';
                $flash_message .= ' Crowdsec firewall is not running. Please check /var/log/crowdsec/crowdsec-firewall-bouncer.log.';
            }
        }
    }

    if (config_path_enabled('system', 'use_mfs_tmpvar')) {
        $flash_class = 'danger';
        $flash_message .= " pfSense is set up to use a RAM disk for /var, the LAPI cannot be used because its database would be lost at every reboot. You can configure CrowdSec to use a remote LAPI or disable the RAM disk <a href='/system_advanced_misc.php'>in this page</a>.";
    }

    if ($_POST) {
        // we only flash the message if we are responding to a POST request
        // otherwise we are in post-install and can't do that
	@phpsession_begin();
	$_SESSION['flash_messages'][$flash_class][] = $flash_message;
	@phpsession_end(true);
    }
}

/**
 * custom_php_after_form_command hook (setting edition after form)
 *
 * @return void
 */
function crowdsec_after_form()
{
    $content = <<<EOF
<script type="text/javascript">
//<![CDATA[

    function disableRemote()
    {
        $('#enable_lapi').closest('div.form-group').next().show();
        $('#enable_lapi').closest('div.form-group').next().next().show();
        $('#enable_lapi').closest('div.form-group').next().next().next().show();
        $('#enable_lapi').closest('div.panel-default').next().find('.panel-body').hide();
    }
    
    function enableAllInterfaces()
    {
       $("#rules_all_interfaces").closest('div.form-group').next().hide("slow");
    }
    
    function disableAllInterfaces()
    {
        $("#rules_all_interfaces").closest('div.form-group').next().show("slow");
    }
    
    function enableRemote()
    {
        $('#enable_lapi').closest('div.form-group').next().hide();
        $('#enable_lapi').closest('div.form-group').next().next().hide();
        $('#enable_lapi').closest('div.form-group').next().next().next().hide();
        $('#enable_lapi').closest('div.panel-default').next().find('.panel-body').show();
    }
    
    events.push(function() {
        $('input[name="enable_lapi"]').on( "change", function() {
            if( $(this).prop('checked') == true ){
             disableRemote();
            } else {
             enableRemote();
            }
        });
        
        $('input[name="rules_all_interfaces"]').on( "change", function() {
            if( $(this).prop('checked') == true ){
             enableAllInterfaces();
            } else {
             disableAllInterfaces();
            }
        });
        
        if( $('input[name="enable_lapi"]').prop('checked') == true ){
             disableRemote();
         } else {
             enableRemote();
         }
        
        if( $('input[name="rules_all_interfaces"]').prop('checked') == true ){
             enableAllInterfaces();
         } else {
             disableAllInterfaces();
         }
        
        $("form").submit(function(){
            $('#submit').closest('div').html('<div class="loading"><i class="fa fa-spinner fa-spin"></i>Saving settings, please wait..</div>');
        });
    });
//]]>
</script>
EOF;

    echo $content;
}

/**
 * filter_rules_needed hook (install, setting edition, ...)
 *
 * @param $type
 * @return string
 */
function crowdsec_generate_rules($type)
{
    $rules = "";
    switch ($type) {
        case 'pfearly':
            global $config;
            $cf = $config['installedpackages']['crowdsec']['config'][0] ?? array();
            $log = !empty($cf['rules_log']) ? "log" : '';
            $tag = !empty($cf['rules_tag']) ? "tag " . $cf['rules_tag'] : '';
            $interfaces = '';
            if(empty($cf['rules_all_interfaces'])){
                $selected_interfaces = explode(',', $cf['rules_interfaces_select']);
                if($selected_interfaces){
                    $interfaces = 'on {';
                    foreach($selected_interfaces as $selected_interface){
                        $interface = get_real_interface($selected_interface);
                        $interfaces .= " $interface ";
                    }
                    $interfaces .= '}';
                }

            }

            if (!empty($cf['enable_rule_v4'])) {
                // incoming traffic

                $rules .= "block drop";
                $rules .= ' in '  . $log . ' ' .
                          ' quick '. $interfaces. ' inet from <crowdsec_blacklists> to any label "CrowdSec IPv4"';
                if (!empty($tag)) {
                    $rules .= ' ' . $tag;
                }
                $rules .= "\n";

                // outgoing traffic

                $rules .= "block drop";
                $rules .= ' out ' . $log . ' ' .
                          ' quick '. $interfaces.' inet from any to <crowdsec_blacklists> label "CrowdSec IPv4 out"';
                if (!empty($tag)) {
                    $rules .= ' ' . $tag;
                }
                $rules .= "\n";
            }

            if (!empty($cf['enable_rule_v6'])) {
                // incoming traffic

                $rules .= "block drop";
                $rules .= ' in ' . $log . ' ' .
                          ' quick '. $interfaces.' inet6 from <crowdsec6_blacklists> to any label "CrowdSec IPv6"';
                if (!empty($tag)) {
                    $rules .= ' ' . $tag;
                }
                $rules .= "\n";

                // outgoing traffic

                $rules .= "block drop";
                $rules .= ' out ' . $log . ' ' .
                          ' quick '. $interfaces.' inet6 from any to <crowdsec6_blacklists> label "CrowdSec IPv6 out"';
                if (!empty($tag)) {
                    $rules .= ' ' . $tag;
                }
                $rules .= "\n";
            }

            break;
        default:
            break;
    }

    if (!empty($rules)) {
        syslog(LOG_INFO, "CrowdSec rules:\n $rules");
    }

    return $rules;
}

