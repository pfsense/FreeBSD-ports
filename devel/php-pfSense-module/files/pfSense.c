/*
 * pfSense.c
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

/*

Functions copied from util.c and modem.c of mpd5 source are protected by
this copyright.
They are ExclusiveOpenDevice/ExclusiveCloseDevice and
OpenSerialDevice.

Copyright (c) 1995-1999 Whistle Communications, Inc. All rights reserved.

Subject to the following obligations and disclaimer of warranty,
use and redistribution of this software, in source or object code
forms, with or without modifications are expressly permitted by
Whistle Communications; provided, however, that:   (i) any and
all reproductions of the source or object code must include the
copyright notice above and the following disclaimer of warranties;
and (ii) no rights are granted, in any manner or form, to use
Whistle Communications, Inc. trademarks, including the mark "WHISTLE
COMMUNICATIONS" on advertising, endorsements, or otherwise except
as such appears in the above copyright notice or in the software.

THIS SOFTWARE IS BEING PROVIDED BY WHISTLE COMMUNICATIONS "AS IS",
AND TO THE MAXIMUM EXTENT PERMITTED BY LAW, WHISTLE COMMUNICATIONS
MAKES NO REPRESENTATIONS OR WARRANTIES, EXPRESS OR IMPLIED,
REGARDING THIS SOFTWARE, INCLUDING WITHOUT LIMITATION, ANY AND
ALL IMPLIED WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
PURPOSE, OR NON-INFRINGEMENT.  WHISTLE COMMUNICATIONS DOES NOT
WARRANT, GUARANTEE, OR MAKE ANY REPRESENTATIONS REGARDING THE USE
OF, OR THE RESULTS OF THE USE OF THIS SOFTWARE IN TERMS OF ITS
CORRECTNESS, ACCURACY, RELIABILITY OR OTHERWISE.  IN NO EVENT
SHALL WHISTLE COMMUNICATIONS BE LIABLE FOR ANY DAMAGES RESULTING
FROM OR ARISING OUT OF ANY USE OF THIS SOFTWARE, INCLUDING WITHOUT
LIMITATION, ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
PUNITIVE, OR CONSEQUENTIAL DAMAGES, PROCUREMENT OF SUBSTITUTE
GOODS OR SERVICES, LOSS OF USE, DATA OR PROFITS, HOWEVER CAUSED
AND UNDER ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF WHISTLE COMMUNICATIONS
IS ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

*/

#include <sys/endian.h>
#include <sys/ioctl.h>
#include <sys/param.h>
#include <sys/types.h>
#include <sys/socket.h>
#include <sys/sysctl.h>

#include <arpa/inet.h>
#include <net/ethernet.h>
#include <net/if.h>
#include <net/if_bridgevar.h>
#include <net/if_dl.h>
#include <net/if_mib.h>
#include <net/if_types.h>
#include <net/if_var.h>
#include <net/if_vlan_var.h>
#include <net/pfvar.h>
#include <net/route.h>
#include <netgraph/ng_message.h>
#include <netinet/if_ether.h>
#include <netinet/in.h>
#include <netinet/in_var.h>
#include <netinet/ip_fw.h>
#include <netinet/tcp_fsm.h>
#include <netinet6/in6_var.h>
#include <netpfil/pf/pf.h>
#include <net80211/ieee80211_ioctl.h>

#include <vm/vm_param.h>

#include <fcntl.h>
#include <glob.h>
#include <inttypes.h>
#include <ifaddrs.h>
#include <libgen.h>
#include <libpfctl.h>
#include <netgraph.h>
#include <netdb.h>
#include <poll.h>
#include <stdio.h>
#include <stdlib.h>
#include <strings.h>
#include <termios.h>
#include <unistd.h>
#include <kenv.h>

#define IS_EXT_MODULE

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#ifdef ETHERSWITCH_FUNCTIONS
#include <net/if_media.h>
#include "etherswitch.h"
#endif

#include "ipfw2.h"
#include "php.h"
#include "php_ini.h"
#include "php_pfSense.h"

int pfSense_dhcpd;

ZEND_DECLARE_MODULE_GLOBALS(pfSense)

static zend_function_entry pfSense_functions[] = {
    PHP_FE(pfSense_get_interface_info, NULL)
    PHP_FE(pfSense_get_interface_addresses, NULL)
    PHP_FE(pfSense_getall_interface_addresses, NULL)
    PHP_FE(pfSense_get_interface_stats, NULL)
    PHP_FE(pfSense_get_pf_rules, NULL)
    PHP_FE(pfSense_get_pf_states, NULL)
    PHP_FE(pfSense_get_pf_stats, NULL)
    PHP_FE(pfSense_get_os_hw_data, NULL)
    PHP_FE(pfSense_kenv_dump, NULL)
    PHP_FE(pfSense_get_os_kern_data, NULL)
    PHP_FE(pfSense_vlan_create, NULL)
    PHP_FE(pfSense_interface_rename, NULL)
    PHP_FE(pfSense_interface_mtu, NULL)
    PHP_FE(pfSense_interface_getmtu, NULL)
    PHP_FE(pfSense_bridge_add_member, NULL)
    PHP_FE(pfSense_bridge_del_member, NULL)
    PHP_FE(pfSense_bridge_member_flags, NULL)
    PHP_FE(pfSense_interface_listget, NULL)
    PHP_FE(pfSense_interface_create, NULL)
    PHP_FE(pfSense_interface_create2, NULL)
    PHP_FE(pfSense_interface_destroy, NULL)
    PHP_FE(pfSense_interface_flags, NULL)
    PHP_FE(pfSense_interface_capabilities, NULL)
    PHP_FE(pfSense_interface_setaddress, NULL)
    PHP_FE(pfSense_interface_deladdress, NULL)
    PHP_FE(pfSense_ngctl_name, NULL)
    PHP_FE(pfSense_get_modem_devices, NULL)
    PHP_FE(pfSense_sync, NULL)
    PHP_FE(pfSense_fsync, NULL)
    PHP_FE(pfSense_kill_states, NULL)
    PHP_FE(pfSense_kill_srcstates, NULL)
    PHP_FE(pfSense_ip_to_mac, NULL)
#ifdef DHCP_INTEGRATION
    PHP_FE(pfSense_open_dhcpd, NULL)
    PHP_FE(pfSense_close_dhcpd, NULL)
    PHP_FE(pfSense_register_lease, NULL)
    PHP_FE(pfSense_delete_lease, NULL)
#endif
#ifdef IPFW_FUNCTIONS
    PHP_FE(pfSense_ipfw_table, NULL)
    PHP_FE(pfSense_ipfw_table_info, NULL)
    PHP_FE(pfSense_ipfw_table_list, NULL)
    PHP_FE(pfSense_ipfw_table_lookup, NULL)
    PHP_FE(pfSense_ipfw_table_zerocnt, NULL)
    PHP_FE(pfSense_ipfw_tables_list, NULL)
    PHP_FE(pfSense_ipfw_pipe, NULL)
#endif
#ifdef ETHERSWITCH_FUNCTIONS
    PHP_FE(pfSense_etherswitch_getinfo, NULL)
    PHP_FE(pfSense_etherswitch_getport, NULL)
    PHP_FE(pfSense_etherswitch_setport, NULL)
    PHP_FE(pfSense_etherswitch_setport_state, NULL)
    PHP_FE(pfSense_etherswitch_getlaggroup, NULL)
    PHP_FE(pfSense_etherswitch_getvlangroup, NULL)
    PHP_FE(pfSense_etherswitch_setlaggroup, NULL)
    PHP_FE(pfSense_etherswitch_setvlangroup, NULL)
    PHP_FE(pfSense_etherswitch_setmode, NULL)
#endif
    PHP_FE(pfSense_ipsec_list_sa, NULL)
#ifdef PF_CP_FUNCTIONS
    PHP_FE(pfSense_pf_cp_flush, NULL)
    PHP_FE(pfSense_pf_cp_get_eth_pipes, NULL)
    PHP_FE(pfSense_pf_cp_get_eth_rule_counters, NULL)
    PHP_FE(pfSense_pf_cp_get_eth_last_active,NULL)
    PHP_FE(pfSense_pf_cp_zerocnt, NULL)
#endif
    {NULL, NULL, NULL}
};

zend_module_entry pfSense_module_entry = {
    STANDARD_MODULE_HEADER,
    PHP_PFSENSE_WORLD_EXTNAME,
    pfSense_functions,
    PHP_MINIT(pfSense_socket),
    PHP_MSHUTDOWN(pfSense_socket_close),
    NULL,
    NULL,
    NULL,
    PHP_PFSENSE_WORLD_VERSION,
    STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_PFSENSE
ZEND_GET_MODULE(pfSense)
#endif

#ifdef DHCP_INTEGRATION
static void
php_pfSense_destroy_dhcpd(zend_rsrc_list_entry *rsrc TSRMLS_DC)
{
	omapi_data *conn = (omapi_data *)rsrc->ptr;

	if (conn)
		efree(conn);
}
#endif

/* interface management code */
static int
pfi_get_ifaces(int dev, const char *filter, struct pfi_kif *buf, int *size)
{
	struct pfioc_iface io;

	bzero(&io, sizeof io);
	if (filter != NULL)
		if (strlcpy(io.pfiio_name, filter, sizeof(io.pfiio_name)) >=
		    sizeof(io.pfiio_name)) {
			errno = EINVAL;
			return (-1);
		}
	io.pfiio_buffer = buf;
	io.pfiio_esize = sizeof(struct pfi_kif);
	io.pfiio_size = *size;
	if (ioctl(dev, DIOCIGETIFACES, &io))
		return (-1);
	*size = io.pfiio_size;
	return (0);
}

/* returns prefixlen, obtained from sbin/ifconfig/af_inet6.c */
static int
prefix(void *val, int size)
{
	u_char *name = (u_char *)val;
	int byte, bit, plen = 0;

	for (byte = 0; byte < size; byte++, plen += 8)
		if (name[byte] != 0xff)
			break;
	if (byte == size)
		return (plen);
	for (bit = 7; bit != 0; bit--, plen++)
		if (!(name[byte] & (1 << bit)))
			break;
	for (; bit != 0; bit--)
		if (name[byte] & (1 << bit))
			return(0);
	byte++;
	for (; byte < size; byte++)
		if (name[byte])
			return(0);
	return (plen);
}

static int
unmask(struct pf_addr *m, sa_family_t af)
{
	int i = 31, j = 0, b = 0;
	uint32_t tmp;

	while (j < 4 && m->addr32[j] == 0xffffffff) {
		b += 32;
		j++;
	}
	if (j < 4) {
		tmp = ntohl(m->addr32[j]);
		for (i = 31; tmp & (1 << i); --i)
			b++;
	}
	return (b);
}

static void
pf_print_addr(struct pf_addr_wrap *addr, sa_family_t af, char *buf, size_t bufsz)
{
	char tmp[512];

	memset(tmp, 0, sizeof(tmp));
	switch (addr->type) {
	case PF_ADDR_DYNIFTL:
		strlcat(buf, "(", bufsz);
		strlcat(buf, addr->v.ifname, bufsz);
		if (addr->iflags & PFI_AFLAG_NETWORK)
			strlcat(buf, ":network", bufsz);
		if (addr->iflags & PFI_AFLAG_BROADCAST)
			strlcat(buf, ":broadcast", bufsz);
		if (addr->iflags & PFI_AFLAG_PEER)
			strlcat(buf, ":peer", bufsz);
		if (addr->iflags & PFI_AFLAG_NOALIAS)
			strlcat(buf, ":0", bufsz);
		if (addr->p.dyncnt <= 0)
			strlcat(buf, ":*", bufsz);
		else {
			printf(tmp, sizeof(tmp) - 1, ":%d", addr->p.dyncnt);
			strlcat(buf, tmp, bufsz);
		}
		strlcat(buf, ")", bufsz);
		break;
	case PF_ADDR_TABLE:
		if (addr->p.tblcnt == -1)
			snprintf(tmp, sizeof(tmp) - 1, "<%s:*>", addr->v.tblname);
		else
			snprintf(tmp, sizeof(tmp) - 1, "<%s:%d>", addr->v.tblname,
			    addr->p.tblcnt);
		strlcat(buf, tmp, bufsz);
		return;
	case PF_ADDR_RANGE:
		if (inet_ntop(af, &addr->v.a.addr, tmp, sizeof(tmp)) == NULL)
			strlcat(buf, "?", bufsz);
		else
			strlcat(buf, tmp, bufsz);
		strlcat(buf, " - ", bufsz);
		if (inet_ntop(af, &addr->v.a.mask, tmp, sizeof(tmp)) == NULL)
			strlcat(buf, "?", bufsz);
		else
			strlcat(buf, tmp, bufsz);
		break;
	case PF_ADDR_ADDRMASK:
		if (PF_AZERO(&addr->v.a.addr, AF_INET6) &&
		    PF_AZERO(&addr->v.a.mask, AF_INET6))
			strlcat(buf, "any", bufsz);
		else {
			if (inet_ntop(af, &addr->v.a.addr, tmp,
			    sizeof(tmp)) == NULL)
				strlcat(buf, "?", bufsz);
			else
				strlcat(buf, tmp, bufsz);
		}
		break;
	case PF_ADDR_NOROUTE:
		strlcat(buf, "no-route", bufsz);
		return;
	case PF_ADDR_URPFFAILED:
		strlcat(buf, "urpf-failed", bufsz);
		return;
	default:
		strlcat(buf, "?", bufsz);
		return;
	}

	/* mask if not _both_ address and mask are zero */
	if (addr->type != PF_ADDR_RANGE &&
	    !(PF_AZERO(&addr->v.a.addr, AF_INET6) &&
	    PF_AZERO(&addr->v.a.mask, AF_INET6))) {
		int bits = unmask(&addr->v.a.mask, af);

		if (bits != (af == AF_INET ? 32 : 128)) {
			snprintf(tmp, sizeof(tmp) - 1, "/%d", bits);
			strlcat(buf, tmp, bufsz);
		}
	}
}

static void
pf_print_host(struct pf_addr *addr, u_int16_t port, sa_family_t af, char *buf,
	size_t bufsz)
{
	char tmp[128];
	struct pf_addr_wrap aw;

	memset(&aw, 0, sizeof(aw));
	aw.v.a.addr = *addr;
	if (af == AF_INET)
		aw.v.a.mask.addr32[0] = 0xffffffff;
	else {
		memset(&aw.v.a.mask, 0xff, sizeof(aw.v.a.mask));
		af = AF_INET6;
	}
	pf_print_addr(&aw, af, buf, bufsz);

	if (port) {
		memset(tmp, 0, sizeof(tmp));
		if (af == AF_INET)
			snprintf(tmp, sizeof(tmp) - 1, ":%u", ntohs(port));
		else
			snprintf(tmp, sizeof(tmp) - 1, "[%u]", ntohs(port));
		strlcat(buf, tmp, bufsz);
	}
}

PHP_MINIT_FUNCTION(pfSense_socket)
{
	int csock;

	PFSENSE_G(s) = socket(AF_LOCAL, SOCK_DGRAM, 0);
	if (PFSENSE_G(s) < 0)
		return FAILURE;

	PFSENSE_G(inets) = socket(AF_INET, SOCK_DGRAM, 0);
	if (PFSENSE_G(inets) < 0) {
		close(PFSENSE_G(s));
		return FAILURE;
	}
	PFSENSE_G(inets6) = socket(AF_INET6, SOCK_DGRAM, 0);
	if (PFSENSE_G(inets6) < 0) {
		close(PFSENSE_G(s));
		close(PFSENSE_G(inets));
		return FAILURE;
	}

	if (geteuid() == 0 || getuid() == 0) {
#ifdef IPFW_FUNCTIONS
		PFSENSE_G(ipfw) = socket(AF_INET, SOCK_RAW, IPPROTO_RAW);
		if (PFSENSE_G(ipfw) < 0) {
			close(PFSENSE_G(s));
			close(PFSENSE_G(inets));
			close(PFSENSE_G(inets6));
			return FAILURE;
		} else
			fcntl(PFSENSE_G(ipfw), F_SETFD, fcntl(PFSENSE_G(ipfw), F_GETFD, 0) | FD_CLOEXEC);

#endif
		/* Create a new socket node */
		if (NgMkSockNode(NULL, &csock, NULL) < 0)
			csock = -1;
		else
			fcntl(csock, F_SETFD, fcntl(csock, F_GETFD, 0) | FD_CLOEXEC);

		PFSENSE_G(csock) = csock;

#ifdef DHCP_INTEGRATION
		pfSense_dhcpd = zend_register_list_destructors_ex(php_pfSense_destroy_dhcpd, NULL, PHP_PFSENSE_RES_NAME, module_number);
		dhcpctl_initialize();
		omapi_init();
#endif
	} else
		PFSENSE_G(csock) = -1;

	/* Don't leak these sockets to child processes */
	fcntl(PFSENSE_G(s), F_SETFD, fcntl(PFSENSE_G(s), F_GETFD, 0) | FD_CLOEXEC);
	fcntl(PFSENSE_G(inets), F_SETFD, fcntl(PFSENSE_G(inets), F_GETFD, 0) | FD_CLOEXEC);
	fcntl(PFSENSE_G(inets6), F_SETFD, fcntl(PFSENSE_G(inets6), F_GETFD, 0) | FD_CLOEXEC);

	REGISTER_LONG_CONSTANT("IFF_UP", IFF_UP, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFF_LINK0", IFF_LINK0, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFF_LINK1", IFF_LINK1, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFF_LINK2", IFF_LINK2, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFF_NOARP", IFF_NOARP, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFF_STATICARP", IFF_STATICARP, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFCAP_RXCSUM", IFCAP_RXCSUM, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFCAP_TXCSUM", IFCAP_TXCSUM, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFCAP_POLLING", IFCAP_POLLING, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFCAP_TSO", IFCAP_TSO, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFCAP_LRO", IFCAP_LRO, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFCAP_WOL", IFCAP_WOL, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFCAP_WOL_UCAST", IFCAP_WOL_UCAST, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFCAP_WOL_MCAST", IFCAP_WOL_MCAST, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFCAP_WOL_MAGIC", IFCAP_WOL_MAGIC, CONST_PERSISTENT | CONST_CS);

	REGISTER_LONG_CONSTANT("IFCAP_VLAN_HWTAGGING", IFCAP_VLAN_HWTAGGING, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFCAP_VLAN_MTU", IFCAP_VLAN_MTU, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFCAP_VLAN_HWFILTER", IFCAP_VLAN_HWFILTER, CONST_PERSISTENT | CONST_CS);
#ifdef IFCAP_VLAN_HWCSUM
	REGISTER_LONG_CONSTANT("IFCAP_VLAN_HWCSUM", IFCAP_VLAN_HWCSUM, CONST_PERSISTENT | CONST_CS);
#endif
#ifdef IFCAP_VLAN_HWTSO
	REGISTER_LONG_CONSTANT("IFCAP_VLAN_HWTSO", IFCAP_VLAN_HWTSO, CONST_PERSISTENT | CONST_CS);
#endif
	REGISTER_LONG_CONSTANT("IFCAP_RXCSUM_IPV6", IFCAP_RXCSUM_IPV6, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFCAP_TXCSUM_IPV6", IFCAP_TXCSUM_IPV6, CONST_PERSISTENT | CONST_CS);

	REGISTER_LONG_CONSTANT("IFBIF_LEARNING", IFBIF_LEARNING, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFBIF_DISCOVER", IFBIF_DISCOVER, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFBIF_STP", IFBIF_STP, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFBIF_SPAN", IFBIF_SPAN, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFBIF_STICKY", IFBIF_STICKY, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFBIF_BSTP_EDGE", IFBIF_BSTP_EDGE, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFBIF_BSTP_AUTOEDGE", IFBIF_BSTP_AUTOEDGE, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFBIF_BSTP_PTP", IFBIF_BSTP_PTP, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFBIF_BSTP_AUTOPTP", IFBIF_BSTP_AUTOPTP, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFBIF_BSTP_ADMEDGE", IFBIF_BSTP_ADMEDGE, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFBIF_BSTP_ADMCOST", IFBIF_BSTP_ADMCOST, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IFBIF_PRIVATE", IFBIF_PRIVATE, CONST_PERSISTENT | CONST_CS);

#ifdef IPFW_FUNCTIONS
	REGISTER_LONG_CONSTANT("IP_FW_TABLE_XADD", IP_FW_TABLE_XADD, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IP_FW_TABLE_XDEL", IP_FW_TABLE_XDEL, CONST_PERSISTENT | CONST_CS);
#endif

	return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(pfSense_socket_close)
{
	if (PFSENSE_G(csock) != -1)
		close(PFSENSE_G(csock));
	if (PFSENSE_G(inets) != -1)
		close(PFSENSE_G(inets));
	if (PFSENSE_G(inets6) != -1)
		close(PFSENSE_G(inets6));
	if (PFSENSE_G(s) != -1)
		close(PFSENSE_G(s));

	return SUCCESS;
}

static int
pfctl_addrprefix(char *addr, struct pf_addr *mask)
{
	char *p;
	const char *errstr;
	int prefix, ret_ga, q, r;
	struct addrinfo hints, *res;

	if ((p = strchr(addr, '/')) == NULL)
		return 0;

	*p++ = '\0';
	prefix = strtonum(p, 0, 128, &errstr);
	if (errstr) {
		php_printf("prefix is %s: %s", errstr, p);
		return (-1);
	}

	bzero(&hints, sizeof(hints));
	/* prefix only with numeric addresses */
	hints.ai_flags |= AI_NUMERICHOST;

	if ((ret_ga = getaddrinfo(addr, NULL, &hints, &res)) != 0) {
		php_printf("getaddrinfo: %s", gai_strerror(ret_ga));
		return (-1);
		/* NOTREACHED */
	}

	if (res->ai_family == AF_INET && prefix > 32) {
		freeaddrinfo(res);
		php_printf("prefix too long for AF_INET");
		return (-1);
	} else if (res->ai_family == AF_INET6 && prefix > 128) {
		freeaddrinfo(res);
		php_printf("prefix too long for AF_INET6");
		return (-1);
	}

	q = prefix >> 3;
	r = prefix & 7;
	switch (res->ai_family) {
	case AF_INET:
		bzero(&mask->v4, sizeof(mask->v4));
		mask->v4.s_addr = htonl((u_int32_t)
		    (0xffffffffffULL << (32 - prefix)));
		break;
	case AF_INET6:
		bzero(&mask->v6, sizeof(mask->v6));
		if (q > 0)
			memset((void *)&mask->v6, 0xff, q);
		if (r > 0)
			*((u_char *)&mask->v6 + q) =
			    (0xff00 >> r) & 0xff;
		break;
	}
	freeaddrinfo(res);

	return (0);
}

PHP_FUNCTION(pfSense_kill_srcstates)
{
	struct pfioc_src_node_kill psnk;
	struct addrinfo *res[2], *resp[2];
	struct sockaddr last_src, last_dst;
	int killed, sources;
	int ret_ga;

	int dev;
	char *ip1 = NULL, *ip2 = NULL;
	size_t ip1_len = 0, ip2_len = 0;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s|s", &ip1, &ip1_len, &ip2, &ip2_len) == FAILURE) {
		RETURN_NULL();
	}

	if ((dev = open("/dev/pf", O_RDWR)) < 0)
		RETURN_NULL();

	killed = sources = 0;

	memset(&psnk, 0, sizeof(psnk));
	memset(&psnk.psnk_src.addr.v.a.mask, 0xff,
	    sizeof(psnk.psnk_src.addr.v.a.mask));
	memset(&last_src, 0xff, sizeof(last_src));
	memset(&last_dst, 0xff, sizeof(last_dst));

	pfctl_addrprefix(ip1, &psnk.psnk_src.addr.v.a.mask);

	if ((ret_ga = getaddrinfo(ip1, NULL, NULL, &res[0])) != 0) {
		php_printf("getaddrinfo: %s", gai_strerror(ret_ga));
		RETURN_NULL();
		/* NOTREACHED */
	}
	for (resp[0] = res[0]; resp[0]; resp[0] = resp[0]->ai_next) {
		if (resp[0]->ai_addr == NULL)
			continue;
		/* We get lots of duplicates.  Catch the easy ones */
		if (memcmp(&last_src, resp[0]->ai_addr, sizeof(last_src)) == 0)
			continue;
		last_src = *(struct sockaddr *)resp[0]->ai_addr;

		psnk.psnk_af = resp[0]->ai_family;
		sources++;

		if (psnk.psnk_af == AF_INET)
			psnk.psnk_src.addr.v.a.addr.v4 =
			    ((struct sockaddr_in *)resp[0]->ai_addr)->sin_addr;
		else if (psnk.psnk_af == AF_INET6)
			psnk.psnk_src.addr.v.a.addr.v6 =
			    ((struct sockaddr_in6 *)resp[0]->ai_addr)->
			    sin6_addr;
		else {
			php_printf("Unknown address family %d", psnk.psnk_af);
			continue;
		}

		if (ip2 != NULL) {
			memset(&psnk.psnk_dst.addr.v.a.mask, 0xff,
			    sizeof(psnk.psnk_dst.addr.v.a.mask));
			memset(&last_dst, 0xff, sizeof(last_dst));
			pfctl_addrprefix(ip2,
			    &psnk.psnk_dst.addr.v.a.mask);
			if ((ret_ga = getaddrinfo(ip2, NULL, NULL,
			    &res[1]))) {
				php_printf("getaddrinfo: %s", gai_strerror(ret_ga));
				break;
			}
			for (resp[1] = res[1]; resp[1];
			    resp[1] = resp[1]->ai_next) {
				if (resp[1]->ai_addr == NULL)
					continue;
				if (psnk.psnk_af != resp[1]->ai_family)
					continue;

				if (memcmp(&last_dst, resp[1]->ai_addr,
				    sizeof(last_dst)) == 0)
					continue;
				last_dst = *(struct sockaddr *)resp[1]->ai_addr;

				if (psnk.psnk_af == AF_INET)
					psnk.psnk_dst.addr.v.a.addr.v4 =
					    ((struct sockaddr_in *)resp[1]->
					    ai_addr)->sin_addr;
				else if (psnk.psnk_af == AF_INET6)
					psnk.psnk_dst.addr.v.a.addr.v6 =
					    ((struct sockaddr_in6 *)resp[1]->
					    ai_addr)->sin6_addr;
				else {
					php_printf("Unknown address family %d",
					    psnk.psnk_af);
					continue;
				}

				if (ioctl(dev, DIOCKILLSRCNODES, &psnk))
					php_printf("DIOCKILLSRCNODES");
				killed += psnk.psnk_af;
				/* fixup psnk.psnk_af */
				psnk.psnk_af = resp[1]->ai_family;
			}
			freeaddrinfo(res[1]);
		} else {
			if (ioctl(dev, DIOCKILLSRCNODES, &psnk)) {
				php_printf("DIOCKILLSRCNODES");
				break;
			}
			killed += psnk.psnk_af;
			/* fixup psnk.psnk_af */
			psnk.psnk_af = res[0]->ai_family;
		}
	}

	freeaddrinfo(res[0]);

	RETURN_TRUE;
}

PHP_FUNCTION(pfSense_kill_states)
{
	struct pfioc_state_kill psk;
	struct addrinfo *res[2], *resp[2];
	struct sockaddr last_src, last_dst;
	int killed, sources;
	int ret_ga;

	int dev;
	char *ip1 = NULL, *ip2 = NULL, *proto = NULL, *iface = NULL;
	size_t ip1_len = 0, ip2_len = 0, proto_len = 0, iface_len = 0;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s|sss", &ip1, &ip1_len, &ip2, &ip2_len, &iface, &iface_len, &proto, &proto_len) == FAILURE) {
		RETURN_NULL();
	}

	if ((dev = open("/dev/pf", O_RDWR)) < 0)
		RETURN_NULL();

	killed = sources = 0;

	memset(&psk, 0, sizeof(psk));
	memset(&psk.psk_src.addr.v.a.mask, 0xff,
	    sizeof(psk.psk_src.addr.v.a.mask));
	memset(&last_src, 0xff, sizeof(last_src));
	memset(&last_dst, 0xff, sizeof(last_dst));

	if (iface != NULL && iface_len > 0 && strlcpy(psk.psk_ifname, iface,
	    sizeof(psk.psk_ifname)) >= sizeof(psk.psk_ifname))
		php_printf("invalid interface: %s", iface);

	if (proto != NULL && proto_len > 0) {
		if (!strncmp(proto, "tcp", strlen("tcp")))
			psk.psk_proto = IPPROTO_TCP;
		else if (!strncmp(proto, "udp", strlen("udp")))
			psk.psk_proto = IPPROTO_UDP;
		else if (!strncmp(proto, "icmpv6", strlen("icmpv6")))
			psk.psk_proto = IPPROTO_ICMPV6;
		else if (!strncmp(proto, "icmp", strlen("icmp")))
			psk.psk_proto = IPPROTO_ICMP;
	}

	if (pfctl_addrprefix(ip1, &psk.psk_src.addr.v.a.mask) < 0)
		RETURN_NULL();

	if ((ret_ga = getaddrinfo(ip1, NULL, NULL, &res[0])) != 0) {
		php_printf("getaddrinfo: %s", gai_strerror(ret_ga));
		RETURN_NULL();
		/* NOTREACHED */
	}
	for (resp[0] = res[0]; resp[0]; resp[0] = resp[0]->ai_next) {
		if (resp[0]->ai_addr == NULL)
			continue;
		/* We get lots of duplicates.  Catch the easy ones */
		if (memcmp(&last_src, resp[0]->ai_addr, sizeof(last_src)) == 0)
			continue;
		last_src = *(struct sockaddr *)resp[0]->ai_addr;

		psk.psk_af = resp[0]->ai_family;
		sources++;

		if (psk.psk_af == AF_INET)
			psk.psk_src.addr.v.a.addr.v4 =
			    ((struct sockaddr_in *)resp[0]->ai_addr)->sin_addr;
		else if (psk.psk_af == AF_INET6)
			psk.psk_src.addr.v.a.addr.v6 =
			    ((struct sockaddr_in6 *)resp[0]->ai_addr)->
			    sin6_addr;
		else {
			php_printf("Unknown address family %d", psk.psk_af);
			continue;
		}

		if (ip2 != NULL && ip2_len > 0) {
			memset(&psk.psk_dst.addr.v.a.mask, 0xff,
			    sizeof(psk.psk_dst.addr.v.a.mask));
			memset(&last_dst, 0xff, sizeof(last_dst));
			pfctl_addrprefix(ip2,
			    &psk.psk_dst.addr.v.a.mask);
			if ((ret_ga = getaddrinfo(ip2, NULL, NULL,
			    &res[1]))) {
				php_printf("getaddrinfo: %s",
				    gai_strerror(ret_ga));
				break;
				/* NOTREACHED */
			}
			for (resp[1] = res[1]; resp[1];
			    resp[1] = resp[1]->ai_next) {
				if (resp[1]->ai_addr == NULL)
					continue;
				if (psk.psk_af != resp[1]->ai_family)
					continue;

				if (memcmp(&last_dst, resp[1]->ai_addr,
				    sizeof(last_dst)) == 0)
					continue;
				last_dst = *(struct sockaddr *)resp[1]->ai_addr;

				if (psk.psk_af == AF_INET)
					psk.psk_dst.addr.v.a.addr.v4 =
					    ((struct sockaddr_in *)resp[1]->
					    ai_addr)->sin_addr;
				else if (psk.psk_af == AF_INET6)
					psk.psk_dst.addr.v.a.addr.v6 =
					    ((struct sockaddr_in6 *)resp[1]->
					    ai_addr)->sin6_addr;
				else {
					php_printf("Unknown address family %d", psk.psk_af);
					continue;
				}

				if (ioctl(dev, DIOCKILLSTATES, &psk))
					php_printf("Could not kill states\n");
				killed += psk.psk_af;
				/* fixup psk.psk_af */
				psk.psk_af = resp[1]->ai_family;
			}
			freeaddrinfo(res[1]);
		} else {
			if (ioctl(dev, DIOCKILLSTATES, &psk)) {
				php_printf("Could not kill states\n");
				break;
			}
			killed += psk.psk_af;
			/* fixup psk.psk_af */
			psk.psk_af = res[0]->ai_family;
		}
	}

	freeaddrinfo(res[0]);

	RETURN_TRUE;
}

#ifdef IPFW_FUNCTIONS
PHP_FUNCTION(pfSense_ipfw_pipe)
{
	int ac, do_pipe = 1;
	size_t param_len = 0;
	enum { bufsize = 2048 };
	char **ap, *av[bufsize], *param = NULL;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &param, &param_len) == FAILURE) {
		RETURN_FALSE;
	}

	memset(av, 0, sizeof(av));
	ac = 0;
	for (ap = av; (*ap = strsep(&param, " \t")) != NULL;) {
		if (**ap != '\0') {
			if (++ap >= &av[bufsize])
				break;
		}
		ac++;
	}
	if (ac > 0)
		ac = ac - 1;

	if (!strncmp(*av, "pipe", strlen(*av)))
		do_pipe = 1;
	else if (!strncmp(*av, "queue", strlen(*av)))
		do_pipe = 2;
	else if (!strncmp(*av, "flowset", strlen(*av)))
		do_pipe = 2;
	else if (!strncmp(*av, "sched", strlen(*av)))
		do_pipe = 3;
	else
		RETURN_FALSE;

	ap = av;
	ac--;
	ap++;

	if (!strncmp(*ap, "delete", strlen(*ap))) {
		ipfw_delete_pipe(do_pipe, strtol(ap[1], NULL, 10));
	} else if (!strncmp(ap[1], "config", strlen(ap[1]))) {
		/*
		 * For pipes, queues and nats we normally say 'nat|pipe NN config'
		 * but the code is easier to parse as 'nat|pipe config NN'
		 * so we swap the two arguments.
		 */
		if (ac > 1 && isdigit(*ap[0])) {
			char *p = ap[0];

			ap[0] = ap[1];
			ap[1] = p;
		}

		if (ipfw_config_pipe(ac, ap, do_pipe) < 0) {
			RETURN_FALSE;
		}
	} else
		RETURN_FALSE;

	RETURN_TRUE;
}

static int
table_get_info(ipfw_obj_header *oh, ipfw_xtable_info *i)
{
	char tbuf[sizeof(ipfw_obj_header) + sizeof(ipfw_xtable_info)];
	int error;
	socklen_t sz;

	sz = sizeof(tbuf);
	memset(tbuf, 0, sizeof(tbuf));
	memcpy(tbuf, oh, sizeof(*oh));
	oh = (ipfw_obj_header *)tbuf;
	oh->opheader.opcode = IP_FW_TABLE_XINFO;
	oh->opheader.version = 0;

	error = getsockopt(PFSENSE_G(ipfw), IPPROTO_IP, IP_FW3, &oh->opheader,
	    &sz);
	if (error != 0)
		return (error);
	if (sz < sizeof(tbuf))
		return (EINVAL);

	*i = *(ipfw_xtable_info *)(oh + 1);

	return (0);
}

static int
get_mac_addr_mask(const char *p, uint8_t *addr, uint8_t *mask)
{
	int i, ret;
	size_t l;
	char *ap, *ptr, *optr;
	struct ether_addr *mac;
	const char *macset = "0123456789abcdefABCDEF:";

	if (strcmp(p, "any") == 0) {
		for (i = 0; i < ETHER_ADDR_LEN; i++)
			addr[i] = mask[i] = 0;
		return (0);
	}

	ret = -1;
	optr = ptr = strdup(p);
	if ((ap = strsep(&ptr, "&/")) != NULL && *ap != 0) {
		l = strlen(ap);
		if (strspn(ap, macset) != l || (mac = ether_aton(ap)) == NULL)
			goto out;
		bcopy(mac, addr, ETHER_ADDR_LEN);
	} else
		goto out;

	if (ptr != NULL) { /* we have mask? */
		if (p[ptr - optr - 1] == '/') { /* mask len */
			long ml = strtol(ptr, &ap, 10);
			if (*ap != 0 || ml > ETHER_ADDR_LEN * 8 || ml < 0)
				return (-1);
			for (i = 0; ml > 0 && i < ETHER_ADDR_LEN; ml -= 8, i++)
				mask[i] = (ml >= 8) ? 0xff: (~0) << (8 - ml);
		} else { /* mask */
			l = strlen(ptr);
			if (strspn(ptr, macset) != l ||
			    (mac = ether_aton(ptr)) == NULL)
				goto out;
			bcopy(mac, mask, ETHER_ADDR_LEN);
		}
	} else { /* default mask: ff:ff:ff:ff:ff:ff */
		for (i = 0; i < ETHER_ADDR_LEN; i++)
			mask[i] = 0xff;
	}
	for (i = 0; i < ETHER_ADDR_LEN; i++)
		addr[i] &= mask[i];

	ret = 0;
out:
	free(optr);

	return (ret);
}

static int
tentry_fill_key(char *arg, uint8_t type, ipfw_obj_tentry *tent)
{
	char *mac, *p;
	int mask;
	uint32_t key;

	switch (type) {
	case IPFW_TABLE_ADDR:
		/* Remove the ',' if exists */
		if ((p = strchr(arg, ',')) != NULL) {
			*p = '\0';
			mac = p + 1;
			if (ether_aton_r(mac,
			    (struct ether_addr *)&tent->mac) == NULL)
				return (-1);
		}

		/* Remove / if exists */
		if ((p = strchr(arg, '/')) != NULL) {
			*p = '\0';
			mask = atoi(p + 1);
		}

		if (inet_pton(AF_INET, arg, &tent->k.addr) == 1) {
			if (p != NULL && mask > 32)
				return (-1);

			tent->subtype = AF_INET;
			tent->masklen = p ? mask : 32;
		} else if (inet_pton(AF_INET6, arg, &tent->k.addr6) == 1) {
			if (IN6_IS_ADDR_V4COMPAT(&tent->k.addr6))
				return (-1);
			if (p != NULL && mask > 128)
				return (-1);

			tent->subtype = AF_INET6;
			tent->masklen = p ? mask : 128;
		} else {
			/* Assume FQDN - not supported. */
			return (-1);
		}
		break;

	case IPFW_TABLE_MAC2: {
		char *src, *dst;
		struct mac_entry *mac;

		dst = arg;
		if ((p = strchr(arg, ',')) == NULL)
			return (-1);
		*p = '\0';
		src = p + 1;

		mac = (struct mac_entry *)&tent->k.mac;
		if (get_mac_addr_mask(dst, mac->addr, mac->mask) == -1)
			return (-1);
		if (get_mac_addr_mask(src, &(mac->addr[ETHER_ADDR_LEN]),
		    &(mac->mask[ETHER_ADDR_LEN])) == -1)
			return (-1);

		tent->subtype = AF_LINK;
		tent->masklen = ETHER_ADDR_LEN * 8;
		}
		break;

	case IPFW_TABLE_INTERFACE:
		/* Assume interface name. Copy significant data only */
		mask = MIN(strlen(arg), IF_NAMESIZE - 1);
		memcpy(tent->k.iface, arg, mask);
		/* Set mask to exact match */
		tent->masklen = 8 * IF_NAMESIZE;
		break;

	case IPFW_TABLE_NUMBER:
		/* Port or any other key */
		key = strtol(arg, &p, 10);
		if (*p != '\0') {
			php_printf("Invalid number: %s", arg);
			return (-1);
		}

		tent->k.key = key;
		tent->masklen = 32;
		break;

	default:
		return (-1);
	}

	return (0);
}

PHP_FUNCTION(pfSense_ipfw_table)
{
	char *arg, *tname;
	char xbuf[sizeof(ipfw_obj_header) + sizeof(ipfw_obj_ctlv) +
	    sizeof(ipfw_obj_tentry)];
	int error;
	ipfw_obj_ctlv *ctlv;
	ipfw_obj_header *oh;
	ipfw_obj_ntlv *ntlv;
	ipfw_obj_tentry *tent;
	ipfw_table_value *v;
	ipfw_xtable_info xi;
	zend_long action, pipe;
	size_t arglen, tnamelen;
	socklen_t size;

	pipe = 0;
	action = IP_FW_TABLE_XADD;
	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "sls|l",
	    &tname, &tnamelen, &action, &arg, &arglen, &pipe) == FAILURE) {
		RETURN_FALSE;
	}

	if (tnamelen == 0 || arglen == 0)
		RETURN_FALSE;
	if (action != IP_FW_TABLE_XDEL && action != IP_FW_TABLE_XADD)
		RETURN_FALSE;

	memset(xbuf, 0, sizeof(xbuf));
	oh = (ipfw_obj_header *)xbuf;
	oh->opheader.opcode = action;
	oh->opheader.version = 1;

	ntlv = &oh->ntlv;
	ntlv->head.type = IPFW_TLV_TBL_NAME;
	ntlv->head.length = sizeof(ipfw_obj_ntlv);
	ntlv->idx = 1;
	ntlv->set = 0;
	strlcpy(ntlv->name, tname, sizeof(ntlv->name));
	oh->idx = 1;

	if (table_get_info(oh, &xi) != 0)
		RETURN_FALSE;

	size = sizeof(ipfw_obj_ctlv) + sizeof(ipfw_obj_tentry);
	ctlv = (ipfw_obj_ctlv *)(oh + 1);
	ctlv->count = 1;
	ctlv->head.length = size;

	tent = (ipfw_obj_tentry *)(ctlv + 1);
	tent->head.length = sizeof(ipfw_obj_tentry);
	tent->idx = oh->idx;

	if (tentry_fill_key(arg, xi.type, tent) == -1)
		RETURN_FALSE;
	ntlv->type = xi.type;

	if (pipe != 0) {
		v = &tent->v.value;
		v->pipe = pipe;
	}

	size += sizeof(ipfw_obj_header);
	error = setsockopt(PFSENSE_G(ipfw), IPPROTO_IP, IP_FW3, &oh->opheader,
	    size);
	if (error < 0 && error != EEXIST) {
		php_printf("Failed setsockopt");
		RETURN_FALSE;
	}

	RETURN_TRUE;
}

static void
table_tinfo(zval *rarray, ipfw_xtable_info *info)
{
	char *type;

	add_assoc_string(rarray, "name", info->tablename);
	add_assoc_long(rarray, "count", info->count);
	add_assoc_long(rarray, "size", info->size);
	add_assoc_long(rarray, "set", info->set);
	if (info->limit > 0)
		add_assoc_long(rarray, "limit", info->limit);
	if (strlen(info->algoname) > 0)
		add_assoc_string(rarray, "algoname",
		    info->algoname);
	switch (info->type) {
	case IPFW_TABLE_ADDR:
		type = "addr";
		break;
	case IPFW_TABLE_INTERFACE:
		type = "interface";
		break;
	case IPFW_TABLE_NUMBER:
		type = "number";
		break;
	case IPFW_TABLE_FLOW:
		type = "flow";
		break;
	case IPFW_TABLE_MAC2:
		type = "mac";
		break;
	default:
		type = "unknown";
	}
	add_assoc_string(rarray, "type", type);
}

PHP_FUNCTION(pfSense_ipfw_table_info)
{
	char xbuf[sizeof(ipfw_obj_header)];
	char *tname;
	ipfw_obj_header *oh;
	ipfw_obj_ntlv *ntlv;
	ipfw_xtable_info xi;
	size_t tnamelen;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s",
	    &tname, &tnamelen) == FAILURE) {
		RETURN_NULL();
	}

	if (tnamelen == 0)
		RETURN_NULL();

	memset(xbuf, 0, sizeof(xbuf));
	oh = (ipfw_obj_header *)xbuf;

	ntlv = &oh->ntlv;
	ntlv->head.type = IPFW_TLV_TBL_NAME;
	ntlv->head.length = sizeof(ipfw_obj_ntlv);
	ntlv->idx = 1;
	ntlv->set = 0;
	strlcpy(ntlv->name, tname, sizeof(ntlv->name));
	oh->idx = 1;

	if (table_get_info(oh, &xi) != 0)
		RETURN_NULL();

	array_init(return_value);
	table_tinfo(return_value, &xi);
}

/*
 * Returns the number of bits set (from left) in a contiguous bitmask,
 * or -1 if the mask is not contiguous.
 * XXX this needs a proper fix.
 * This effectively works on masks in big-endian (network) format.
 * when compiled on little endian architectures.
 *
 * First bit is bit 7 of the first byte -- note, for MAC addresses,
 * the first bit on the wire is bit 0 of the first byte.
 * len is the max length in bits.
 */
int
contigmask(uint8_t *p, int len)
{
	int i, n;

	for (i=0; i<len ; i++)
		if ( (p[i/8] & (1 << (7 - (i%8)))) == 0) /* first bit unset */
			break;
	for (n=i+1; n < len; n++)
		if ( (p[n/8] & (1 << (7 - (n%8)))) != 0)
			return -1; /* mask not contiguous */
	return i;
}

static void
print_mac(zval *rarray, char *label, char *labelm, uint8_t *addr, uint8_t *mask)
{
	char buf[64];
	int l;

	l = contigmask(mask, 48);
	if (l == 0)
		add_assoc_string(rarray, label, "any");
	else {
		snprintf(buf, sizeof(buf), "%02x:%02x:%02x:%02x:%02x:%02x",
		    addr[0], addr[1], addr[2], addr[3], addr[4], addr[5]);
		add_assoc_string(rarray, label, buf);
		if (l == -1) {
			snprintf(buf, sizeof(buf),
			    "&%02x:%02x:%02x:%02x:%02x:%02x",
			    mask[0], mask[1], mask[2],
			    mask[3], mask[4], mask[5]);
			add_assoc_string(rarray, labelm, buf);
		} else if (l < 48)
			add_assoc_long(rarray, labelm, l);
	}
}

static void
table_show_value(zval *rarray, ipfw_table_value *v, uint32_t vmask)
{
	char abuf[INET6_ADDRSTRLEN + IF_NAMESIZE + 2];
	struct sockaddr_in6 sa6;
	uint32_t flag, i;
	struct in_addr a4;

	/*
	 * Some shorthands for printing values:
	 * legacy assumes all values are equal, so keep the first one.
	 */
	if (vmask == IPFW_VTYPE_LEGACY) {
		add_assoc_long(rarray, "value", v->tag);
		return;
	}

	for (i = 1; i < (1 << 31); i *= 2) {
		if ((flag = (vmask & i)) == 0)
			continue;
		switch (flag) {
		case IPFW_VTYPE_TAG:
			add_assoc_long(rarray, "tag", v->tag);
			break;
		case IPFW_VTYPE_PIPE:
			add_assoc_long(rarray, "pipe", v->pipe);
			break;
		case IPFW_VTYPE_DIVERT:
			add_assoc_long(rarray, "divert", v->divert);
			break;
		case IPFW_VTYPE_SKIPTO:
			add_assoc_long(rarray, "skipto", v->skipto);
			break;
		case IPFW_VTYPE_NETGRAPH:
			add_assoc_long(rarray, "netgraph", v->netgraph);
			break;
		case IPFW_VTYPE_FIB:
			add_assoc_long(rarray, "fib", v->fib);
			break;
		case IPFW_VTYPE_NAT:
			add_assoc_long(rarray, "nat", v->nat);
			break;
		case IPFW_VTYPE_LIMIT:
			add_assoc_long(rarray, "limit", v->limit);
			break;
		case IPFW_VTYPE_NH4:
			a4.s_addr = htonl(v->nh4);
			inet_ntop(AF_INET, &a4, abuf, sizeof(abuf));
			add_assoc_string(rarray, "nh4", abuf);
			break;
		case IPFW_VTYPE_DSCP:
			add_assoc_long(rarray, "dscp", v->dscp);
			break;
		case IPFW_VTYPE_NH6:
			sa6.sin6_family = AF_INET6;
			sa6.sin6_len = sizeof(sa6);
			sa6.sin6_addr = v->nh6;
			sa6.sin6_port = 0;
			sa6.sin6_scope_id = v->zoneid;
			if (getnameinfo((const struct sockaddr *)&sa6,
			    sa6.sin6_len, abuf, sizeof(abuf), NULL, 0,
			    NI_NUMERICHOST) == 0)
				add_assoc_string(rarray, "nh6", abuf);
			break;
		}
	}
}

static void
table_show_entry(zval *rarray, ipfw_xtable_info *i, ipfw_obj_tentry *tent)
{
	char tbuf[128];

	switch (i->type) {
	case IPFW_TABLE_ADDR:
		/* IPv4 or IPv6 prefixes */
		inet_ntop(tent->subtype, &tent->k, tbuf, sizeof(tbuf));
		add_assoc_string(rarray, "type", "addr");
		add_assoc_string(rarray, "ip", tbuf);
		add_assoc_long(rarray, "mask", tent->masklen);
		if (tent->mac != 0) {
			ether_ntoa_r((struct ether_addr *)&tent->mac, tbuf);
			add_assoc_string(rarray, "mac", tbuf);
		}
		break;
	case IPFW_TABLE_MAC2:
		/* Ethernet MAC address */
		add_assoc_string(rarray, "type", "mac2");
		print_mac(rarray, "dst", "dstmask", tent->k.mac.addr,
		    tent->k.mac.mask);
		print_mac(rarray, "src", "srcmask", tent->k.mac.addr + 6,
		    tent->k.mac.mask + 6);
		break;
	case IPFW_TABLE_INTERFACE:
		/* Interface names */
		add_assoc_string(rarray, "type", "interface");
		add_assoc_string(rarray, "iface", tent->k.iface);
		break;
	case IPFW_TABLE_NUMBER:
		/* numbers */
		add_assoc_string(rarray, "type", "number");
		add_assoc_long(rarray, "number", tent->k.key);
		break;
	default:
		add_assoc_string(rarray, "type", "unsupported");
	}

	table_show_value(rarray, &tent->v.value, i->vmask);
	add_assoc_double(rarray, "bytes", (double)tent->bcnt);
	add_assoc_double(rarray, "packets", (double)tent->pcnt);
	add_assoc_double(rarray, "timestamp", (double)tent->timestamp);
}

static void
table_show_list(zval *rarray, ipfw_obj_header *oh)
{
	ipfw_obj_tentry *tent;
	ipfw_xtable_info *i;
	uint32_t count;
	zval entarray;

	i = (ipfw_xtable_info *)(oh + 1);
	tent = (ipfw_obj_tentry *)(i + 1);

	count = i->count;
	while (count > 0) {
		array_init(&entarray);
		table_show_entry(&entarray, i, tent);
		add_next_index_zval(rarray, &entarray);
		tent = (ipfw_obj_tentry *)((caddr_t)tent + tent->head.length);
		count--;
	}
}

PHP_FUNCTION(pfSense_ipfw_table_list)
{
	char xbuf[sizeof(ipfw_obj_header)];
	char *tname;
	int c, error;
	ipfw_obj_header *oh;
	ipfw_obj_ntlv *ntlv;
	ipfw_xtable_info xi;
	size_t tnamelen;
	socklen_t sz;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s",
	    &tname, &tnamelen) == FAILURE) {
		RETURN_NULL();
	}

	if (tnamelen == 0)
		RETURN_NULL();

	memset(xbuf, 0, sizeof(*oh));
	oh = (ipfw_obj_header *)xbuf;

	ntlv = &oh->ntlv;
	ntlv->head.type = IPFW_TLV_TBL_NAME;
	ntlv->head.length = sizeof(ipfw_obj_ntlv);
	ntlv->idx = 1;
	ntlv->set = 0;
	strlcpy(ntlv->name, tname, sizeof(ntlv->name));
	oh->idx = 1;

	if (table_get_info(oh, &xi) != 0)
		RETURN_NULL();

	sz = 0;
	oh = NULL;
	for (c = 0; c < 8; c++) {
		if (sz < xi.size)
			sz = xi.size + 44;
		if (oh != NULL)
			free(oh);
		if ((oh = calloc(1, sz)) == NULL)
			continue;
		memcpy(oh, xbuf, sizeof(*oh));
		oh->opheader.opcode = IP_FW_TABLE_XLIST;
		oh->opheader.version = 1; /* Current version */

		error = getsockopt(PFSENSE_G(ipfw), IPPROTO_IP, IP_FW3,
		    &oh->opheader, &sz);
		if (error != 0) {
			if (errno == ENOMEM)
				continue;
			free(oh);
			RETURN_NULL();
		}

		break;
	}

	if (error == 0) {
		array_init(return_value);
		table_show_list(return_value, oh);
	}
	free(oh);
}

PHP_FUNCTION(pfSense_ipfw_table_lookup)
{
	char xbuf[sizeof(ipfw_obj_header) + sizeof(ipfw_obj_tentry)];
	char *arg, *tname;
	ipfw_obj_header *oh;
	ipfw_obj_ntlv *ntlv;
	ipfw_obj_tentry *tent;
	ipfw_xtable_info xi;
	size_t arglen, tnamelen;
	socklen_t sz;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "ss",
	    &tname, &tnamelen, &arg, &arglen) == FAILURE) {
		RETURN_NULL();
	}

	if (tnamelen == 0 || arglen == 0)
		RETURN_NULL();

	memset(xbuf, 0, sizeof(*oh));
	oh = (ipfw_obj_header *)xbuf;
	oh->opheader.opcode = IP_FW_TABLE_XFIND;

	ntlv = &oh->ntlv;
	ntlv->head.type = IPFW_TLV_TBL_NAME;
	ntlv->head.length = sizeof(ipfw_obj_ntlv);
	ntlv->idx = 1;
	ntlv->set = 0;
	strlcpy(ntlv->name, tname, sizeof(ntlv->name));
	oh->idx = 1;

	if (table_get_info(oh, &xi) != 0)
		RETURN_NULL();

	tent = (ipfw_obj_tentry *)(oh + 1);
	memset(tent, 0, sizeof(*tent));
	tent->head.length = sizeof(*tent);
	tent->idx = 1;

	if (tentry_fill_key(arg, xi.type, tent) == -1)
		RETURN_NULL();
	ntlv->type = xi.type;

	sz = sizeof(xbuf);
	if (getsockopt(PFSENSE_G(ipfw), IPPROTO_IP, IP_FW3,
	    &oh->opheader, &sz) != 0)
		RETURN_NULL();

	if (sz < sizeof(xbuf))
		RETURN_NULL();

	array_init(return_value);
	table_show_entry(return_value, &xi, tent);
}

PHP_FUNCTION(pfSense_ipfw_table_zerocnt)
{
	char xbuf[sizeof(ipfw_obj_header) + sizeof(ipfw_obj_tentry)];
	char *arg, *tname;
	ipfw_obj_header *oh;
	ipfw_obj_ntlv *ntlv;
	ipfw_obj_tentry *tent;
	ipfw_xtable_info xi;
	size_t arglen, tnamelen;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC,
	    "ss", &tname, &tnamelen, &arg, &arglen) == FAILURE)
		RETURN_FALSE;
	if (tnamelen == 0 || arglen == 0)
		RETURN_FALSE;

	memset(xbuf, 0, sizeof(*oh));
	oh = (ipfw_obj_header *)xbuf;
	oh->opheader.opcode = IP_FW_TABLE_XZEROCNT;

	ntlv = &oh->ntlv;
	ntlv->head.type = IPFW_TLV_TBL_NAME;
	ntlv->head.length = sizeof(ipfw_obj_ntlv);
	ntlv->idx = 1;
	ntlv->set = 0;
	strlcpy(ntlv->name, tname, sizeof(ntlv->name));
	oh->idx = 1;

	if (table_get_info(oh, &xi) != 0)
		RETURN_FALSE;

	tent = (ipfw_obj_tentry *)(oh + 1);
	memset(tent, 0, sizeof(*tent));
	tent->head.length = sizeof(*tent);
	tent->idx = 1;

	if (tentry_fill_key(arg, xi.type, tent) == -1)
		RETURN_FALSE;
	ntlv->type = xi.type;

	if (setsockopt(PFSENSE_G(ipfw), IPPROTO_IP, IP_FW3,
	    &oh->opheader, sizeof(xbuf)) != 0)
		RETURN_FALSE;

	RETURN_TRUE;
}

/*
 * Compare table names.
 * Honor number comparison.
 */
static int
stringnum_cmp(const char *a, const char *b)
{
	int la, lb;

	la = strlen(a);
	lb = strlen(b);

	if (la > lb)
		return (1);
	else if (la < lb)
		return (-01);

	return (strcmp(a, b));
}

static int
tablename_cmp(const void *a, const void *b)
{
	ipfw_xtable_info *ia, *ib;

	ia = (ipfw_xtable_info *)a;
	ib = (ipfw_xtable_info *)b;

	return (stringnum_cmp(ia->tablename, ib->tablename));
}

PHP_FUNCTION(pfSense_ipfw_tables_list)
{
	int i, error;
	ipfw_obj_lheader *olh;
	ipfw_xtable_info *info;
	socklen_t sz;
	zval tinfo;

	/* Start with reasonable default */
	sz = sizeof(*olh) + 16 * sizeof(ipfw_xtable_info);

	for (;;) {
		if ((olh = calloc(1, sz)) == NULL)
			RETURN_NULL();

		olh->size = sz;
		olh->opheader.opcode = IP_FW_TABLES_XLIST;
		error = getsockopt(PFSENSE_G(ipfw), IPPROTO_IP, IP_FW3,
		    &olh->opheader, &sz);
		if (error != 0) {
			sz = olh->size;
			free(olh);
			if (errno != ENOMEM)
				RETURN_NULL();
			continue;
		}

		qsort(olh + 1, olh->count, olh->objsize, tablename_cmp);

		array_init(return_value);
		info = (ipfw_xtable_info *)(olh + 1);
		for (i = 0; i < olh->count; i++) {
			array_init(&tinfo);
			table_tinfo(&tinfo, info);

			add_next_index_zval(return_value, &tinfo);
			info = (ipfw_xtable_info *)((caddr_t)(info) + olh->objsize);
		}

		free(olh);
		break;
	}
}
#endif

#ifdef ETHERSWITCH_FUNCTIONS
static int
etherswitch_dev_is_valid(char *dev)
{
	char *ep;
	long unit;

	if (dev == NULL || strlen(dev) <= 16 ||
	    strncmp(dev, "/dev/etherswitch", 16) != 0) {
		return (-1);
	}
	unit = strtol(dev + 16, &ep, 0);
	if (*(dev + 16) != '\0' && ep != NULL && *ep != '\0')
		return (-1);

	return ((int)unit);
}

PHP_FUNCTION(pfSense_etherswitch_getinfo)
{
	char *dev, *vlan_mode;
	etherswitch_conf_t conf;
	etherswitch_info_t info;
	int fd, i;
	size_t devlen;
	zval caps, pmask, swcaps;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &dev, &devlen) == FAILURE)
		RETURN_NULL();
	if (devlen == 0)
		dev = "/dev/etherswitch0";
	if (etherswitch_dev_is_valid(dev) < 0)
		RETURN_NULL();
	fd = open(dev, O_RDONLY);
	if (fd == -1)
		RETURN_NULL();
	memset(&info, 0, sizeof(info));
	if (ioctl(fd, IOETHERSWITCHGETINFO, &info) != 0) {
		close(fd);
		RETURN_NULL();
	}
	memset(&conf, 0, sizeof(conf));
	if (ioctl(fd, IOETHERSWITCHGETCONF, &conf) != 0) {
		close(fd);
		RETURN_NULL();
	}
	close(fd);

	array_init(return_value);
	add_assoc_string(return_value, "name", info.es_name);
	add_assoc_long(return_value, "nports", info.es_nports);
	add_assoc_long(return_value, "nlaggroups", info.es_nlaggroups);
	add_assoc_long(return_value, "nvlangroups", info.es_nvlangroups);

	array_init(&caps);
	if (info.es_vlan_caps & ETHERSWITCH_VLAN_ISL)
		add_assoc_long(&caps, "ISL", 1);
	if (info.es_vlan_caps & ETHERSWITCH_VLAN_PORT)
		add_assoc_long(&caps, "PORT", 1);
	if (info.es_vlan_caps & ETHERSWITCH_VLAN_DOT1Q)
		add_assoc_long(&caps, "DOT1Q", 1);
	if (info.es_vlan_caps & ETHERSWITCH_VLAN_DOT1Q_4K)
		add_assoc_long(&caps, "DOT1Q4K", 1);
	if (info.es_vlan_caps & ETHERSWITCH_VLAN_DOUBLE_TAG)
		add_assoc_long(&caps, "QinQ", 1);
	add_assoc_zval(return_value, "caps", &caps);

	array_init(&swcaps);
	if (info.es_switch_caps & ETHERSWITCH_CAPS_PORTS_MASK)
		add_assoc_long(&swcaps, "PORTS_MASK", 1);
	if (info.es_switch_caps & ETHERSWITCH_CAPS_LAGG)
		add_assoc_long(&swcaps, "LAGG", 1);
	if (info.es_switch_caps & ETHERSWITCH_CAPS_PSTATE)
		add_assoc_long(&swcaps, "PSTATE", 1);
	add_assoc_zval(return_value, "switch_caps", &swcaps);

	if (info.es_switch_caps & ETHERSWITCH_CAPS_PORTS_MASK) {
		array_init(&pmask);
		for (i = 0; i < info.es_nports; i++)
			if ((info.es_ports_mask[i / 32] & (1 << (i % 32))) != 0)
				add_index_bool(&pmask, i, 1);
		add_assoc_zval(return_value, "ports_mask", &pmask);
	}

	switch(conf.vlan_mode) {
	case ETHERSWITCH_VLAN_ISL:
		vlan_mode = "ISL";
		break;
	case ETHERSWITCH_VLAN_PORT:
		vlan_mode = "PORT";
		break;
	case ETHERSWITCH_VLAN_DOT1Q:
		vlan_mode = "DOT1Q";
		break;
	case ETHERSWITCH_VLAN_DOT1Q_4K:
		vlan_mode = "DOT1Q4K";
		break;
	case ETHERSWITCH_VLAN_DOUBLE_TAG:
		vlan_mode = "QinQ";
		break;
	default:
		vlan_mode = "Unknown";
	}
	add_assoc_string(return_value, "vlan_mode", vlan_mode);
}

#define	IFMEDIAREQ_NULISTENTRIES	256

PHP_FUNCTION(pfSense_etherswitch_getport)
{
	char buf[128], *dev;
	etherswitch_conf_t conf;
	etherswitch_port_t p;
	int fd, ifm_ulist[IFMEDIAREQ_NULISTENTRIES];
	size_t devlen;
	zend_long port;
	zval flags, media, state;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "sl", &dev,
	    &devlen, &port) == FAILURE)
		RETURN_NULL();
	if (devlen == 0)
		dev = "/dev/etherswitch0";
	if (etherswitch_dev_is_valid(dev) < 0)
		RETURN_NULL();
	fd = open(dev, O_RDONLY);
	if (fd == -1)
		RETURN_NULL();

	memset(&conf, 0, sizeof(conf));
	if (ioctl(fd, IOETHERSWITCHGETCONF, &conf) != 0) {
		close(fd);
		RETURN_NULL();
	}
	memset(&p, 0, sizeof(p));
	p.es_port = port;
	p.es_ifmr.ifm_ulist = ifm_ulist;
	p.es_ifmr.ifm_count = IFMEDIAREQ_NULISTENTRIES;
	if (ioctl(fd, IOETHERSWITCHGETPORT, &p) != 0) {
		close(fd);
		RETURN_NULL();
	}
	close(fd);

	array_init(return_value);
	add_assoc_long(return_value, "port", p.es_port);
	if (conf.vlan_mode == ETHERSWITCH_VLAN_DOT1Q)
		add_assoc_long(return_value, "pvid", p.es_pvid);
	add_assoc_string(return_value, "status",
	    (p.es_ifmr.ifm_status & IFM_ACTIVE) ? "active" : "no carrier");

	array_init(&state);
	if (p.es_state & ETHERSWITCH_PSTATE_DISABLED)
		add_assoc_long(&state, "DISABLED", 1);
	if (p.es_state & ETHERSWITCH_PSTATE_BLOCKING)
		add_assoc_long(&state, "BLOCKING", 1);
	if (p.es_state & ETHERSWITCH_PSTATE_LEARNING)
		add_assoc_long(&state, "LEARNING", 1);
	if (p.es_state & ETHERSWITCH_PSTATE_FORWARDING)
		add_assoc_long(&state, "FORWARDING", 1);
	add_assoc_zval(return_value, "state", &state);

	array_init(&flags);
	if (p.es_flags & ETHERSWITCH_PORT_CPU)
		add_assoc_long(&flags, "HOST", 1);
	if (p.es_flags & ETHERSWITCH_PORT_STRIPTAG)
		add_assoc_long(&flags, "STRIPTAG", 1);
	if (p.es_flags & ETHERSWITCH_PORT_ADDTAG)
		add_assoc_long(&flags, "ADDTAG", 1);
	if (p.es_flags & ETHERSWITCH_PORT_FIRSTLOCK)
		add_assoc_long(&flags, "FIRSTLOCK", 1);
	if (p.es_flags & ETHERSWITCH_PORT_DROPTAGGED)
		add_assoc_long(&flags, "DROPTAGGED", 1);
	if (p.es_flags & ETHERSWITCH_PORT_DROPUNTAGGED)
		add_assoc_long(&flags, "DROPUNTAGGED", 1);
	if (p.es_flags & ETHERSWITCH_PORT_DOUBLE_TAG)
		add_assoc_long(&flags, "QinQ", 1);
	if (p.es_flags & ETHERSWITCH_PORT_INGRESS)
		add_assoc_long(&flags, "INGRESS", 1);
	add_assoc_zval(return_value, "flags", &flags);

	array_init(&media);
	memset(buf, 0, sizeof(buf));
	print_media_word(buf, sizeof(buf), p.es_ifmr.ifm_current, 1);
	add_assoc_string(&media, "current", buf);
	if (p.es_ifmr.ifm_active != p.es_ifmr.ifm_current) {
		memset(buf, 0, sizeof(buf));
		print_media_word(buf, sizeof(buf), p.es_ifmr.ifm_active, 0);
		add_assoc_string(&media, "active", buf);
	}
	add_assoc_zval(return_value, "media", &media);
}

PHP_FUNCTION(pfSense_etherswitch_setport)
{
	char *dev;
	etherswitch_port_t p;
	int fd;
	zend_long port, pvid;
	size_t devlen;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "sll", &dev,
	    &devlen, &port, &pvid) == FAILURE)
		RETURN_FALSE;
	if (devlen == 0)
		dev = "/dev/etherswitch0";
	if (etherswitch_dev_is_valid(dev) < 0)
		RETURN_FALSE;
	fd = open(dev, O_RDONLY);
	if (fd == -1)
		RETURN_FALSE;

	memset(&p, 0, sizeof(p));
	p.es_port = port;
	if (ioctl(fd, IOETHERSWITCHGETPORT, &p) != 0) {
		close(fd);
		RETURN_FALSE;
	}
	if (pvid >= 0 && pvid <= 4094)
		p.es_pvid = pvid;

	/* XXX - ports flags */

	if (ioctl(fd, IOETHERSWITCHSETPORT, &p) != 0) {
		close(fd);
		RETURN_FALSE;
	}
	close(fd);

	RETURN_TRUE;
}

PHP_FUNCTION(pfSense_etherswitch_setport_state)
{
	char *dev, *state;
	etherswitch_port_t p;
	int fd;
	size_t devlen;
	zend_long port, statelen;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "sls", &dev,
	    &devlen, &port, &state, &statelen) == FAILURE)
		RETURN_FALSE;
	if (statelen == 0)
		RETURN_FALSE;
	if (devlen == 0)
		dev = "/dev/etherswitch0";
	if (etherswitch_dev_is_valid(dev) < 0)
		RETURN_FALSE;
	fd = open(dev, O_RDONLY);
	if (fd == -1)
		RETURN_FALSE;

	memset(&p, 0, sizeof(p));
	p.es_port = port;
	if (ioctl(fd, IOETHERSWITCHGETPORT, &p) != 0) {
		close(fd);
		RETURN_FALSE;
	}
	if (strcasecmp(state, "forwarding") == 0)
		p.es_state = ETHERSWITCH_PSTATE_FORWARDING;
	else if (strcasecmp(state, "blocking") == 0)
		p.es_state = ETHERSWITCH_PSTATE_BLOCKING;
	else if (strcasecmp(state, "learning") == 0)
		p.es_state = ETHERSWITCH_PSTATE_LEARNING;
	else if (strcasecmp(state, "disabled") == 0)
		p.es_state = ETHERSWITCH_PSTATE_DISABLED;
	if (ioctl(fd, IOETHERSWITCHSETPORT, &p) != 0) {
		close(fd);
		RETURN_FALSE;
	}
	close(fd);

	RETURN_TRUE;
}

PHP_FUNCTION(pfSense_etherswitch_getlaggroup)
{
	char buf[32], *dev;
	etherswitch_info_t info;
	etherswitch_laggroup_t lg;
	int fd, i;
	size_t devlen;
	zend_long laggroup;
	zval members;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "sl", &dev,
	    &devlen, &laggroup) == FAILURE)
		RETURN_NULL();
	if (devlen == 0)
		dev = "/dev/etherswitch0";
	if (etherswitch_dev_is_valid(dev) < 0)
		RETURN_NULL();
	fd = open(dev, O_RDONLY);
	if (fd == -1)
		RETURN_NULL();

	memset(&info, 0, sizeof(info));
	if (ioctl(fd, IOETHERSWITCHGETINFO, &info) != 0) {
		close(fd);
		RETURN_NULL();
	}
	if ((info.es_switch_caps & ETHERSWITCH_CAPS_LAGG) == 0) {
		close(fd);
		RETURN_NULL();
	}
	if (laggroup >= info.es_nlaggroups) {
		close(fd);
		RETURN_NULL();
	}
	memset(&lg, 0, sizeof(lg));
	lg.es_laggroup = laggroup;
	if (ioctl(fd, IOETHERSWITCHGETLAGGROUP, &lg) != 0) {
		close(fd);
		RETURN_NULL();
	}
	close(fd);
	if (lg.es_lagg_valid == 0)
		RETURN_NULL();

	array_init(return_value);
	add_assoc_long(return_value, "laggroup", lg.es_laggroup);

	array_init(&members);
	for (i = 0; i < info.es_nports; i++) {
		if ((lg.es_member_ports & ETHERSWITCH_PORTMASK(i)) != 0) {
			memset(buf, 0, sizeof(buf));
			snprintf(buf, sizeof(buf) - 1, "%d", i);
			add_assoc_long(&members, buf, 1);
		}
	}
	add_assoc_zval(return_value, "members", &members);
}

PHP_FUNCTION(pfSense_etherswitch_getvlangroup)
{
	char buf[32], *dev, *tag;
	etherswitch_info_t info;
	etherswitch_vlangroup_t vg;
	int fd, i;
	zend_long vlangroup;
	size_t devlen;
	zval members;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "sl", &dev,
	    &devlen, &vlangroup) == FAILURE)
		RETURN_NULL();
	if (devlen == 0)
		dev = "/dev/etherswitch0";
	if (etherswitch_dev_is_valid(dev) < 0)
		RETURN_NULL();
	fd = open(dev, O_RDONLY);
	if (fd == -1)
		RETURN_NULL();

	memset(&info, 0, sizeof(info));
	if (ioctl(fd, IOETHERSWITCHGETINFO, &info) != 0) {
		close(fd);
		RETURN_NULL();
	}
	if (vlangroup >= info.es_nvlangroups) {
		close(fd);
		RETURN_NULL();
	}
	memset(&vg, 0, sizeof(vg));
	vg.es_vlangroup = vlangroup;
	if (ioctl(fd, IOETHERSWITCHGETVLANGROUP, &vg) != 0) {
		close(fd);
		RETURN_NULL();
	}
	close(fd);
	if ((vg.es_vid & ETHERSWITCH_VID_VALID) == 0)
		RETURN_NULL();

	array_init(return_value);
	add_assoc_long(return_value, "vlangroup", vg.es_vlangroup);
	add_assoc_long(return_value, "vid", vg.es_vid & ETHERSWITCH_VID_MASK);

	array_init(&members);
	for (i = 0; i < info.es_nports; i++) {
		if ((vg.es_member_ports & ETHERSWITCH_PORTMASK(i)) != 0) {
			if ((vg.es_untagged_ports & ETHERSWITCH_PORTMASK(i)) != 0)
				tag = "";
			else
				tag = "t";
			memset(buf, 0, sizeof(buf));
			snprintf(buf, sizeof(buf) - 1, "%d%s", i, tag);
			add_assoc_long(&members, buf, 1);
		}
	}
	add_assoc_zval(return_value, "members", &members);
}

PHP_FUNCTION(pfSense_etherswitch_setlaggroup)
{
	char *dev;
	etherswitch_info_t info;
	etherswitch_laggroup_t lag;
	int fd, members, port, tagged, untagged;
	size_t devlen;
	zval *zvar;
	zend_long laggroup;
	HashTable *hash1, *hash2;
	zval *val, *val2;
	zend_long lkey, lkey2;
	zend_string *skey, *skey2;

	zvar = NULL;
	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "sl|z", &dev,
	    &devlen, &laggroup, &zvar) == FAILURE)
		RETURN_LONG(-1);
	if (laggroup < 0)
		RETURN_LONG(-1);
	if (devlen == 0)
		dev = "/dev/etherswitch0";
	if (etherswitch_dev_is_valid(dev) < 0)
		RETURN_LONG(-1);
	fd = open(dev, O_RDONLY);
	if (fd == -1)
		RETURN_LONG(-1);
	memset(&info, 0, sizeof(info));
	if (ioctl(fd, IOETHERSWITCHGETINFO, &info) != 0) {
		close(fd);
		RETURN_LONG(-1);
	}
	if (laggroup >= info.es_nvlangroups) {
		close(fd);
		RETURN_LONG(-1);
	}

	members = untagged = 0;
	if (zvar != NULL && Z_TYPE_P(zvar) == IS_ARRAY) {
		hash1 = Z_ARRVAL_P(zvar);

		ZEND_HASH_FOREACH_KEY_VAL(hash1, lkey, skey, val) {
			if (skey != NULL || (Z_TYPE_P(val) != IS_ARRAY)) {
				continue;
			}

			port = lkey;
			if (port < 0 || port >= info.es_nports) {
				continue;
			}

			hash2 = Z_ARRVAL_P(val);
			tagged = 0;
			ZEND_HASH_FOREACH_KEY_VAL(hash2, lkey2, skey2, val2) {
				if (!skey2 || Z_TYPE_P(val2) != IS_LONG) {
					continue;
				}
				if (strlen(ZSTR_VAL(skey2)) == 6 && strcasecmp(ZSTR_VAL(skey2), "tagged") == 0 && Z_LVAL_P(val2) != 0) {
					tagged = 1;
				}
			} ZEND_HASH_FOREACH_END();

			members |= (1 << port);
			if (!tagged)
				untagged |= (1 << port);

		} ZEND_HASH_FOREACH_END();
	}

	memset(&lag, 0, sizeof(lag));
	lag.es_laggroup = laggroup;
	if (ioctl(fd, IOETHERSWITCHGETLAGGROUP, &lag) != 0) {
		close(fd);
		RETURN_LONG(-1);
	}
	lag.es_member_ports = members;
	lag.es_untagged_ports = untagged;
	if (ioctl(fd, IOETHERSWITCHSETLAGGROUP, &lag) != 0) {
		close(fd);
		RETURN_LONG(-1);
	}
	close(fd);
	RETURN_LONG(0);
}

PHP_FUNCTION(pfSense_etherswitch_setvlangroup)
{
	char *dev;
	etherswitch_info_t info;
	etherswitch_vlangroup_t vg;
	int fd, i, members, port, tagged, untagged;
	size_t devlen;
	zend_long vlan, vlangroup;
	zval *zvar;
	HashTable *hash1, *hash2;
	zval *val, *val2;
	zend_long lkey, lkey2;
	zend_string *skey, *skey2;

	zvar = NULL;
	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "sll|z", &dev,
	    &devlen, &vlangroup, &vlan, &zvar) == FAILURE)
		RETURN_LONG(-1);
	if ((vlan & ~ETHERSWITCH_VID_MASK) != 0)
		RETURN_LONG(-1);
	/* vlangroup == -1 is only valid with a vlanid. */
	if (vlangroup == -1 && vlan == 0)
		RETURN_LONG(-1);
	if (devlen == 0)
		dev = "/dev/etherswitch0";
	if (etherswitch_dev_is_valid(dev) < 0)
		RETURN_LONG(-1);
	fd = open(dev, O_RDONLY);
	if (fd == -1)
		RETURN_LONG(-1);
	memset(&info, 0, sizeof(info));
	if (ioctl(fd, IOETHERSWITCHGETINFO, &info) != 0) {
		close(fd);
		RETURN_LONG(-1);
	}
	if (vlangroup != -1 && vlangroup >= info.es_nvlangroups) {
		close(fd);
		RETURN_LONG(-1);
	}

	/* If we are setting a vlan id, parse switch ports members. */
	members = untagged = 0;
	if (vlan != 0 && zvar != NULL && Z_TYPE_P(zvar) == IS_ARRAY) {
		hash1 = Z_ARRVAL_P(zvar);

		ZEND_HASH_FOREACH_KEY_VAL(hash1, lkey, skey, val) {
			if (skey != NULL || (Z_TYPE_P(val) != IS_ARRAY)) {
				continue;
			}

			port = lkey;

			if (port < 0 || port >= info.es_nports) {
				continue;
			}

			hash2 = Z_ARRVAL_P(val);
			tagged = 0;

			ZEND_HASH_FOREACH_KEY_VAL(hash2, lkey2, skey2, val2) {
				if (!skey2 || Z_TYPE_P(val2) != IS_LONG) {
					continue;
				}

				if (strlen(ZSTR_VAL(skey2)) == 6 && strcasecmp(ZSTR_VAL(skey2), "tagged") == 0 && Z_LVAL_P(val2) != 0) {
					tagged = 1;
				}

			} ZEND_HASH_FOREACH_END();

			members |= (1 << port);

			if (!tagged)
				untagged |= (1 << port);

		} ZEND_HASH_FOREACH_END();
	}

	/*
	 * Find the first unused vlangroup.  Happens only when adding a
	 * vlangroup.
	 */
	if (vlangroup == -1) {
		for (i = 0; i < info.es_nvlangroups; i++) {
			memset(&vg, 0, sizeof(vg));
			vg.es_vlangroup = i;
			if (ioctl(fd, IOETHERSWITCHGETVLANGROUP, &vg) != 0) {
				close(fd);
				RETURN_LONG(-1);
			}
			if ((vg.es_vid & ETHERSWITCH_VID_VALID) == 0) {
				vlangroup = i;
				break;
			}
		}
		if (vlangroup == -1) {
			close(fd);
			RETURN_LONG(-1);
		}
	}

	memset(&vg, 0, sizeof(vg));
	vg.es_vlangroup = vlangroup;
	if (ioctl(fd, IOETHERSWITCHGETVLANGROUP, &vg) != 0) {
		close(fd);
		RETURN_LONG(-1);
	}
	vg.es_vid = vlan;
	vg.es_member_ports = members;
	vg.es_untagged_ports = untagged;
	if (ioctl(fd, IOETHERSWITCHSETVLANGROUP, &vg) != 0) {
		close(fd);
		RETURN_LONG(-1);
	}
	close(fd);
	RETURN_LONG(vlangroup);
}

PHP_FUNCTION(pfSense_etherswitch_setmode)
{
	char *dev, *mode;
	etherswitch_conf_t conf;
	etherswitch_info_t info;
	int fd;
	size_t devlen, modelen;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "ss", &dev,
	    &devlen, &mode, &modelen) == FAILURE)
		RETURN_LONG(-1);
	if (modelen == 0)
		RETURN_LONG(-1);
	if (devlen == 0)
		dev = "/dev/etherswitch0";
	if (etherswitch_dev_is_valid(dev) < 0)
		RETURN_LONG(-1);
	fd = open(dev, O_RDONLY);
	if (fd == -1)
		RETURN_LONG(-1);
	/* Just to check the Switch. */
	memset(&info, 0, sizeof(info));
	if (ioctl(fd, IOETHERSWITCHGETINFO, &info) != 0) {
		close(fd);
		RETURN_LONG(-1);
	}

	bzero(&conf, sizeof(conf));
	conf.cmd = ETHERSWITCH_CONF_VLAN_MODE;
	if (strcasecmp(mode, "isl") == 0)
		conf.vlan_mode = ETHERSWITCH_VLAN_ISL;
	else if (strcasecmp(mode, "port") == 0)
		conf.vlan_mode = ETHERSWITCH_VLAN_PORT;
	else if (strcasecmp(mode, "dot1q") == 0)
		conf.vlan_mode = ETHERSWITCH_VLAN_DOT1Q;
	else if (strcasecmp(mode, "dot1q4k") == 0)
		conf.vlan_mode = ETHERSWITCH_VLAN_DOT1Q_4K;
	else if (strcasecmp(mode, "qinq") == 0)
		conf.vlan_mode = ETHERSWITCH_VLAN_DOUBLE_TAG;
	else
		conf.vlan_mode = 0;
	if (ioctl(fd, IOETHERSWITCHSETCONF, &conf) != 0) {
		close(fd);
		RETURN_LONG(-1);
	}
	close(fd);
	RETURN_LONG(0);
}
#endif

#ifdef DHCP_INTEGRATION
PHP_FUNCTION(pfSense_open_dhcpd)
{
	omapi_data *data;
	char *key, *addr, *name;
	size_t key_len, addr_len, name_len;
	zend_long port;
	dhcpctl_status status;
	dhcpctl_handle auth = dhcpctl_null_handle, conn = dhcpctl_null_handle;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "sssl", &name, &name_len, &key, &key_len, &addr, &addr_len, &port) == FAILURE) {
		RETURN_FALSE;
	}

	status = dhcpctl_new_authenticator(&auth, name, "hmac-md5", key, key_len);
	if (status != ISC_R_SUCCESS) {
		//php_printf("Failed to get aythenticator: %s - %s\n", isc_result_totext(status), key);
		RETURN_NULL();
	}

	status = dhcpctl_connect(&conn, addr, (int)port, auth);
	if (status != ISC_R_SUCCESS) {
		//php_printf("Error occured during connecting: %s\n", isc_result_totext(status));
		RETURN_NULL();
	}

	data = emalloc(sizeof(*data));
	data->handle = conn;

	ZEND_REGISTER_RESOURCE(return_value, data, pfSense_dhcpd);
}

PHP_FUNCTION(pfSense_register_lease)
{
	dhcpctl_status status = ISC_R_SUCCESS;
	dhcpctl_status status2 = ISC_R_SUCCESS;
	dhcpctl_handle hp = NULL;
	struct ether_addr *ds;
	struct in_addr nds;
	char *mac, *ip, *name;
	size_t mac_len, ip_len, name_len;
	zval *res;
	omapi_data *conn;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "zsss", &res, &name, &name_len, &mac, &mac_len, &ip, &ip_len) == FAILURE) {
		RETURN_FALSE;
	}

	ZEND_FETCH_RESOURCE(conn, omapi_data *, &res, -1, PHP_PFSENSE_RES_NAME, pfSense_dhcpd);
	ZEND_VERIFY_RESOURCE(conn);

	if ((status = dhcpctl_new_object(&hp, conn->handle, "host")) != ISC_R_SUCCESS) {
		//php_printf("1Error occured during connecting: %s\n", isc_result_totext(status));
		RETURN_FALSE;
	}

	inet_aton(ip, &nds);
	if ((status = dhcpctl_set_data_value(hp, (char *)&nds, sizeof(struct in_addr), "ip-address")) != ISC_R_SUCCESS) {
		//php_printf("3Error occured during connecting: %s\n", isc_result_totext(status));
		omapi_object_dereference(&hp,__FILE__,__LINE__);
		RETURN_FALSE;
	}

	if ((status = dhcpctl_set_string_value(hp, name, "name")) != ISC_R_SUCCESS) {
		//php_printf("4Error occured during connecting: %s\n", isc_result_totext(status));
		omapi_object_dereference(&hp,__FILE__,__LINE__);
		RETURN_FALSE;
	}

	if (!(ds = ether_aton(mac)))
		RETURN_FALSE;
	if ((status = dhcpctl_set_data_value(hp, (u_char *)ds, sizeof(struct ether_addr), "hardware-address")) != ISC_R_SUCCESS) {
		//php_printf("2Error occured during connecting: %s\n", isc_result_totext(status));
		omapi_object_dereference(&hp,__FILE__,__LINE__);
		RETURN_FALSE;
	}

	if ((status= dhcpctl_set_int_value(hp, 1,"hardware-type")) != ISC_R_SUCCESS)  {
		//php_printf("2Error occured during connecting: %s\n", isc_result_totext(status));
		omapi_object_dereference(&hp,__FILE__,__LINE__);
		RETURN_FALSE;
	}

	//php_printf("Coonection handle %d\n", conn->handle);
	if ((status = dhcpctl_open_object(hp, conn->handle, DHCPCTL_CREATE|DHCPCTL_EXCL)) != ISC_R_SUCCESS) {
		//php_printf("5Error occured during connecting: %s\n", isc_result_totext(status));
		omapi_object_dereference(&hp,__FILE__,__LINE__);
		RETURN_FALSE;
	}
	if ((status = dhcpctl_wait_for_completion(hp, &status2)) != ISC_R_SUCCESS) {
		//php_printf("6Error occured during connecting: %s-  %s\n", isc_result_totext(status), isc_result_totext(status2));
		omapi_object_dereference(&hp,__FILE__,__LINE__);
		RETURN_FALSE;
	}
	if (status2 != ISC_R_SUCCESS) {
		//php_printf("7Error occured during connecting: %s\n", isc_result_totext(status2));
		omapi_object_dereference(&hp,__FILE__,__LINE__);
		RETURN_FALSE;
	}

	omapi_object_dereference(&hp,__FILE__,__LINE__);

	RETURN_TRUE;
}

PHP_FUNCTION(pfSense_delete_lease)
{
	dhcpctl_status status;
	dhcpctl_status status2;
	dhcpctl_handle hp = NULL;
	dhcpctl_data_string ds = NULL;
	omapi_data_string_t *nds;
	char *mac;
	size_t mac_len;
	zval *res;
	omapi_data *conn;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &mac, &mac_len) == FAILURE) {
		RETURN_FALSE;
	}

	ZEND_FETCH_RESOURCE(conn, omapi_data *, &res, -1, PHP_PFSENSE_RES_NAME, pfSense_dhcpd);
	ZEND_VERIFY_RESOURCE(conn);

	if ((status = dhcpctl_new_object(&hp, conn->handle, "host")))
		RETURN_FALSE;

	if (mac) {
		omapi_data_string_new(&ds, sizeof(struct ether_addr), __FILE__, __LINE__);
		memcpy(ds->value,ether_aton(mac),sizeof(struct ether_addr));
		if ((status = dhcpctl_set_value(hp, ds, "hardware-address"))) {
			omapi_object_dereference(&hp,__FILE__,__LINE__);
			RETURN_FALSE;
		}
	} else
		RETURN_FALSE;

	if ((status = dhcpctl_open_object(hp, conn->handle, 0))) {
		omapi_object_dereference(&hp,__FILE__,__LINE__);
		RETURN_FALSE;
	}
	if ((status = dhcpctl_wait_for_completion(hp, &status2))) {
		omapi_object_dereference(&hp,__FILE__,__LINE__);
		RETURN_FALSE;
	}
	if (status2) {
		omapi_object_dereference(&hp,__FILE__,__LINE__);
		RETURN_FALSE;
	}
	if ((status = dhcpctl_object_remove(conn->handle, hp))) {
		omapi_object_dereference(&hp,__FILE__,__LINE__);
		RETURN_FALSE;
	}
	if ((status = dhcpctl_wait_for_completion(hp, &status2))) {
		omapi_object_dereference(&hp,__FILE__,__LINE__);
		RETURN_FALSE;
	}
	if (status2) {
		omapi_object_dereference(&hp,__FILE__,__LINE__);
		RETURN_FALSE;
	}
	omapi_object_dereference(&hp,__FILE__,__LINE__);

	RETURN_TRUE;
}

PHP_FUNCTION(pfSense_close_dhcpd)
{
	zval *data;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "r", &data) == FAILURE) {
		RETURN_FALSE;
	}

	zend_list_delete(Z_LVAL_P(data));

	RETURN_TRUE;
}
#endif

PHP_FUNCTION(pfSense_ip_to_mac)
{
	char *ip = NULL, *rifname = NULL;
	size_t ip_len, ifname_len = 0;

	int mib[6];
	size_t needed;
	char *lim, *buf, *next;
	struct rt_msghdr *rtm;
	struct sockaddr_inarp *sin2, addr;
	struct sockaddr_dl *sdl;
	char ifname[IF_NAMESIZE];
	int st, found_entry = 0;
	char outputbuf[128];

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s|s", &ip, &ip_len, &rifname, &ifname_len) == FAILURE)
		RETURN_NULL();

	bzero(&addr, sizeof(addr));
	if (!inet_pton(AF_INET, ip, &addr.sin_addr.s_addr))
		RETURN_NULL();
	addr.sin_len = sizeof(addr);
	addr.sin_family = AF_INET;

	mib[0] = CTL_NET;
	mib[1] = PF_ROUTE;
	mib[2] = 0;
	mib[3] = AF_INET;
	mib[4] = NET_RT_FLAGS;
#ifdef RTF_LLINFO
	mib[5] = RTF_LLINFO;
#else
	mib[5] = 0;
#endif
	if (sysctl(mib, 6, NULL, &needed, NULL, 0) < 0) {
		php_printf("route-sysctl-estimate");
		RETURN_NULL();
	}
	if (needed == 0)	/* empty table */
		RETURN_NULL();
	buf = NULL;
	for (;;) {
		buf = reallocf(buf, needed);
		if (buf == NULL) {
			php_printf("could not reallocate memory");
			free(buf);
			RETURN_NULL();
		}
		st = sysctl(mib, 6, buf, &needed, NULL, 0);
		if (st == 0 || errno != ENOMEM)
			break;
		needed += needed / 8;
	}
	if (st == -1)
		php_printf("actual retrieval of routing table");
	lim = buf + needed;
	for (next = buf; next < lim; next += rtm->rtm_msglen) {
		rtm = (struct rt_msghdr *)next;
		sin2 = (struct sockaddr_inarp *)(rtm + 1);
		sdl = (struct sockaddr_dl *)((char *)sin2 + SA_SIZE(sin2));
		if (rifname && if_indextoname(sdl->sdl_index, ifname) &&
		    strcmp(ifname, rifname))
			continue;
		if (addr.sin_addr.s_addr == sin2->sin_addr.s_addr) {
			found_entry = 1;
			break;
		}
	}

	if (found_entry == 0) {
		free(buf);
		RETURN_NULL();
	}

	array_init(return_value);
	bzero(outputbuf, sizeof outputbuf);
	ether_ntoa_r((struct ether_addr *)LLADDR(sdl), outputbuf);
	add_assoc_string(return_value, "macaddr", outputbuf);
	free(buf);
}

PHP_FUNCTION(pfSense_getall_interface_addresses)
{
	struct ifaddrs *ifdata, *mb;
	struct sockaddr_in *tmp;
	struct sockaddr_in6 *tmp6;
	char outputbuf[132];
	char *ifname;
	size_t ifname_len;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &ifname,
	    &ifname_len) == FAILURE)
		RETURN_NULL();

	if (getifaddrs(&ifdata) == -1)
		RETURN_NULL();

	array_init(return_value);

	for(mb = ifdata; mb != NULL && mb->ifa_addr != NULL; mb = mb->ifa_next) {
		if (ifname_len != strlen(mb->ifa_name))
			continue;
		if (strncmp(ifname, mb->ifa_name, ifname_len) != 0)
			continue;

		switch (mb->ifa_addr->sa_family) {
		case AF_INET:
			bzero(outputbuf, sizeof outputbuf);
			tmp = (struct sockaddr_in *)mb->ifa_addr;
			inet_ntop(AF_INET, (void *)&tmp->sin_addr, outputbuf,
			    sizeof(outputbuf));
			tmp = (struct sockaddr_in *)mb->ifa_netmask;
			unsigned char mask;
			const unsigned char *byte =
			    (unsigned char *)&tmp->sin_addr.s_addr;
			int i = 0, n = sizeof(tmp->sin_addr.s_addr);
			while (n--) {
				mask = ((unsigned char)-1 >> 1) + 1;
					do {
						if (mask & byte[n])
							i++;
						mask >>= 1;
					} while (mask);
			}
			snprintf(outputbuf + strlen(outputbuf),
			    sizeof(outputbuf) - strlen(outputbuf), "/%d", i);
			add_next_index_string(return_value, outputbuf);
			break;
		case AF_INET6:
			bzero(outputbuf, sizeof outputbuf);
			tmp6 = (struct sockaddr_in6 *)mb->ifa_addr;
			if (getnameinfo((struct sockaddr *)tmp6, tmp6->sin6_len,
			    outputbuf, sizeof(outputbuf), NULL, 0,
			    NI_NUMERICHOST))
				inet_ntop(AF_INET6, (void *)&tmp6->sin6_addr,
				    outputbuf, INET6_ADDRSTRLEN);
			tmp6 = (struct sockaddr_in6 *)mb->ifa_netmask;
			snprintf(outputbuf + strlen(outputbuf),
			    sizeof(outputbuf) - strlen(outputbuf), "/%d",
			    prefix(&tmp6->sin6_addr, sizeof(struct in6_addr)));
			add_next_index_string(return_value, outputbuf);
			break;
		}
	}
	freeifaddrs(ifdata);
}

PHP_FUNCTION(pfSense_get_interface_addresses)
{
	struct ifaddrs *ifdata, *mb;
	struct if_data *md;
	struct sockaddr_in *tmp;
	struct sockaddr_in6 *tmp6;
	struct sockaddr_dl *tmpdl;
	struct in6_ifreq ifr6;
	struct ifreq ifr;
	char outputbuf[128];
	char *ifname;
	size_t ifname_len;
	int llflag, addresscnt, addresscnt6;
	zval caps;
	zval encaps;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &ifname,
	    &ifname_len) == FAILURE)
		RETURN_NULL();

	if (getifaddrs(&ifdata) == -1)
		RETURN_NULL();

	addresscnt = 0;
	addresscnt6 = 0;
	array_init(return_value);

	for(mb = ifdata; mb != NULL && mb->ifa_addr != NULL; mb = mb->ifa_next) {
		if (ifname_len != strlen(mb->ifa_name))
			continue;
		if (strncmp(ifname, mb->ifa_name, ifname_len) != 0)
			continue;

		switch (mb->ifa_addr->sa_family) {
		case AF_INET:
			if (addresscnt > 0)
				break;
			bzero(outputbuf, sizeof outputbuf);
			tmp = (struct sockaddr_in *)mb->ifa_addr;
			inet_ntop(AF_INET, (void *)&tmp->sin_addr, outputbuf,
			    sizeof(outputbuf));
			add_assoc_string(return_value, "ipaddr", outputbuf);
			addresscnt++;
			tmp = (struct sockaddr_in *)mb->ifa_netmask;
			unsigned char mask;
			const unsigned char *byte =
			    (unsigned char *)&tmp->sin_addr.s_addr;
			int i = 0, n = sizeof(tmp->sin_addr.s_addr);
			while (n--) {
				mask = ((unsigned char)-1 >> 1) + 1;
					do {
						if (mask & byte[n])
							i++;
						mask >>= 1;
					} while (mask);
			}
			add_assoc_long(return_value, "subnetbits", i);

			bzero(outputbuf, sizeof outputbuf);
			inet_ntop(AF_INET, (void *)&tmp->sin_addr, outputbuf,
			    sizeof(outputbuf));
			add_assoc_string(return_value, "subnet", outputbuf);

			if (mb->ifa_flags & IFF_BROADCAST) {
				bzero(outputbuf, sizeof outputbuf);
				tmp = (struct sockaddr_in *)mb->ifa_broadaddr;
				inet_ntop(AF_INET, (void *)&tmp->sin_addr,
				    outputbuf, sizeof(outputbuf));
				add_assoc_string(return_value, "broadcast",
				    outputbuf);
			}

			if (mb->ifa_flags & IFF_POINTOPOINT) {
				tmp = (struct sockaddr_in *)mb->ifa_dstaddr;
				if (tmp != NULL && tmp->sin_family == AF_INET) {
					bzero(outputbuf, sizeof outputbuf);
					inet_ntop(AF_INET,
					    (void *)&tmp->sin_addr, outputbuf,
					    sizeof(outputbuf));
					add_assoc_string(return_value, "tunnel",
					    outputbuf);
				}
			}

			break;
		case AF_INET6:
			if (addresscnt6 > 0)
				break;
			bzero(outputbuf, sizeof outputbuf);
			tmp6 = (struct sockaddr_in6 *)mb->ifa_addr;
			if (IN6_IS_ADDR_LINKLOCAL(&tmp6->sin6_addr))
				break;
			inet_ntop(AF_INET6, (void *)&tmp6->sin6_addr, outputbuf,
			    sizeof(outputbuf));
			add_assoc_string(return_value, "ipaddr6", outputbuf);
			addresscnt6++;

			memset(&ifr6, 0, sizeof(ifr6));
			strncpy(ifr6.ifr_name, mb->ifa_name,
			    sizeof(ifr6.ifr_name));
			memcpy(&ifr6.ifr_ifru.ifru_addr, tmp6, tmp6->sin6_len);
			if (ioctl(PFSENSE_G(inets6),
			    SIOCGIFAFLAG_IN6, &ifr6) == 0) {
				llflag = ifr6.ifr_ifru.ifru_flags6;
				if ((llflag & IN6_IFF_TENTATIVE) != 0)
					add_assoc_long(return_value,
					    "tentative", 1);
			}

			tmp6 = (struct sockaddr_in6 *)mb->ifa_netmask;
			add_assoc_long(return_value, "subnetbits6",
			    prefix(&tmp6->sin6_addr, sizeof(struct in6_addr)));

			if (mb->ifa_flags & IFF_POINTOPOINT) {
				tmp6 = (struct sockaddr_in6 *)mb->ifa_dstaddr;
				if (tmp6 != NULL &&
				    tmp6->sin6_family == AF_INET6) {
					bzero(outputbuf, sizeof outputbuf);
					inet_ntop(AF_INET6,
					    (void *)&tmp6->sin6_addr, outputbuf,
					    sizeof(outputbuf));
					add_assoc_string(return_value,
					    "tunnel6", outputbuf);
				}
			}
			break;
		}

		if (mb->ifa_addr->sa_family != AF_LINK)
			continue;

		if (mb->ifa_flags & IFF_UP)
			add_assoc_string(return_value, "status", "up");
		else
			add_assoc_string(return_value, "status", "down");
		if (mb->ifa_flags & IFF_LINK0)
			add_assoc_long(return_value, "link0", 1);
		if (mb->ifa_flags & IFF_LINK1)
			add_assoc_long(return_value, "link1", 1);
		if (mb->ifa_flags & IFF_LINK2)
			add_assoc_long(return_value, "link2", 1);
		if (mb->ifa_flags & IFF_MULTICAST)
			add_assoc_long(return_value, "multicast", 1);
		if (mb->ifa_flags & IFF_LOOPBACK)
			add_assoc_long(return_value, "loopback", 1);
		if (mb->ifa_flags & IFF_POINTOPOINT)
			add_assoc_long(return_value, "pointtopoint", 1);
		if (mb->ifa_flags & IFF_PROMISC)
			add_assoc_long(return_value, "promisc", 1);
		if (mb->ifa_flags & IFF_PPROMISC)
			add_assoc_long(return_value, "permanentpromisc", 1);
		if (mb->ifa_flags & IFF_OACTIVE)
			add_assoc_long(return_value, "oactive", 1);
		if (mb->ifa_flags & IFF_ALLMULTI)
			add_assoc_long(return_value, "allmulti", 1);
		if (mb->ifa_flags & IFF_SIMPLEX)
			add_assoc_long(return_value, "simplex", 1);
		memset(&ifr, 0, sizeof(ifr));
		strncpy(ifr.ifr_name, ifname, sizeof(ifr.ifr_name));
		if (mb->ifa_data != NULL) {
			md = mb->ifa_data;
			if (md->ifi_link_state == LINK_STATE_UP)
				add_assoc_long(return_value, "linkstateup", 1);
			switch (md->ifi_type) {
			case IFT_IEEE80211:
				add_assoc_string(return_value, "iftype",
				    "wireless");
				break;
			case IFT_ETHER:
			case IFT_FASTETHER:
			case IFT_FASTETHERFX:
			case IFT_GIGABITETHERNET:
				if (ioctl(PFSENSE_G(s), SIOCG80211STATS,
				    (caddr_t)&ifr) == 0) {
					add_assoc_string(return_value, "iftype",
					    "wireless");
					/* Reset ifr after use. */
					memset(&ifr, 0, sizeof(ifr));
					strncpy(ifr.ifr_name, ifname, sizeof(ifr.ifr_name));
				} else {
					add_assoc_string(return_value, "iftype",
					    "ether");
				}
				break;
			case IFT_L2VLAN:
				add_assoc_string(return_value, "iftype",
				    "vlan");
				break;
			case IFT_BRIDGE:
				add_assoc_string(return_value, "iftype",
				    "bridge");
				break;
			case IFT_TUNNEL:
			case IFT_GIF:
#if (__FreeBSD_version < 1100000)
			case IFT_FAITH:
#endif
			case IFT_ENC:
			case IFT_PFLOG:
			case IFT_PFSYNC:
				add_assoc_string(return_value, "iftype",
				    "virtual");
				break;
			default:
				add_assoc_string(return_value, "iftype",
				    "other");
			}
		}

		array_init(&caps);
		array_init(&encaps);
		if (ioctl(PFSENSE_G(s), SIOCGIFMTU, (caddr_t)&ifr) == 0)
			add_assoc_long(return_value, "mtu", ifr.ifr_mtu);
		if (ioctl(PFSENSE_G(s), SIOCGIFCAP, (caddr_t)&ifr) == 0) {
			add_assoc_long(&caps, "flags", ifr.ifr_reqcap);
			if (ifr.ifr_reqcap & IFCAP_POLLING)
				add_assoc_long(&caps, "polling", 1);
			if (ifr.ifr_reqcap & IFCAP_RXCSUM)
				add_assoc_long(&caps, "rxcsum", 1);
			if (ifr.ifr_reqcap & IFCAP_TXCSUM)
				add_assoc_long(&caps, "txcsum", 1);
			if (ifr.ifr_reqcap & IFCAP_RXCSUM_IPV6)
				add_assoc_long(&caps, "rxcsum6", 1);
			if (ifr.ifr_reqcap & IFCAP_TXCSUM_IPV6)
				add_assoc_long(&caps, "txcsum6", 1);
			if (ifr.ifr_reqcap & IFCAP_VLAN_MTU)
				add_assoc_long(&caps, "vlanmtu", 1);
			if (ifr.ifr_reqcap & IFCAP_JUMBO_MTU)
				add_assoc_long(&caps, "jumbomtu", 1);
			if (ifr.ifr_reqcap & IFCAP_VLAN_HWTAGGING)
				add_assoc_long(&caps, "vlanhwtag", 1);
			if (ifr.ifr_reqcap & IFCAP_VLAN_HWCSUM)
				add_assoc_long(&caps, "vlanhwcsum", 1);
			if (ifr.ifr_reqcap & IFCAP_TSO4)
				add_assoc_long(&caps, "tso4", 1);
			if (ifr.ifr_reqcap & IFCAP_TSO6)
				add_assoc_long(&caps, "tso6", 1);
			if (ifr.ifr_reqcap & IFCAP_LRO)
				add_assoc_long(&caps, "lro", 1);
			if (ifr.ifr_reqcap & IFCAP_WOL_UCAST)
				add_assoc_long(&caps, "wolucast", 1);
			if (ifr.ifr_reqcap & IFCAP_WOL_MCAST)
				add_assoc_long(&caps, "wolmcast", 1);
			if (ifr.ifr_reqcap & IFCAP_WOL_MAGIC)
				add_assoc_long(&caps, "wolmagic", 1);
			if (ifr.ifr_reqcap & IFCAP_TOE4)
				add_assoc_long(&caps, "toe4", 1);
			if (ifr.ifr_reqcap & IFCAP_TOE6)
				add_assoc_long(&caps, "toe6", 1);
			if (ifr.ifr_reqcap & IFCAP_VLAN_HWFILTER)
				add_assoc_long(&caps, "vlanhwfilter", 1);

			add_assoc_long(&encaps, "flags", ifr.ifr_curcap);
			if (ifr.ifr_curcap & IFCAP_POLLING)
				add_assoc_long(&encaps, "polling", 1);
			if (ifr.ifr_curcap & IFCAP_RXCSUM)
				add_assoc_long(&encaps, "rxcsum", 1);
			if (ifr.ifr_curcap & IFCAP_TXCSUM)
				add_assoc_long(&encaps, "txcsum", 1);
			if (ifr.ifr_curcap & IFCAP_RXCSUM_IPV6)
				add_assoc_long(&encaps, "rxcsum6", 1);
			if (ifr.ifr_curcap & IFCAP_TXCSUM_IPV6)
				add_assoc_long(&encaps, "txcsum6", 1);
			if (ifr.ifr_curcap & IFCAP_VLAN_MTU)
				add_assoc_long(&encaps, "vlanmtu", 1);
			if (ifr.ifr_curcap & IFCAP_JUMBO_MTU)
				add_assoc_long(&encaps, "jumbomtu", 1);
			if (ifr.ifr_curcap & IFCAP_VLAN_HWTAGGING)
				add_assoc_long(&encaps, "vlanhwtag", 1);
			if (ifr.ifr_curcap & IFCAP_VLAN_HWCSUM)
				add_assoc_long(&encaps, "vlanhwcsum", 1);
			if (ifr.ifr_curcap & IFCAP_TSO4)
				add_assoc_long(&encaps, "tso4", 1);
			if (ifr.ifr_curcap & IFCAP_TSO6)
				add_assoc_long(&encaps, "tso6", 1);
			if (ifr.ifr_curcap & IFCAP_LRO)
				add_assoc_long(&encaps, "lro", 1);
			if (ifr.ifr_curcap & IFCAP_WOL_UCAST)
				add_assoc_long(&encaps, "wolucast", 1);
			if (ifr.ifr_curcap & IFCAP_WOL_MCAST)
				add_assoc_long(&encaps, "wolmcast", 1);
			if (ifr.ifr_curcap & IFCAP_WOL_MAGIC)
				add_assoc_long(&encaps, "wolmagic", 1);
			if (ifr.ifr_curcap & IFCAP_TOE4)
				add_assoc_long(&encaps, "toe4", 1);
			if (ifr.ifr_curcap & IFCAP_TOE6)
				add_assoc_long(&encaps, "toe6", 1);
			if (ifr.ifr_curcap & IFCAP_VLAN_HWFILTER)
				add_assoc_long(&encaps, "vlanhwfilter", 1);
		}

		add_assoc_zval(return_value, "caps", &caps);
		add_assoc_zval(return_value, "encaps", &encaps);

		tmpdl = (struct sockaddr_dl *)mb->ifa_addr;
		if (tmpdl->sdl_alen != ETHER_ADDR_LEN)
			continue;
		bzero(outputbuf, sizeof outputbuf);
		ether_ntoa_r((struct ether_addr *)LLADDR(tmpdl), outputbuf);
		add_assoc_string(return_value, "macaddr", outputbuf);

		if (tmpdl->sdl_type != IFT_ETHER)
			continue;
		memcpy(&ifr.ifr_addr, mb->ifa_addr,
		    sizeof(mb->ifa_addr->sa_len));
		ifr.ifr_addr.sa_family = AF_LOCAL;
		if (ioctl(PFSENSE_G(s), SIOCGHWADDR, &ifr) != 0)
			continue;

		bzero(outputbuf, sizeof outputbuf);
		ether_ntoa_r((const struct ether_addr *)&ifr.ifr_addr.sa_data,
		    outputbuf);
		add_assoc_string(return_value, "hwaddr", outputbuf);
	}
	freeifaddrs(ifdata);
}

PHP_FUNCTION(pfSense_bridge_add_member) {
	char *ifname, *ifchld;
	size_t ifname_len, ifchld_len;
	struct ifdrv drv;
	struct ifbreq req;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "ss", &ifname, &ifname_len, &ifchld, &ifchld_len) == FAILURE) {
		RETURN_NULL();
	}

	memset(&drv, 0, sizeof(drv));
	memset(&req, 0, sizeof(req));
	strlcpy(drv.ifd_name, ifname, sizeof(drv.ifd_name));
	strlcpy(req.ifbr_ifsname, ifchld, sizeof(req.ifbr_ifsname));
	drv.ifd_cmd = BRDGADD;
	drv.ifd_data = &req;
	drv.ifd_len = sizeof(req);
	if (ioctl(PFSENSE_G(s), SIOCSDRVSPEC, (caddr_t)&drv) < 0)
		RETURN_FALSE;

	RETURN_TRUE;
}

PHP_FUNCTION(pfSense_bridge_del_member) {
	char *ifname, *ifchld;
	size_t ifname_len, ifchld_len;
	struct ifdrv drv;
	struct ifbreq req;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "ss", &ifname, &ifname_len, &ifchld, &ifchld_len) == FAILURE) {
		RETURN_NULL();
	}

	memset(&drv, 0, sizeof(drv));
	memset(&req, 0, sizeof(req));
	strlcpy(drv.ifd_name, ifname, sizeof(drv.ifd_name));
	strlcpy(req.ifbr_ifsname, ifchld, sizeof(req.ifbr_ifsname));
	drv.ifd_cmd = BRDGDEL;
	drv.ifd_data = &req;
	drv.ifd_len = sizeof(req);
	if (ioctl(PFSENSE_G(s), SIOCSDRVSPEC, (caddr_t)&drv) < 0)
		RETURN_FALSE;

	RETURN_TRUE;
}

PHP_FUNCTION(pfSense_bridge_member_flags) {
	char *ifname, *ifchld;
	size_t ifname_len, ifchld_len;
	struct ifdrv drv;
	struct ifbreq req;
	zend_long flags = 0;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "ssl", &ifname, &ifname_len, &ifchld, &ifchld_len, &flags) == FAILURE) {
		RETURN_NULL();
	}

	memset(&drv, 0, sizeof(drv));
	memset(&req, 0, sizeof(req));
	strlcpy(drv.ifd_name, ifname, sizeof(drv.ifd_name));
	strlcpy(req.ifbr_ifsname, ifchld, sizeof(req.ifbr_ifsname));
	drv.ifd_cmd = BRDGGIFFLGS;
	drv.ifd_data = &req;
	drv.ifd_len = sizeof(req);
	if (ioctl(PFSENSE_G(s), SIOCGDRVSPEC, (caddr_t)&drv) < 0)
		RETURN_FALSE;

	if (flags < 0) {
		flags = -flags;
		req.ifbr_ifsflags &= ~(int)flags;
	} else
		req.ifbr_ifsflags |= (int)flags;

	drv.ifd_cmd = BRDGSIFFLGS;
	drv.ifd_data = &req;
	drv.ifd_len = sizeof(req);
	if (ioctl(PFSENSE_G(s), SIOCSDRVSPEC, (caddr_t)&drv) < 0)
		RETURN_FALSE;

	RETURN_TRUE;
}

PHP_FUNCTION(pfSense_interface_listget) {
	struct ifaddrs *ifdata, *mb;
	char *ifname;
	int ifname_len;
	zend_long flags = 0;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "|l", &flags) == FAILURE)
		RETURN_NULL();

	if (getifaddrs(&ifdata) == -1)
		RETURN_NULL();

	array_init(return_value);
	ifname = NULL;
	ifname_len = 0;
	for(mb = ifdata; mb != NULL; mb = mb->ifa_next) {

		if (flags != 0) {
			if (mb->ifa_flags & IFF_UP && flags < 0)
				continue;
			if (!(mb->ifa_flags & IFF_UP) && flags > 0)
				continue;
		}

		if (ifname != NULL && ifname_len == strlen(mb->ifa_name) && strcmp(ifname, mb->ifa_name) == 0)
			continue;
		ifname = mb->ifa_name;
		ifname_len = strlen(mb->ifa_name);

		add_next_index_string(return_value, mb->ifa_name);
	}

	freeifaddrs(ifdata);
}

static int interface_create(char *ifname, unsigned long op, zend_string **str, zval *return_value) {
	struct ifreq ifr;

	memset(&ifr, 0, sizeof(ifr));
	strlcpy(ifr.ifr_name, ifname, sizeof(ifr.ifr_name));

	*str = NULL;
	if (ioctl(PFSENSE_G(s), op, &ifr) == -1) {
		array_init(return_value);
		add_assoc_string(return_value, "error", "Could not create interface");
		return (-1);
	}
	*str = zend_string_init(ifr.ifr_name, strlen(ifr.ifr_name), 0);
	return (0);
}

PHP_FUNCTION(pfSense_interface_create) {
	char *ifname;
	size_t ifname_len;
	zend_string *str;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &ifname, &ifname_len) == FAILURE) {
		RETURN_NULL();
	}
	if (interface_create(ifname, SIOCIFCREATE, &str, return_value) == 0) {
		RETURN_STR(str);
	}
}

PHP_FUNCTION(pfSense_interface_create2) {
	char *ifname;
	size_t ifname_len;
	zend_string *str;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &ifname, &ifname_len) == FAILURE) {
		RETURN_NULL();
	}
	if (interface_create(ifname, SIOCIFCREATE2, &str, return_value) == 0) {
		RETURN_STR(str);
	}
}

PHP_FUNCTION(pfSense_interface_destroy) {
	char *ifname;
	size_t ifname_len;
	struct ifreq ifr;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &ifname, &ifname_len) == FAILURE) {
		RETURN_NULL();
	}

	memset(&ifr, 0, sizeof(ifr));
	strlcpy(ifr.ifr_name, ifname, sizeof(ifr.ifr_name));
	if (ioctl(PFSENSE_G(s), SIOCIFDESTROY, &ifr) < 0) {
		array_init(return_value);
		add_assoc_string(return_value, "error", "Could not destroy interface");
	} else
		RETURN_TRUE;
}

PHP_FUNCTION(pfSense_interface_setaddress) {
	char *ifname, *ip, *p = NULL;
	size_t ifname_len, ip_len;
	struct sockaddr_in *sin;
	struct in_aliasreq ifra;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "ss", &ifname, &ifname_len, &ip, &ip_len) == FAILURE) {
		RETURN_NULL();
	}

	memset(&ifra, 0, sizeof(ifra));
	strlcpy(ifra.ifra_name, ifname, sizeof(ifra.ifra_name));
	if ((p = strrchr(ip, '/')) != NULL) {
		sin =  &ifra.ifra_mask;
		sin->sin_family = AF_INET;
		sin->sin_len = sizeof(*sin);
		sin->sin_addr.s_addr = 0;
		/* address is `name/masklen' */
		int masklen;
		int ret;
		*p = '\0';
		ret = sscanf(p+1, "%u", &masklen);
		if(ret != 1 || (masklen < 0 || masklen > 32)) {
			*p = '/';
			RETURN_FALSE;
		}
		sin->sin_addr.s_addr = htonl(~((1LL << (32 - masklen)) - 1) &
			0xffffffff);
	}
	sin =  &ifra.ifra_addr;
	sin->sin_family = AF_INET;
	sin->sin_len = sizeof(*sin);
	if (inet_pton(AF_INET, ip, &sin->sin_addr) <= 0)
		RETURN_FALSE;

	if (ioctl(PFSENSE_G(inets), SIOCAIFADDR, &ifra) < 0) {
		array_init(return_value);
		add_assoc_string(return_value, "error", "Could not set interface address");
	} else
		RETURN_TRUE;
}

PHP_FUNCTION(pfSense_interface_deladdress) {
	char *ifname, *ip = NULL;
	size_t ifname_len, ip_len;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "ss", &ifname, &ifname_len, &ip, &ip_len) == FAILURE) {
		RETURN_NULL();
	}

	if (strstr(ip, ":")) {
		struct in6_aliasreq ifra6;
		struct sockaddr_in6 *sin6;

		memset(&ifra6, 0, sizeof(ifra6));
		strlcpy(ifra6.ifra_name, ifname, sizeof(ifra6.ifra_name));
		sin6 =  (struct sockaddr_in6 *)&ifra6.ifra_addr;
		sin6->sin6_family = AF_INET;
		sin6->sin6_len = sizeof(*sin6);
		if (inet_pton(AF_INET6, ip, &sin6->sin6_addr) <= 0)
			RETURN_FALSE;

		if (ioctl(PFSENSE_G(inets6), SIOCDIFADDR_IN6, &ifra6) < 0) {
			array_init(return_value);
			add_assoc_string(return_value, "error", "Could not delete interface address");
		} else
			RETURN_TRUE;

	} else {
		struct in_aliasreq ifra;
		struct sockaddr_in *sin;

		memset(&ifra, 0, sizeof(ifra));
		strlcpy(ifra.ifra_name, ifname, sizeof(ifra.ifra_name));
		sin =  &ifra.ifra_addr;
		sin->sin_family = AF_INET;
		sin->sin_len = sizeof(*sin);
		if (inet_pton(AF_INET, ip, &sin->sin_addr) <= 0)
			RETURN_FALSE;

		if (ioctl(PFSENSE_G(inets), SIOCDIFADDR, &ifra) < 0) {
			array_init(return_value);
			add_assoc_string(return_value, "error", "Could not delete interface address");
		} else
			RETURN_TRUE;
	}
}

PHP_FUNCTION(pfSense_interface_rename) {
	char *ifname, *newifname;
	size_t ifname_len, newifname_len;
	struct ifreq ifr;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "ss", &ifname, &ifname_len, &newifname, &newifname_len) == FAILURE) {
		RETURN_NULL();
	}

	memset(&ifr, 0, sizeof(ifr));
	strlcpy(ifr.ifr_name, ifname, sizeof(ifr.ifr_name));
	ifr.ifr_data = (caddr_t) newifname;
	if (ioctl(PFSENSE_G(s), SIOCSIFNAME, (caddr_t) &ifr) < 0) {
		array_init(return_value);
		add_assoc_string(return_value, "error", "Could not rename interface");
	} else
		RETURN_TRUE;
}

PHP_FUNCTION(pfSense_ngctl_name) {
	char *ifname, *newifname;
	size_t ifname_len, newifname_len;

	if (PFSENSE_G(csock) == -1)
		RETURN_NULL();

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "ss", &ifname, &ifname_len, &newifname, &newifname_len) == FAILURE) {
		RETURN_NULL();
	}

	/* Send message */
	if (NgNameNode(PFSENSE_G(csock), ifname, "%s", newifname) < 0)
		RETURN_NULL();

	RETURN_TRUE;
}

PHP_FUNCTION(pfSense_vlan_create) {
	char *ifname = NULL;
	char *parentifname = NULL;
	size_t ifname_len, parent_len;
	zend_long tag, pcp;
	struct ifreq ifr;
	struct vlanreq params;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "ssll", &ifname, &ifname_len, &parentifname, &parent_len, &tag, &pcp) == FAILURE) {
		RETURN_NULL();
	}

	memset(&ifr, 0, sizeof(ifr));
	memset(&params, 0, sizeof(params));
	strlcpy(ifr.ifr_name, ifname, sizeof(ifr.ifr_name));
	strlcpy(params.vlr_parent, parentifname, sizeof(params.vlr_parent));
	params.vlr_tag = (u_short) tag;
	ifr.ifr_data = (caddr_t) &params;
	if (ioctl(PFSENSE_G(s), SIOCSETVLAN, (caddr_t) &ifr) < 0)
		RETURN_NULL();
	ifr.ifr_vlan_pcp = (u_short) pcp;
	if (ioctl(PFSENSE_G(s), SIOCSVLANPCP, (caddr_t) &ifr) < 0)
		RETURN_NULL();

	RETURN_TRUE;
}

PHP_FUNCTION(pfSense_interface_getmtu) {
	char *ifname;
	size_t ifname_len;
	struct ifreq ifr;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &ifname, &ifname_len) == FAILURE) {
		RETURN_NULL();
	}
	memset(&ifr, 0, sizeof(ifr));
	strlcpy(ifr.ifr_name, ifname, sizeof(ifr.ifr_name));
	if (ioctl(PFSENSE_G(s), SIOCGIFMTU, (caddr_t)&ifr) < 0)
		RETURN_NULL();
	array_init(return_value);
	add_assoc_long(return_value, "mtu", ifr.ifr_mtu);
}

PHP_FUNCTION(pfSense_interface_mtu) {
	char *ifname;
	size_t ifname_len;
	zend_long mtu;
	struct ifreq ifr;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "sl", &ifname, &ifname_len, &mtu) == FAILURE) {
		RETURN_NULL();
	}
	memset(&ifr, 0, sizeof(ifr));
	strlcpy(ifr.ifr_name, ifname, sizeof(ifr.ifr_name));
	ifr.ifr_mtu = (int) mtu;
	if (ioctl(PFSENSE_G(s), SIOCSIFMTU, (caddr_t)&ifr) < 0)
		RETURN_NULL();
	RETURN_TRUE;
}

PHP_FUNCTION(pfSense_interface_flags) {
	struct ifreq ifr;
	char *ifname;
	int flags;
	size_t ifname_len;
	zend_long value;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "sl", &ifname, &ifname_len, &value) == FAILURE) {
		RETURN_NULL();
	}

	memset(&ifr, 0, sizeof(ifr));
	strlcpy(ifr.ifr_name, ifname, sizeof(ifr.ifr_name));
	if (ioctl(PFSENSE_G(s), SIOCGIFFLAGS, (caddr_t)&ifr) < 0) {
		RETURN_NULL();
	}
	flags = (ifr.ifr_flags & 0xffff) | (ifr.ifr_flagshigh << 16);
	if (value < 0) {
		value = -value;
		flags &= ~(int)value;
	} else
		flags |= (int)value;
	ifr.ifr_flags = flags & 0xffff;
	ifr.ifr_flagshigh = flags >> 16;
	if (ioctl(PFSENSE_G(s), SIOCSIFFLAGS, (caddr_t)&ifr) < 0)
		RETURN_NULL();
	RETURN_TRUE;
}

PHP_FUNCTION(pfSense_interface_capabilities) {
	struct ifreq ifr;
	char *ifname;
	int flags;
	size_t ifname_len;
	zend_long value;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "sl", &ifname, &ifname_len, &value) == FAILURE) {
		RETURN_NULL();
	}

	memset(&ifr, 0, sizeof(ifr));
	strlcpy(ifr.ifr_name, ifname, sizeof(ifr.ifr_name));
	if (ioctl(PFSENSE_G(s), SIOCGIFCAP, (caddr_t)&ifr) < 0) {
		RETURN_NULL();
	}
	flags = ifr.ifr_curcap;
	if (value < 0) {
		value = -value;
		flags &= ~(int)value;
	} else
		flags |= (int)value;
	flags &= ifr.ifr_reqcap;
	ifr.ifr_reqcap = flags;
	if (ioctl(PFSENSE_G(s), SIOCSIFCAP, (caddr_t)&ifr) < 0)
		RETURN_NULL();
	RETURN_TRUE;

}

PHP_FUNCTION(pfSense_get_interface_info)
{
	struct ifaddrs *ifdata, *mb;
	struct if_data *tmpd;
	struct pfi_kif kif = { { 0 } };
	int size = 1, found = 0;
	char *ifname;
	size_t ifname_len;
	int error = 0;
	int dev;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &ifname, &ifname_len) == FAILURE) {
		RETURN_NULL();
	}

	if ((dev = open("/dev/pf", O_RDWR)) < 0)
		RETURN_NULL();

	if (getifaddrs(&ifdata) == -1) {
		close(dev);
		RETURN_NULL();
	}

	for(mb = ifdata; mb != NULL && mb->ifa_addr != NULL; mb = mb->ifa_next) {
		if (ifname_len != strlen(mb->ifa_name))
			continue;
		if (strncmp(ifname, mb->ifa_name, ifname_len) != 0)
			continue;

		if (found == 0)
			array_init(return_value);

		found = 1;

		switch (mb->ifa_addr->sa_family) {
		case AF_LINK:

			tmpd = (struct if_data *)mb->ifa_data;
			add_assoc_long(return_value, "inerrs", tmpd->ifi_ierrors);
			add_assoc_long(return_value, "outerrs", tmpd->ifi_oerrors);
			add_assoc_long(return_value, "collisions", tmpd->ifi_collisions);
			add_assoc_long(return_value, "inmcasts", tmpd->ifi_imcasts);
			add_assoc_long(return_value, "outmcasts", tmpd->ifi_omcasts);
			add_assoc_long(return_value, "unsuppproto", tmpd->ifi_noproto);
			add_assoc_long(return_value, "mtu", tmpd->ifi_mtu);

			break;
		}
	}
	freeifaddrs(ifdata);

	if (found == 0) {
		close(dev);
		RETURN_NULL();
	}

	if (pfi_get_ifaces(dev, ifname, &kif, &size))
		error = 1;

	if (error == 0) {
		add_assoc_string(return_value, "interface", kif.pfik_name);

#define PAF_INET 0
#define PPF_IN 0
#define PPF_OUT 1
		add_assoc_long(return_value, "inpktspass", (unsigned long long)kif.pfik_packets[PAF_INET][PPF_IN][PF_PASS]);
		add_assoc_long(return_value, "outpktspass", (unsigned long long)kif.pfik_packets[PAF_INET][PPF_OUT][PF_PASS]);
		add_assoc_long(return_value, "inbytespass", (unsigned long long)kif.pfik_bytes[PAF_INET][PPF_IN][PF_PASS]);
		add_assoc_long(return_value, "outbytespass", (unsigned long long)kif.pfik_bytes[PAF_INET][PPF_OUT][PF_PASS]);

		add_assoc_long(return_value, "inpktsblock", (unsigned long long)kif.pfik_packets[PAF_INET][PPF_IN][PF_DROP]);
		add_assoc_long(return_value, "outpktsblock", (unsigned long long)kif.pfik_packets[PAF_INET][PPF_OUT][PF_DROP]);
		add_assoc_long(return_value, "inbytesblock", (unsigned long long)kif.pfik_bytes[PAF_INET][PPF_IN][PF_DROP]);
		add_assoc_long(return_value, "outbytesblock", (unsigned long long)kif.pfik_bytes[PAF_INET][PPF_OUT][PF_DROP]);

		add_assoc_long(return_value, "inbytes", (unsigned long long)(kif.pfik_bytes[PAF_INET][PPF_IN][PF_DROP] + kif.pfik_bytes[PAF_INET][PPF_IN][PF_PASS]));
		add_assoc_long(return_value, "outbytes", (unsigned long long)(kif.pfik_bytes[PAF_INET][PPF_OUT][PF_DROP] + kif.pfik_bytes[PAF_INET][PPF_OUT][PF_PASS]));
		add_assoc_long(return_value, "inpkts", (unsigned long long)(kif.pfik_packets[PAF_INET][PPF_IN][PF_DROP] + kif.pfik_packets[PAF_INET][PPF_IN][PF_PASS]));
		add_assoc_long(return_value, "outpkts", (unsigned long long)(kif.pfik_packets[PAF_INET][PPF_OUT][PF_DROP] + kif.pfik_packets[PAF_INET][PPF_OUT][PF_PASS]));
#undef PPF_IN
#undef PPF_OUT
#undef PAF_INET
	}
	close(dev);
}

PHP_FUNCTION(pfSense_get_interface_stats)
{
	struct ifmibdata ifmd;
	struct if_data *tmpd;
	char *ifname;
	size_t len, ifname_len;
	int name[6];
	unsigned int ifidx;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &ifname, &ifname_len) == FAILURE) {
		RETURN_NULL();
	}

	ifidx = if_nametoindex(ifname);
	if (ifidx == 0)
		RETURN_NULL();

	name[0] = CTL_NET;
	name[1] = PF_LINK;
	name[2] = NETLINK_GENERIC;
	name[3] = IFMIB_IFDATA;
	name[4] = ifidx;
	name[5] = IFDATA_GENERAL;

	len = sizeof(ifmd);

	if (sysctl(name, 6, &ifmd, &len, (void *)0, 0) < 0)
		RETURN_NULL();

	tmpd = &ifmd.ifmd_data;

	array_init(return_value);
	add_assoc_double(return_value, "inpkts", (double)tmpd->ifi_ipackets);
	add_assoc_double(return_value, "inbytes", (double)tmpd->ifi_ibytes);
	add_assoc_double(return_value, "outpkts", (double)tmpd->ifi_opackets);
	add_assoc_double(return_value, "outbytes", (double)tmpd->ifi_obytes);
	add_assoc_double(return_value, "inerrs", (double)tmpd->ifi_ierrors);
	add_assoc_double(return_value, "outerrs", (double)tmpd->ifi_oerrors);
	add_assoc_double(return_value, "collisions", (double)tmpd->ifi_collisions);
	add_assoc_double(return_value, "inmcasts", (double)tmpd->ifi_imcasts);
	add_assoc_double(return_value, "outmcasts", (double)tmpd->ifi_omcasts);
	add_assoc_double(return_value, "unsuppproto", (double)tmpd->ifi_noproto);
	add_assoc_long(return_value, "mtu", (long)tmpd->ifi_mtu);
}

PHP_FUNCTION(pfSense_get_pf_rules) {
	int dev;
	struct pfioc_rule pr;
	struct pfctl_rule r;
	uint32_t mnr, nr;

	if ((dev = open("/dev/pf", O_RDWR)) < 0)
		RETURN_NULL();
	memset(&pr, 0, sizeof(pr));
	pr.rule.action = PF_PASS;
	if (ioctl(dev, DIOCGETRULES, &pr)) {
		close(dev);
		RETURN_NULL();
	}

	mnr = pr.nr;
	array_init(return_value);
	for (nr = 0; nr < mnr; ++nr) {
		zval array;
		zval labels;
		char *label = NULL;
		int i;

		if (pfctl_get_rule(dev, nr, pr.ticket, pr.anchor, pr.action,
		    &r, pr.anchor_call)) {
			add_assoc_string(return_value, "error", strerror(errno));
			break;
		}

		array_init(&labels);
		for (i = 0; i < nitems(r.label) && r.label[i][0] != 0; i++) {
			char *key;
			char *value;
			value = r.label[i];
			key = strsep(&value, ": ");

			/* Take a non-prefixed label only if another
			 * non-prefixed label or user rule isn't already
			 * found */
			if ((label == NULL && key == NULL) ||
			    (strcmp("USER_RULE", key) == 0)) {
				label = value;
			}
			add_assoc_string(&labels, key, value);
		}
		if (label == NULL) {
			label = "";
		}
		array_init(&array);
		add_assoc_long(&array, "id", (long)r.nr);
		add_assoc_long(&array, "tracker", (long)r.ridentifier);
		add_assoc_string(&array, "label", label);
		add_assoc_zval(&array, "all_labels", &labels);
		add_assoc_double(&array, "evaluations", (double)r.evaluations);
		add_assoc_double(&array, "packets", (double)(r.packets[0] + r.packets[1]));
		add_assoc_double(&array, "bytes", (double)(r.bytes[0] + r.bytes[1]));
		add_assoc_double(&array, "states", (double)r.states_cur);
		add_assoc_long(&array, "pid", (long)r.cpid);
		add_assoc_double(&array, "state creations", (double)r.states_tot);
		add_index_zval(return_value, r.nr, &array);
	}
	close(dev);
}

PHP_FUNCTION(pfSense_get_pf_states) {
	char buf[128], *filter;
	int count, dev, filter_if, filter_rl, found, min, sec;
	sa_family_t af;
	struct pfctl_states states;
	struct pfctl_state *s;
	struct pfctl_state_peer *src, *dst;
	struct pfctl_state_key *sk, *nk;
	struct protoent *p;
	uint8_t proto;
	uint32_t expire, creation;
	uint64_t bytes[2], id, packets[2];
	zval array, *zvar;
	HashTable *hash1, *hash2;
	zval *val, *val2;
	zend_long lkey, lkey2;
	zend_string *skey, *skey2;
	int entries = 0;

	filter = NULL;
	filter_if = filter_rl = 0;
	zvar = NULL;
	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "|z", &zvar) == FAILURE)
		RETURN_NULL();
/*
	Check if an array was passed as an argument to this function (meaning we want to filter the states in some way) e.g.:
	Array
	(
	    [0] => Array
	        (
	            [filter] => igb0
	        )
	)
*/
	if (zvar != NULL && Z_TYPE_P(zvar) == IS_ARRAY) {
		hash1 = Z_ARRVAL_P(zvar);

		// Find the next (sub) array with a numeric key
		ZEND_HASH_FOREACH_KEY_VAL(hash1, lkey, skey, val) {
			if (skey || Z_TYPE_P(val) != IS_ARRAY) {
				continue;
			}

			hash2 = Z_ARRVAL_P(val);

			// Now search teh sub-array interfaces, rules or filters
			ZEND_HASH_FOREACH_KEY_VAL(hash2, lkey2, skey2, val2) {
				entries = 1;
				if((strcasecmp(ZSTR_VAL(skey2), "interface") == 0) && (Z_TYPE_P(val2) == IS_STRING)) {
					filter_if = 1;
				} else if ((strcasecmp(ZSTR_VAL(skey2), "ruleid") == 0) && (Z_TYPE_P(val2) == IS_LONG)) {
					filter_rl = 1;
				} else if ((strcasecmp(ZSTR_VAL(skey2), "filter") == 0) && (Z_TYPE_P(val2) == IS_STRING)) {
					filter = Z_STRVAL_P(val2);
				}

			} ZEND_HASH_FOREACH_END();
		} ZEND_HASH_FOREACH_END();

		if (entries == 0) {
			RETURN_NULL();
		}

		if (filter_if && filter_rl)
			RETURN_NULL();
	}

	if ((dev = open("/dev/pf", O_RDONLY)) < 0)
		RETURN_NULL();
	memset(&states, 0, sizeof(states));
	if (pfctl_get_states(dev, &states) != 0) {
		close(dev);
		RETURN_NULL();
	}
	close(dev);

	count = 0;
	array_init(return_value);
	TAILQ_FOREACH(s, &states.states, entry) {
		/* Limit the result to 50.000 states maximum. */
		if (++count == 50000)
			break;

		if (filter_if || filter_rl) {
			found = 0;
			hash1 = Z_ARRVAL_P(zvar);

			ZEND_HASH_FOREACH_KEY_VAL(hash1, lkey, skey, val) {
				hash2 = Z_ARRVAL_P(val);
				entries = 0;
				ZEND_HASH_FOREACH_KEY_VAL(hash2, lkey2, skey2, val2) {
					entries = 1;

					if (filter_if) {
						if (strcasecmp(s->orig_ifname, Z_STRVAL_P(val2)) == 0) {
							found = 1;
						}
					} else if (filter_rl) {
						if (s->rule != -1 &&
						    s->rule == Z_LVAL_P(val2)) {
							found = 1;
						}
					}
				} ZEND_HASH_FOREACH_END();

				if (entries == 0) {
					pfctl_free_states(&states);
					RETURN_NULL();
				}
			} ZEND_HASH_FOREACH_END();

			if (!found)
				continue;
		}

		af = s->key[PF_SK_WIRE].af;
		proto = s->key[PF_SK_WIRE].proto;
		if (s->direction == PF_OUT) {
			src = &s->src;
			dst = &s->dst;
			sk = &s->key[PF_SK_STACK];
			nk = &s->key[PF_SK_WIRE];
			if (proto == IPPROTO_ICMP || proto == IPPROTO_ICMPV6)
				sk->port[0] = nk->port[0];
		} else {
			src = &s->dst;
			dst = &s->src;
			sk = &s->key[PF_SK_WIRE];
			nk = &s->key[PF_SK_STACK];
			if (proto == IPPROTO_ICMP || proto == IPPROTO_ICMPV6)
				sk->port[1] = nk->port[1];
		}

		found = 0;

		array_init(&array);

		add_assoc_string(&array, "if", s->orig_ifname);
		if ((p = getprotobynumber(proto)) != NULL) {
			add_assoc_string(&array, "proto", p->p_name);
			if (filter != NULL && strstr(p->p_name, filter))
				found = 1;
		} else
			add_assoc_long(&array, "proto", (long)proto);
		add_assoc_string(&array, "direction",
		    ((s->direction == PF_OUT) ? "out" : "in"));

		memset(buf, 0, sizeof(buf));
		pf_print_host(&nk->addr[1], nk->port[1], af, buf, sizeof(buf));
		add_assoc_string(&array, ((s->direction == PF_OUT) ? "src" : "dst"), buf);
		if (filter != NULL && !found && strstr(buf, filter))
			found = 1;

		if (PF_ANEQ(&nk->addr[1], &sk->addr[1], af) ||
		    nk->port[1] != sk->port[1]) {
			memset(buf, 0, sizeof(buf));
			pf_print_host(&sk->addr[1], sk->port[1], af, buf,
			    sizeof(buf));
			add_assoc_string(&array,
			    ((s->direction == PF_OUT) ? "src-orig" : "dst-orig"), buf);
			if (filter != NULL && !found && strstr(buf, filter))
				found = 1;
		}

		memset(buf, 0, sizeof(buf));
		pf_print_host(&nk->addr[0], nk->port[0], af, buf, sizeof(buf));
		add_assoc_string(&array, ((s->direction == PF_OUT) ? "dst" : "src"), buf);
		if (filter != NULL && !found && strstr(buf, filter))
			found = 1;

		if (PF_ANEQ(&nk->addr[0], &sk->addr[0], af) ||
		    nk->port[0] != sk->port[0]) {
			memset(buf, 0, sizeof(buf));
			pf_print_host(&sk->addr[0], sk->port[0], af, buf,
			    sizeof(buf));
			add_assoc_string(&array,
			    ((s->direction == PF_OUT) ? "dst-orig" : "src-orig"), buf);
			if (filter != NULL && !found && strstr(buf, filter))
				found = 1;
		}

		if (proto == IPPROTO_TCP) {
			if (src->state <= TCPS_TIME_WAIT &&
			    dst->state <= TCPS_TIME_WAIT) {
				snprintf(buf, sizeof(buf) - 1, "%s:%s",
				    tcpstates[src->state], tcpstates[dst->state]);
				add_assoc_string(&array, "state", buf);
				if (filter != NULL && !found &&
				    (strstr(tcpstates[src->state], filter) ||
				    strstr(tcpstates[dst->state], filter))) {
					found = 1;
				}
			} else if (src->state == PF_TCPS_PROXY_SRC ||
			    dst->state == PF_TCPS_PROXY_SRC)
				add_assoc_string(&array, "state", "PROXY:SRC");
			else if (src->state == PF_TCPS_PROXY_DST ||
			    dst->state == PF_TCPS_PROXY_DST)
				add_assoc_string(&array, "state", "PROXY:DST");
			else {
				snprintf(buf, sizeof(buf) - 1,
				    "<BAD STATE LEVELS %u:%u>",
				    src->state, dst->state);
				add_assoc_string(&array, "state", buf);
			}
		} else if (proto == IPPROTO_UDP && src->state < PFUDPS_NSTATES &&
		    dst->state < PFUDPS_NSTATES) {
			const char *states[] = PFUDPS_NAMES;

			snprintf(buf, sizeof(buf) - 1, "%s:%s",
			    states[src->state], states[dst->state]);
			add_assoc_string(&array, "state", buf);

			if (filter != NULL && !found &&
			    (strstr(states[src->state], filter) ||
			    strstr(states[dst->state], filter))) {
				found = 1;
			}
		} else if (proto != IPPROTO_ICMP && src->state < PFOTHERS_NSTATES &&
		    dst->state < PFOTHERS_NSTATES) {
			/* XXX ICMP doesn't really have state levels */
			const char *states[] = PFOTHERS_NAMES;

			snprintf(buf, sizeof(buf) - 1, "%s:%s",
			    states[src->state], states[dst->state]);
			add_assoc_string(&array, "state", buf);

			if (filter != NULL && !found &&
			    (strstr(states[src->state], filter) ||
			    strstr(states[dst->state], filter))) {
				found = 1;
			}
		} else {
			snprintf(buf, sizeof(buf) - 1, "%u:%u", src->state, dst->state);
			add_assoc_string(&array, "state", buf);
		}

		if (filter != NULL && !found) {
			zval_dtor(&array);
			continue;
		}

		creation = s->creation;
		sec = creation % 60;
		creation /= 60;
		min = creation % 60;
		creation /= 60;
		snprintf(buf, sizeof(buf) - 1, "%.2u:%.2u:%.2u", creation, min, sec);
		add_assoc_string(&array, "age", buf);
		expire = s->expire;
		sec = expire % 60;
		expire /= 60;
		min = expire % 60;
		expire /= 60;
		snprintf(buf, sizeof(buf) - 1, "%.2u:%.2u:%.2u", expire, min, sec);
		add_assoc_string(&array, "expires in", buf);

		bcopy(&s->packets[0], &packets[0], sizeof(uint64_t));
		bcopy(&s->packets[1], &packets[1], sizeof(uint64_t));
		bcopy(&s->bytes[0], &bytes[0], sizeof(uint64_t));
		bcopy(&s->bytes[1], &bytes[1], sizeof(uint64_t));
		add_assoc_double(&array, "packets total",
		    (double)(packets[0] + packets[1]));
		add_assoc_double(&array, "packets in",
		    (double)packets[0]);
		add_assoc_double(&array, "packets out",
		    (double)packets[1]);
		add_assoc_double(&array, "bytes total",
		    (double)(bytes[0] + bytes[1]));
		add_assoc_double(&array, "bytes in", (double)bytes[0]);
		add_assoc_double(&array, "bytes out", (double)bytes[1]);
		if (s->anchor != -1)
			add_assoc_long(&array, "anchor", (long)s->anchor);
		if (s->rule != -1)
			add_assoc_long(&array, "rule", (long)s->rule);

		bcopy(&s->id, &id, sizeof(uint64_t));
		snprintf(buf, sizeof(buf) - 1, "%016jx", (uintmax_t)id);
		add_assoc_string(&array, "id", buf);
		snprintf(buf, sizeof(buf) - 1, "%08x", s->creatorid);
		add_assoc_string(&array, "creatorid", buf);

		add_next_index_zval(return_value, &array);
	}
	pfctl_free_states(&states);
}

PHP_FUNCTION(pfSense_get_pf_stats) {
	struct pf_status status;
	time_t runtime;
	unsigned sec, min, hrs, day;
	char statline[80];
	char buf[PF_MD5_DIGEST_LENGTH * 2 + 1];
	static const char hex[] = "0123456789abcdef";
	int i;
	int dev;

	array_init(return_value);

	if ((dev = open("/dev/pf", O_RDWR)) < 0) {
		add_assoc_string(return_value, "error", strerror(errno));
	} else {


	bzero(&status, sizeof(status));
	if (ioctl(dev, DIOCGETSTATUS, &status)) {
		add_assoc_string(return_value, "error", strerror(errno));
	} else {
		add_assoc_long(return_value, "rulesmatch", (unsigned long long)status.counters[PFRES_MATCH]);
		add_assoc_long(return_value, "pullhdrfail", (unsigned long long)status.counters[PFRES_BADOFF]);
		add_assoc_long(return_value, "fragments", (unsigned long long)status.counters[PFRES_FRAG]);
		add_assoc_long(return_value, "shortpacket", (unsigned long long)status.counters[PFRES_SHORT]);
		add_assoc_long(return_value, "normalizedrop", (unsigned long long)status.counters[PFRES_NORM]);
		add_assoc_long(return_value, "nomemory", (unsigned long long)status.counters[PFRES_MEMORY]);
		add_assoc_long(return_value, "badtimestamp", (unsigned long long)status.counters[PFRES_TS]);
		add_assoc_long(return_value, "congestion", (unsigned long long)status.counters[PFRES_CONGEST]);
		add_assoc_long(return_value, "ipoptions", (unsigned long long)status.counters[PFRES_IPOPTIONS]);
		add_assoc_long(return_value, "protocksumbad", (unsigned long long)status.counters[PFRES_PROTCKSUM]);
		add_assoc_long(return_value, "statesbad", (unsigned long long)status.counters[PFRES_BADSTATE]);
		add_assoc_long(return_value, "stateinsertions", (unsigned long long)status.counters[PFRES_STATEINS]);
		add_assoc_long(return_value, "maxstatesdrop", (unsigned long long)status.counters[PFRES_MAXSTATES]);
		add_assoc_long(return_value, "srclimitdrop", (unsigned long long)status.counters[PFRES_SRCLIMIT]);
		add_assoc_long(return_value, "synproxydrop", (unsigned long long)status.counters[PFRES_SYNPROXY]);

		add_assoc_long(return_value, "maxstatesreached", (unsigned long long)status.lcounters[LCNT_STATES]);
		add_assoc_long(return_value, "maxsrcstatesreached", (unsigned long long)status.lcounters[LCNT_SRCSTATES]);
		add_assoc_long(return_value, "maxsrcnodesreached", (unsigned long long)status.lcounters[LCNT_SRCNODES]);
		add_assoc_long(return_value, "maxsrcconnreached", (unsigned long long)status.lcounters[LCNT_SRCCONN]);
		add_assoc_long(return_value, "maxsrcconnratereached", (unsigned long long)status.lcounters[LCNT_SRCCONNRATE]);
		add_assoc_long(return_value, "overloadtable", (unsigned long long)status.lcounters[LCNT_OVERLOAD_TABLE]);
		add_assoc_long(return_value, "overloadflush", (unsigned long long)status.lcounters[LCNT_OVERLOAD_FLUSH]);

		add_assoc_long(return_value, "statesearch", (unsigned long long)status.fcounters[FCNT_STATE_SEARCH]);
		add_assoc_long(return_value, "stateinsert", (unsigned long long)status.fcounters[FCNT_STATE_INSERT]);
		add_assoc_long(return_value, "stateremovals", (unsigned long long)status.fcounters[FCNT_STATE_REMOVALS]);

		add_assoc_long(return_value, "srcnodessearch", (unsigned long long)status.scounters[SCNT_SRC_NODE_SEARCH]);
		add_assoc_long(return_value, "srcnodesinsert", (unsigned long long)status.scounters[SCNT_SRC_NODE_INSERT]);
		add_assoc_long(return_value, "srcnodesremovals", (unsigned long long)status.scounters[SCNT_SRC_NODE_REMOVALS]);

		add_assoc_long(return_value, "running", status.running);
		add_assoc_long(return_value, "states", status.states);
		add_assoc_long(return_value, "srcnodes", status.src_nodes);

		add_assoc_long(return_value, "hostid", ntohl(status.hostid));
		for (i = 0; i < PF_MD5_DIGEST_LENGTH; i++) {
			buf[i + i] = hex[status.pf_chksum[i] >> 4];
			buf[i + i + 1] = hex[status.pf_chksum[i] & 0x0f];
		}
		buf[i + i] = '\0';
		add_assoc_string(return_value, "pfchecksum", buf);
		printf("Checksum: 0x%s\n\n", buf);

		switch(status.debug) {
		case PF_DEBUG_NONE:
			add_assoc_string(return_value, "debuglevel", "none");
			break;
		case PF_DEBUG_URGENT:
			add_assoc_string(return_value, "debuglevel", "urgent");
			break;
		case PF_DEBUG_MISC:
			add_assoc_string(return_value, "debuglevel", "misc");
			break;
		case PF_DEBUG_NOISY:
			add_assoc_string(return_value, "debuglevel", "noisy");
			break;
		default:
			add_assoc_string(return_value, "debuglevel", "unknown");
			break;
		}

		runtime = time(NULL) - status.since;
		if (status.since) {
			day = runtime;
			sec = day % 60;
			day /= 60;
			min = day % 60;
			day /= 60;
			hrs = day % 24;
			day /= 24;
			snprintf(statline, sizeof(statline),
			    "Running: for %u days %.2u:%.2u:%.2u",
			    day, hrs, min, sec);
			add_assoc_string(return_value, "uptime", statline);
		}
	}
	close(dev);
	}
}

PHP_FUNCTION(pfSense_sync) {
	sync();
}

PHP_FUNCTION(pfSense_fsync) {
	char *fname, *parent_dir;
	size_t fname_len;
	int fd;

	if (ZEND_NUM_ARGS() != 1 ||
	    zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &fname,
	    &fname_len) == FAILURE) {
		RETURN_FALSE;
	}
	if (fname_len == 0)
		RETURN_FALSE;

	if ((fd = open(fname, O_RDWR|O_CLOEXEC)) == -1) {
		php_printf("\tcan't open %s\n", fname);
		RETURN_FALSE;
	}
	if (fsync(fd) == -1) {
		php_printf("\tcan't fsync %s\n", fname);
		close(fd);
		RETURN_FALSE;
	}
	close(fd);

	if ((fname = strdup(fname)) == NULL)
		RETURN_FALSE;
	parent_dir = dirname(fname);
	fd = open(parent_dir, O_RDWR|O_CLOEXEC);
	free(fname);
	if (fd == -1)
		RETURN_FALSE;
	if (fsync(fd) == -1) {
		close(fd);
		RETURN_FALSE;
	}
	close(fd);

	RETURN_TRUE;
}

PHP_FUNCTION(pfSense_get_modem_devices) {
	struct termios		attr, origattr;
	struct pollfd		pfd;
	glob_t			g;
	char			buf[2048] = { 0 };
	char			*path;
	int			nw = 0, i, fd, retries;
	zend_bool		show_info = 0;
	zend_long		poll_timeout = 700;

	if (ZEND_NUM_ARGS() > 2) {
		RETURN_NULL();
	}
	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "|bl", &show_info, &poll_timeout) == FAILURE) {
		php_printf("Maximum two parameter can be passed\n");
			RETURN_NULL();
	}

	array_init(return_value);

	bzero(&g, sizeof g);
	glob("/dev/cua*", 0, NULL, &g);
	glob("/dev/modem*", GLOB_APPEND, NULL, &g);

	if (g.gl_pathc > 0)
	for (i = 0; g.gl_pathv[i] != NULL; i++) {
		path = g.gl_pathv[i];
		if (strstr(path, "lock") || strstr(path, "init"))
			continue;
		if (show_info)
			php_printf("Found modem device: %s\n", path);
		/* Open & lock serial port */
		if ((fd = open(path, O_RDWR | O_NONBLOCK, 0)) < 0) {
			if (show_info)
				php_printf("Could not open the device exlusively\n");
			add_assoc_string(return_value, path, path);
			continue;
		}

		/* Set non-blocking I/O  */
		if (fcntl(fd, F_SETFL, O_NONBLOCK) < 0)
			goto errormodem;

		/* Set serial port raw mode, baud rate, hardware flow control, etc. */
		if (tcgetattr(fd, &attr) < 0)
			goto errormodem;

		origattr = attr;
		cfmakeraw(&attr);

		attr.c_cflag &= ~(CSIZE|PARENB|PARODD);
		attr.c_cflag |= (CS8|CREAD|CLOCAL|HUPCL|CCTS_OFLOW|CRTS_IFLOW);
		attr.c_iflag &= ~(IXANY|IMAXBEL|ISTRIP|IXON|IXOFF|BRKINT|ICRNL|INLCR);
		attr.c_iflag |= (IGNBRK|IGNPAR);
		attr.c_oflag &= ~OPOST;
		attr.c_lflag = 0;

#define MODEM_DEFAULT_SPEED              115200
		cfsetspeed(&attr, (speed_t) MODEM_DEFAULT_SPEED);
#undef	MODEM_DEFAULT_SPEED

		if (tcsetattr(fd, TCSANOW, &attr) < 0)
			goto errormodem;

		/* OK */
		retries = 0;
		while (retries++ < 3) {
			if ((nw = write(fd, "AT OK\r\n", strlen("AT OK\r\n"))) < 0) {
				if (errno == EAGAIN) {
					if (show_info)
						php_printf("\tRetrying write\n");
					continue;
				}

				if (show_info)
					php_printf("\tError ocurred\n");
				goto errormodem;
			}
		}
		if (retries >= 3)
			goto errormodem;

		retries = 0;
tryagain2:
		if (show_info)
			php_printf("\tTrying to read data\n");
		bzero(buf, sizeof buf);
		bzero(&pfd, sizeof pfd);
		pfd.fd = fd;
		pfd.events = POLLIN | POLLRDNORM | POLLRDBAND | POLLPRI | POLLHUP;
		if ((nw = poll(&pfd, 1, poll_timeout)) > 0) {
			if ((nw = read(fd, buf, sizeof(buf))) < 0) {
				if (errno == EAGAIN) {
					if (show_info)
						php_printf("\tTrying again after errno = EAGAIN\n");
					if (++retries < 3)
						goto tryagain2;
				}
				if (show_info)
					php_printf("\tError ocurred on 1st read\n");
				goto errormodem;
			}

			buf[2047] = '\0';
			if (show_info)
				php_printf("\tRead %s\n", buf);
			//if (strnstr(buf, "OK", sizeof(buf)))
			if (nw > 0) {
				/*
				write(fd, "ATI3\r\n", strlen("ATI3\r\n"));
				bzero(buf, sizeof buf);
				bzero(&pfd, sizeof pfd);
				pfd.fd = fd;
				pfd.events = POLLIN | POLLRDNORM | POLLRDBAND | POLLPRI | POLLHUP;

				if (poll(&pfd, 1, 200) > 0) {
					read(fd, buf, sizeof(buf));
				buf[2047] = '\0';
				if (show_info)
					php_printf("\tRead %s\n", buf);
				}
				*/
				add_assoc_string(return_value, path, path);
			}
		} else if (show_info)
			php_printf("\ttimedout or interrupted: %d\n", nw);

		tcsetattr(fd, TCSANOW, &origattr);
errormodem:
		if (show_info)
			php_printf("\tClosing device %s\n", path);
		close(fd);
	}
}

PHP_FUNCTION(pfSense_get_os_hw_data) {
	int mib[4], idata;
	u_long ldata;
	size_t len;
	char *data;

	array_init(return_value);

	mib[0] = CTL_HW;
	mib[1] = HW_MACHINE;
	if (!sysctl(mib, 2, NULL, &len, NULL, 0)) {
		data = malloc(len);
		if (data != NULL) {
			if (!sysctl(mib, 2, data, &len, NULL, 0)) {
				add_assoc_string(return_value, "hwmachine", data);
				free(data);
			}
		}
	}

	mib[0] = CTL_HW;
	mib[1] = HW_MODEL;
	if (!sysctl(mib, 2, NULL, &len, NULL, 0)) {
		data = malloc(len);
		if (data != NULL) {
			if (!sysctl(mib, 2, data, &len, NULL, 0)) {
				add_assoc_string(return_value, "hwmodel", data);
				free(data);
			}
		}
	}

	mib[0] = CTL_HW;
	mib[1] = HW_MACHINE_ARCH;
	if (!sysctl(mib, 2, NULL, &len, NULL, 0)) {
		data = malloc(len);
		if (data != NULL) {
			if (!sysctl(mib, 2, data, &len, NULL, 0)) {
				add_assoc_string(return_value, "hwarch", data);
				free(data);
			}
		}
	}

	mib[0] = CTL_HW;
	mib[1] = HW_NCPU;
	len = sizeof(idata);
	if (!sysctl(mib, 2, &idata, &len, NULL, 0))
		add_assoc_long(return_value, "ncpus", idata);

	mib[0] = CTL_HW;
	mib[1] = HW_PHYSMEM;
	len = sizeof(ldata);
	if (!sysctl(mib, 2, &ldata, &len, NULL, 0))
		add_assoc_long(return_value, "physmem", ldata);

	mib[0] = CTL_HW;
	mib[1] = HW_USERMEM;
	len = sizeof(ldata);
	if (!sysctl(mib, 2, &ldata, &len, NULL, 0))
		add_assoc_long(return_value, "usermem", ldata);

	mib[0] = CTL_HW;
	mib[1] = HW_REALMEM;
	len = sizeof(ldata);
	if (!sysctl(mib, 2, &ldata, &len, NULL, 0))
		add_assoc_long(return_value, "realmem", ldata);
}

PHP_FUNCTION(pfSense_get_os_kern_data) {
	int mib[4], idata;
	size_t len;
	char *data;
	struct timeval bootime;

	array_init(return_value);

	mib[0] = CTL_KERN;
	mib[1] = KERN_HOSTUUID;
	if (!sysctl(mib, 2, NULL, &len, NULL, 0)) {
		data = malloc(len);
		if (data != NULL) {
			if (!sysctl(mib, 2, data, &len, NULL, 0)) {
				add_assoc_string(return_value, "hostuuid", data);
				free(data);
			}
		}
	}

	mib[0] = CTL_KERN;
	mib[1] = KERN_HOSTNAME;
	if (!sysctl(mib, 2, NULL, &len, NULL, 0)) {
		data = malloc(len);
		if (data != NULL) {
			if (!sysctl(mib, 2, data, &len, NULL, 0)) {
				add_assoc_string(return_value, "hostname", data);
				free(data);
			}
		}
	}

	mib[0] = CTL_KERN;
	mib[1] = KERN_OSRELEASE;
	if (!sysctl(mib, 2, NULL, &len, NULL, 0)) {
		data = malloc(len);
		if (data != NULL) {
			if (!sysctl(mib, 2, data, &len, NULL, 0)) {
				add_assoc_string(return_value, "osrelease", data);
				free(data);
			}
		}
	}

	mib[0] = CTL_KERN;
	mib[1] = KERN_VERSION;
	if (!sysctl(mib, 2, NULL, &len, NULL, 0)) {
		data = malloc(len);
		if (data != NULL) {
			if (!sysctl(mib, 2, data, &len, NULL, 0)) {
				add_assoc_string(return_value, "oskernel_version", data);
				free(data);
			}
		}
	}

	mib[0] = CTL_KERN;
	mib[1] = KERN_BOOTTIME;
	len = sizeof(bootime);
	if (!sysctl(mib, 2, &bootime, &len, NULL, 0))
		add_assoc_string(return_value, "boottime", ctime(&bootime.tv_sec));

	mib[0] = CTL_KERN;
	mib[1] = KERN_HOSTID;
	len = sizeof(idata);
	if (!sysctl(mib, 2, &idata, &len, NULL, 0))
		add_assoc_long(return_value, "hostid", idata);

	mib[0] = CTL_KERN;
	mib[1] = KERN_OSRELDATE;
	len = sizeof(idata);
	if (!sysctl(mib, 2, &idata, &len, NULL, 0))
		add_assoc_long(return_value, "osreleasedate", idata);

	mib[0] = CTL_KERN;
	mib[1] = KERN_OSREV;
	len = sizeof(idata);
	if (!sysctl(mib, 2, &idata, &len, NULL, 0))
		add_assoc_long(return_value, "osrevision", idata);

	mib[0] = CTL_KERN;
	mib[1] = KERN_SECURELVL;
	len = sizeof(idata);
	if (!sysctl(mib, 2, &idata, &len, NULL, 0))
		add_assoc_long(return_value, "ossecurelevel", idata);

	mib[0] = CTL_KERN;
	mib[1] = KERN_OSRELDATE;
	len = sizeof(idata);
	if (!sysctl(mib, 2, &idata, &len, NULL, 0))
		add_assoc_long(return_value, "osreleasedate", idata);

	mib[0] = CTL_KERN;
	mib[1] = KERN_OSRELDATE;
	len = sizeof(idata);
	if (!sysctl(mib, 2, &idata, &len, NULL, 0))
		add_assoc_long(return_value, "osreleasedate", idata);
}

static void build_ipsec_sa_array(void *salist, char *label, vici_res_t *res) {
	char *name, *value;
	/* message sections may be nested. maintain a stack as we traverse */

	int done = 0;
	int level = 0;
	zval nestedarrs[32];
	char *temp = "con-id";

	nestedarrs[level] = *((zval *) salist);

	while (!done) {
		name = value = NULL;
		vici_parse_t pres;
		pres = vici_parse(res);
		switch (pres) {
			case VICI_PARSE_BEGIN_SECTION:
				name = vici_parse_name(res);

				array_init(&(nestedarrs[level + 1]));

				if (level == 0) {
					add_next_index_zval(&(nestedarrs[level]),&(nestedarrs[level+1]));
					add_assoc_string(&(nestedarrs[level + 1]), temp, name);
				} else {
					add_assoc_zval(&(nestedarrs[level]), name, &(nestedarrs[level + 1]));
				}
				Z_ADDREF_P(&(nestedarrs[level + 1]));
				level++;
				break;
			case VICI_PARSE_END_SECTION:
//				&(nestedarrs[level]) = NULL;
				level--;
				break;
			case VICI_PARSE_KEY_VALUE:
				name = vici_parse_name(res);
				value = vici_parse_value_str(res);
				add_assoc_string(&(nestedarrs[level]), name, value);
				break;
			case VICI_PARSE_BEGIN_LIST:
				name = vici_parse_name(res);

				array_init(&(nestedarrs[level + 1]));
				if (level == 0) {
					add_next_index_zval(&(nestedarrs[level]),&(nestedarrs[level+1]));
					add_assoc_string(&(nestedarrs[level + 1]), temp, name);
				} else {
					add_assoc_zval(&(nestedarrs[level]), name, &(nestedarrs[level + 1]));
				}
				Z_ADDREF_P(&(nestedarrs[level + 1]));
				level++;
				break;
			case VICI_PARSE_END_LIST:
//				&(nestedarrs[level]) = NULL;
				level--;
				break;
			case VICI_PARSE_LIST_ITEM:
				value = vici_parse_value_str(res);
				add_next_index_string(&(nestedarrs[level]), value);
				break;
			case VICI_PARSE_END:
				done++;
				break;
			default:
				php_printf("Parse error!\n");
				done++;
				break;
		}
	}
	return;
}

PHP_FUNCTION(pfSense_ipsec_list_sa) {

	vici_conn_t *conn;
	vici_req_t *req;
	vici_res_t *res;

	array_init(return_value);

	vici_init();
	conn = vici_connect(NULL);
	if (conn) {
		if (vici_register(conn, "list-sa", build_ipsec_sa_array, (void *) return_value) != 0) {
			php_printf("VICI registration failed: %s\n", strerror(errno));
		} else {
			req = vici_begin("list-sas");
			res = vici_submit(req, conn);
			if (res) {
				vici_free_res(res);
			}
		}
		vici_disconnect(conn);
	} else {
		php_printf("VICI connection failed: %s\n", strerror(errno));
	}

	vici_deinit();

}

#ifdef PF_CP_FUNCTIONS
#define FLUSH_TYPE_RULES "rules"
#define FLUSH_TYPE_NAT "nat"
#define FLUSH_TYPE_ETH "ether"
PHP_FUNCTION(pfSense_pf_cp_flush) {
	char *path, *type;
	size_t path_len, type_len = 0;
	int dev = 0, ret = -1;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "ss", &path, &path_len, &type, &type_len) == FAILURE) {
		RETURN_NULL();
	}
	if ((dev = open("/dev/pf", O_RDWR)) < 0) {
		RETURN_NULL();
	}

	if (strncmp(FLUSH_TYPE_RULES, type, MIN(strlen(FLUSH_TYPE_RULES), type_len)) == 0) {
		ret = pfctl_clear_rules(dev, path);
	} else if (strncmp(FLUSH_TYPE_NAT, type, MIN(strlen(FLUSH_TYPE_NAT), type_len)) == 0) {
		ret = pfctl_clear_nat(dev, path);
	} else if (strncmp(FLUSH_TYPE_ETH, type, MIN(strlen(FLUSH_TYPE_ETH), type_len)) == 0) {
		ret = pfctl_clear_eth_rules(dev, path);
	} else {
		close(dev);
		RETURN_NULL();
	}

	close(dev);
	if (ret == 0) {
		RETURN_TRUE;
	}
	RETURN_FALSE;
}

PHP_FUNCTION(pfSense_pf_cp_get_eth_pipes) {
	char *path;
	size_t path_len;
	struct pfctl_eth_rules_info info;
	struct pfctl_eth_rule rule;
	char anchor_call[MAXPATHLEN];
	int dev = 0;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &path, &path_len) == FAILURE)
		RETURN_NULL();

	if ((dev = open("/dev/pf", O_RDWR)) < 0)
		RETURN_NULL();

	if (path_len > MAXPATHLEN)
		goto error_out;
	if (pfctl_get_eth_rules_info(dev, &info, path))
		goto error_out;

	array_init(return_value);
	for (int nr = 0; nr < info.nr; nr++) {
		if (pfctl_get_eth_rule(dev, nr, info.ticket, path, &rule, 0, anchor_call) != 0)
			goto error_out;
		if (rule.dnflags & PFRULE_DN_IS_PIPE) {
			add_next_index_long(return_value, (zend_long)rule.dnpipe);
			add_next_index_long(return_value, (zend_long)rule.dnpipe + 1);
		}
	}

error_out:
	close(dev);
}

PHP_FUNCTION(pfSense_pf_cp_get_eth_rule_counters) {
	char *path;
	size_t path_len;
	struct pfctl_eth_rules_info info;
	struct pfctl_eth_rule rule;
	char anchor_call[MAXPATHLEN];
	int dev = 0;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &path, &path_len) == FAILURE)
		RETURN_NULL();
	if ((dev = open("/dev/pf", O_RDWR)) < 0)
		RETURN_NULL();

	if (path_len > MAXPATHLEN)
		goto error_out;
	if (pfctl_get_eth_rules_info(dev, &info, path))
		goto error_out;
	array_init(return_value);
	for (int nr = 0; nr < info.nr; nr++) {
		if (pfctl_get_eth_rule(dev, nr, info.ticket, path, &rule, 0,
		    anchor_call) != 0)
			goto error_out;
		if (rule.dnflags&PFRULE_DN_IS_PIPE) {
			add_next_index_long(return_value, (zend_long)rule.packets[1]);
			add_next_index_long(return_value, (zend_long)rule.bytes[1]);
			add_next_index_long(return_value, (zend_long)rule.packets[0]);
			add_next_index_long(return_value, (zend_long)rule.bytes[0]);
		}
	}

error_out:
	close(dev);
}

PHP_FUNCTION(pfSense_pf_cp_zerocnt) {
	char *path;
	size_t path_len;
	struct pfctl_rules_info info;
	struct pfctl_rule rule;
	struct pfctl_eth_rules_info einfo;
	struct pfctl_eth_rule erule;

	char anchor_call[MAXPATHLEN];
	uint32_t if_rulesets[] = {PF_RULESET_SCRUB, PF_RULESET_FILTER, PF_RULESET_NAT,PF_RULESET_BINAT, PF_RULESET_RDR,
				  PF_RULESET_MAX};

	int dev = 0;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &path, &path_len) == FAILURE)
		RETURN_NULL();
	if ((dev = open("/dev/pf", O_RDWR)) < 0)
		RETURN_NULL();

	if (path_len > MAXPATHLEN)
		goto error_out;

	/* Zero eth rule counters */
	if (pfctl_get_eth_rules_info(dev, &einfo, path))
		goto error_out;
	for (int nr = 0; nr < info.nr; nr++) {
		if (pfctl_get_eth_rule(dev, nr, info.ticket, path, &erule, true, anchor_call) != 0)
			goto error_out;
	}

	/* Zero all other rules */
	for (int nrs = 0; nrs < nitems(if_rulesets); nrs++) {
		if (pfctl_get_rules_info(dev, &info, if_rulesets[nrs], path))
			goto error_out;
		for (int nr = 0; nr < info.nr; nr++) {
			if (pfctl_get_clear_rule(dev, nr, info.ticket, path, if_rulesets[nr], &rule, anchor_call,
			    true) != 0)
				goto error_out;
		}
	}
error_out:
	close(dev);
}

PHP_FUNCTION(pfSense_pf_cp_get_eth_last_active) {
	char *path;
	size_t path_len;
	struct pfctl_eth_rules_info info;
	struct pfctl_eth_rule rule;
	char anchor_call[MAXPATHLEN];
	int dev = 0;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &path, &path_len) == FAILURE)
		RETURN_NULL();
	if ((dev = open("/dev/pf", O_RDWR)) < 0)
		RETURN_NULL();

	if (path_len > MAXPATHLEN)
		goto error_out;

	array_init(return_value);
	for (int nr = 0; nr < info.nr; nr++) {
		if (pfctl_get_eth_rule(dev, nr, info.ticket, path, &rule, true, anchor_call) != 0)
			goto error_out;
		add_next_index_long(return_value, (zend_long)rule.last_active_timestamp);
	}

error_out:
	close(dev);
}
#endif

PHP_FUNCTION(pfSense_kenv_dump) {
	char *buf, *bp, *cp;
	int size;

	size = kenv(KENV_DUMP, NULL, NULL, 0);
	if (size < 0)
		return;
	size += 1;
	buf = malloc(size);
	if (buf == NULL)
		return;
	if (kenv(KENV_DUMP, NULL, buf, size) < 0) {
		free(buf);
		return;
	}

	array_init(return_value);

	/*
	 * Stolen from bin/kenv
	 */
	for (bp = buf; *bp != '\0'; bp += strlen(bp) + 1) {
		cp = strchr(bp, '=');
		if (cp == NULL)
			continue;
		*cp++ = '\0';
		add_assoc_string(return_value, bp, cp);
		bp = cp;
	}

	free(buf);
}
