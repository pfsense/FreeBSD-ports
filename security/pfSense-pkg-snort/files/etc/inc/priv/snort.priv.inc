<?php
/*
 * snort.priv.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013 Marcello Coutinho <marcellocoutinho@gmail.com>
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

global $priv_list;

$priv_list['page-services-snort'] = array();
$priv_list['page-services-snort']['name'] = "WebCfg - Services: Snort package.";
$priv_list['page-services-snort']['descr'] = "Allow access to Snort package gui";
$priv_list['page-services-snort']['match'] = array();
$priv_list['page-services-snort']['match'][] = "snort/snort_alerts.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_blocked.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_check_for_rule_updates.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_define_servers.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_defs.inc*";
$priv_list['page-services-snort']['match'][] = "snort/snort_download_rules.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_download_updates.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_edit_hat_data.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_frag3_engine.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_ftp_client_engine.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_ftp_server_engine.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_httpinspect_engine.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_import_aliases.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_interfaces.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_interfaces_edit.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_interfaces_global.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_interfaces_suppress.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_interfaces_suppress_edit.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_passlist.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_passlist_edit.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_list_view.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_migrate_config.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_post_install.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_preprocessors.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_rules.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_rules_edit.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_rules_flowbits.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_rulesets.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_select_alias.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_stream5_engine.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_ip_list_mgmt.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_sid_mgmt.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_ip_reputation.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_iprep_list_browser.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_generate_conf.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_interface_logs.php*";
$priv_list['page-services-snort']['match'][] = "snort/snort_log_mgmt.php*";
$priv_list['page-services-snort']['match'][] = "widgets/javascript/snort_alerts.js*";
$priv_list['page-services-snort']['match'][] = "widgets/include/widget-snort.inc*";
$priv_list['page-services-snort']['match'][] = "widgets/widgets/snort_alerts.widget.php*";
$priv_list['page-services-snort']['match'][] = "pkg_edit.php?xml=snort_sync.xml*";
$priv_list['page-services-snort']['match'][] = "pkg_edit.php?xml=snort/snort.xml*";
$priv_list['page-services-snort']['match'][] = "snort/snort_check_cron_misc.inc*";
$priv_list['page-services-snort']['match'][] = "snort/snort_conf_template.inc*";
$priv_list['page-services-snort']['match'][] = "snort/snort.inc*";
?>
