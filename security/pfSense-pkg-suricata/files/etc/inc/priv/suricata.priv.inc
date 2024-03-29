<?php
/*
 * suricata.priv.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2018-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2023 Bill Meeks
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

$priv_list['page-services-suricata'] = array();
$priv_list['page-services-suricata']['name'] = "WebCfg - Services: suricata package.";
$priv_list['page-services-suricata']['descr'] = "Allow access to suricata package gui";
$priv_list['page-services-suricata']['match'] = array();
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_alerts.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_blocked.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_check_for_rule_updates.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_define_vars.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_download_updates.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_app_parsers.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_libhtp_policy_engine.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_import_aliases.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_interfaces.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_interfaces_edit.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_interfaces_global.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_suppress.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_suppress_edit.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_select_alias.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_list_view.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_logs_browser.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_logs_mgmt.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_sid_mgmt.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_passlist.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_passlist_edit.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_post_install.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_flow_stream.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_rules.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_rules_edit.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_rules_flowbits.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_rulesets.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_os_policy_engine.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_global.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_ip_list_mgmt.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_ip_reputation.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_iprep_list_browser.php*";
$priv_list['page-services-suricata']['match'][] = "pkg_edit.php?xml=suricata/suricata.xml*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_check_cron_misc.inc*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_yaml_template.inc*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata.inc*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_defs.inc*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_geoipupdate.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_etiqrisk_update.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_post_install.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_uninstall.php*";
$priv_list['page-services-suricata']['match'][] = "suricata/suricata_generate_yaml.php*";
$priv_list['page-services-suricata']['match'][] = "widgets/javascript/suricata_alerts.js*";
$priv_list['page-services-suricata']['match'][] = "widgets/widgets/suricata_alerts.widget.php*";
$priv_list['page-services-suricata']['match'][] = "widgets/include/widget-suricata.inc*";
?>
