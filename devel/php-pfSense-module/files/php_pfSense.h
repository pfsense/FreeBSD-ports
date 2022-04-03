/*
 * php_pfSense.h
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2022 Rubicon Communications, LLC (Netgate)
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

#ifndef PHP_PFSENSE_H
#define PHP_PFSENSE_H 1

#ifdef ZTS
#include "TSRM.h"
#endif
#ifdef DHCP_INTEGRATION
#define DNS_TSEC_H 1

typedef char dns_tsec_t;

#include <dhcpctl.h>
#endif

#include <libvici.h>

ZEND_BEGIN_MODULE_GLOBALS(pfSense)
	int s;
	int inets;
	int inets6;
#ifdef IPFW_FUNCTIONS
	int ipfw;
#endif
	int csock;
ZEND_END_MODULE_GLOBALS(pfSense)

#ifdef ZTS
#define PFSENSE_G(v) TSRMG(pfSense_globals_id, zend_pfSense_globals *, v)
extern int pfSense_globals_id;
#else
#define PFSENSE_G(v) (pfSense_globals.v)
#endif

#ifdef DHCP_INTEGRATION
typedef struct _omapi_data {
	dhcpctl_handle handle;
} omapi_data;
#define PHP_PFSENSE_RES_NAME "DHCP data"
#endif

#define PHP_PFSENSE_WORLD_VERSION "1.0"
#define PHP_PFSENSE_WORLD_EXTNAME "pfSense"

PHP_MINIT_FUNCTION(pfSense_socket);
PHP_MSHUTDOWN_FUNCTION(pfSense_socket_close);

PHP_FUNCTION(pfSense_get_interface_info);
PHP_FUNCTION(pfSense_get_interface_stats);
PHP_FUNCTION(pfSense_get_pf_rules);
PHP_FUNCTION(pfSense_get_pf_states);
PHP_FUNCTION(pfSense_get_pf_stats);
PHP_FUNCTION(pfSense_get_os_hw_data);
PHP_FUNCTION(pfSense_kenv_dump);
PHP_FUNCTION(pfSense_get_os_kern_data);
PHP_FUNCTION(pfSense_get_interface_addresses);
PHP_FUNCTION(pfSense_getall_interface_addresses);
PHP_FUNCTION(pfSense_vlan_create);
PHP_FUNCTION(pfSense_interface_rename);
PHP_FUNCTION(pfSense_interface_mtu);
PHP_FUNCTION(pfSense_interface_getmtu);
PHP_FUNCTION(pfSense_bridge_add_member);
PHP_FUNCTION(pfSense_bridge_del_member);
PHP_FUNCTION(pfSense_bridge_member_flags);
PHP_FUNCTION(pfSense_interface_listget);
PHP_FUNCTION(pfSense_interface_create);
PHP_FUNCTION(pfSense_interface_create2);
PHP_FUNCTION(pfSense_interface_destroy);
PHP_FUNCTION(pfSense_interface_flags);
PHP_FUNCTION(pfSense_interface_setaddress);
PHP_FUNCTION(pfSense_interface_deladdress);
PHP_FUNCTION(pfSense_interface_capabilities);
PHP_FUNCTION(pfSense_ngctl_name);
PHP_FUNCTION(pfSense_ngctl_attach);
PHP_FUNCTION(pfSense_ngctl_detach);
PHP_FUNCTION(pfSense_get_modem_devices);
PHP_FUNCTION(pfSense_sync);
PHP_FUNCTION(pfSense_fsync);
PHP_FUNCTION(pfSense_kill_states);
PHP_FUNCTION(pfSense_kill_srcstates);
PHP_FUNCTION(pfSense_ip_to_mac);
#ifdef DHCP_INTEGRATION
PHP_FUNCTION(pfSense_open_dhcpd);
PHP_FUNCTION(pfSense_close_dhcpd);
PHP_FUNCTION(pfSense_register_lease);
PHP_FUNCTION(pfSense_delete_lease);
#endif

#ifdef IPFW_FUNCTIONS
PHP_FUNCTION(pfSense_ipfw_table);
PHP_FUNCTION(pfSense_ipfw_table_info);
PHP_FUNCTION(pfSense_ipfw_table_list);
PHP_FUNCTION(pfSense_ipfw_table_lookup);
PHP_FUNCTION(pfSense_ipfw_table_zerocnt);
PHP_FUNCTION(pfSense_ipfw_tables_list);
PHP_FUNCTION(pfSense_ipfw_pipe);
#endif
#ifdef ETHERSWITCH_FUNCTIONS
PHP_FUNCTION(pfSense_etherswitch_getinfo);
PHP_FUNCTION(pfSense_etherswitch_getport);
PHP_FUNCTION(pfSense_etherswitch_setport);
PHP_FUNCTION(pfSense_etherswitch_setport_state);
PHP_FUNCTION(pfSense_etherswitch_getlaggroup);
PHP_FUNCTION(pfSense_etherswitch_getvlangroup);
PHP_FUNCTION(pfSense_etherswitch_setlaggroup);
PHP_FUNCTION(pfSense_etherswitch_setvlangroup);
PHP_FUNCTION(pfSense_etherswitch_setmode);
#endif
PHP_FUNCTION(pfSense_ipsec_list_sa);
#ifdef PF_CP_FUNCTIONS
PHP_FUNCTION(pfSense_pf_cp_flush);
PHP_FUNCTION(pfSense_pf_cp_get_eth_pipes);
PHP_FUNCTION(pfSense_pf_cp_get_eth_rule_counters);
PHP_FUNCTION(pfSense_pf_cp_get_eth_last_active);
PHP_FUNCTION(pfSense_pf_cp_zerocnt);
#endif

extern zend_module_entry pfSense_module_entry;
#define phpext_pfSense_ptr &pfSense_module_entry

#endif
