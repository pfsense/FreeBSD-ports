/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved. 
 *
 *  Redistribution and use in source and binary forms, with or without modification, 
 *  are permitted provided that the following conditions are met: 
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *      this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in
 *      the documentation and/or other materials provided with the
 *      distribution. 
 *
 *  3. All advertising materials mentioning features or use of this software 
 *      must display the following acknowledgment:
 *      "This product includes software developed by the pfSense Project
 *       for use in the pfSense® software distribution. (http://www.pfsense.org/). 
 *
 *  4. The names "pfSense" and "pfSense Project" must not be used to
 *       endorse or promote products derived from this software without
 *       prior written permission. For written permission, please contact
 *       coreteam@pfsense.org.
 *
 *  5. Products derived from this software may not be called "pfSense"
 *      nor may "pfSense" appear in their names without prior written
 *      permission of the Electric Sheep Fencing, LLC.
 *
 *  6. Redistributions of any form whatsoever must retain the following
 *      acknowledgment:
 *
 *  "This product includes software developed by the pfSense Project
 *  for use in the pfSense software distribution (http://www.pfsense.org/).
  *
 *  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *  OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *  ====================================================================
 *
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

#ifdef IPFW_FUNCTIONS
#include "php_dummynet.h"
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
PHP_FUNCTION(pfSense_ipfw_getTablestats);
PHP_FUNCTION(pfSense_ipfw_Tableaction);
PHP_FUNCTION(pfSense_pipe_action);
#endif
PHP_FUNCTION(pfSense_ipsec_list_sa);

extern zend_module_entry pfSense_module_entry;
#define phpext_pfSense_ptr &pfSense_module_entry

#endif
