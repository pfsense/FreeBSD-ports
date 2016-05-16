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
#include <netgraph/ng_ether.h>
#include <netinet/if_ether.h>
#include <netinet/in.h>
#include <netinet/in_var.h>
#include <netinet/ip_fw.h>
#include <netinet/tcp_fsm.h>
#include <netinet6/in6_var.h>

#include <vm/vm_param.h>

#include <fcntl.h>
#include <glob.h>
#include <inttypes.h>
#include <ifaddrs.h>
#include <libgen.h>
#include <netgraph.h>
#include <netdb.h>
#include <poll.h>
#include <stdio.h>
#include <stdlib.h>
#include <strings.h>
#include <termios.h>
#include <unistd.h>

#define IS_EXT_MODULE

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

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
    PHP_FE(pfSense_interface_destroy, NULL)
    PHP_FE(pfSense_interface_flags, NULL)
    PHP_FE(pfSense_interface_capabilities, NULL)
    PHP_FE(pfSense_interface_setaddress, NULL)
    PHP_FE(pfSense_interface_deladdress, NULL)
    PHP_FE(pfSense_ngctl_name, NULL)
    PHP_FE(pfSense_ngctl_attach, NULL)
    PHP_FE(pfSense_ngctl_detach, NULL)
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
   PHP_FE(pfSense_ipfw_getTablestats, NULL)
   PHP_FE(pfSense_ipfw_Tableaction, NULL)
   PHP_FE(pfSense_pipe_action, NULL)
#endif
   PHP_FE(pfSense_ipsec_list_sa, NULL)
    {NULL, NULL, NULL}
};

zend_module_entry pfSense_module_entry = {
#if ZEND_MODULE_API_NO >= 20010901
    STANDARD_MODULE_HEADER,
#endif
    PHP_PFSENSE_WORLD_EXTNAME,
    pfSense_functions,
    PHP_MINIT(pfSense_socket),
    PHP_MSHUTDOWN(pfSense_socket_close),
    NULL,
    NULL,
    NULL,
#if ZEND_MODULE_API_NO >= 20010901
    PHP_PFSENSE_WORLD_VERSION,
#endif
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
get_pf_states(int dev, struct pfioc_states *ps)
{
	char *inbuf, *newinbuf;
	unsigned int len;

	len = 0;
	inbuf = newinbuf = NULL;
	memset(ps, 0, sizeof(*ps));
	for (;;) {
		ps->ps_len = len;
		if (len) {
			newinbuf = realloc(inbuf, len);
			if (newinbuf == NULL)
				return (-1);
			ps->ps_buf = inbuf = newinbuf;
		}
		if (ioctl(dev, DIOCGETSTATES, ps) < 0) {
			free(inbuf);
			return (-1);
		}
		if (ps->ps_len == 0)
			break;		/* no states */
		if (ps->ps_len + sizeof(struct pfioc_states) < len)
			break;
		if (len == 0 && ps->ps_len != 0)
			len = ps->ps_len;
		len *= 2;
	}

	if (ps->ps_len == 0)
		free(inbuf);

	return (0);
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
#if (__FreeBSD_version >= 1000000)
	REGISTER_LONG_CONSTANT("IP_FW_TABLE_XADD", IP_FW_TABLE_XADD, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IP_FW_TABLE_XDEL", IP_FW_TABLE_XDEL, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IP_FW_TABLE_XLISTENTRY", IP_FW_TABLE_XLISTENTRY, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IP_FW_TABLE_XZEROENTRY", IP_FW_TABLE_XZEROENTRY, CONST_PERSISTENT | CONST_CS);
#else
	REGISTER_LONG_CONSTANT("IP_FW_TABLE_ADD", IP_FW_TABLE_ADD, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IP_FW_TABLE_DEL", IP_FW_TABLE_DEL, CONST_PERSISTENT | CONST_CS);
	REGISTER_LONG_CONSTANT("IP_FW_TABLE_ZERO_ENTRY_STATS", IP_FW_TABLE_ZERO_ENTRY_STATS, CONST_PERSISTENT | CONST_CS);
#endif
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

	if ((ret_ga = getaddrinfo(addr, NULL, &hints, &res))) {
		php_printf("getaddrinfo: %s", gai_strerror(ret_ga));
		return (-1);
		/* NOTREACHED */
	}

	if (res->ai_family == AF_INET && prefix > 32) {
		php_printf("prefix too long for AF_INET");
		return (-1);
	} else if (res->ai_family == AF_INET6 && prefix > 128) {
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
	int killed, sources, dests;
	int ret_ga;

        int dev;
	char *ip1 = NULL, *ip2 = NULL;
	int ip1_len = 0, ip2_len = 0;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s|s", &ip1, &ip1_len, &ip2, &ip2_len) == FAILURE) {
		RETURN_NULL();
	}

	if ((dev = open("/dev/pf", O_RDWR)) < 0)
		RETURN_NULL();

	killed = sources = dests = 0;

	memset(&psnk, 0, sizeof(psnk));
	memset(&psnk.psnk_src.addr.v.a.mask, 0xff,
	    sizeof(psnk.psnk_src.addr.v.a.mask));
	memset(&last_src, 0xff, sizeof(last_src));
	memset(&last_dst, 0xff, sizeof(last_dst));

	pfctl_addrprefix(ip1, &psnk.psnk_src.addr.v.a.mask);

	if ((ret_ga = getaddrinfo(ip1, NULL, NULL, &res[0]))) {
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
			dests = 0;
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

				dests++;

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
	int killed, sources, dests;
	int ret_ga;

	int dev;
	char *ip1 = NULL, *ip2 = NULL, *proto = NULL, *iface = NULL;
	int ip1_len = 0, ip2_len = 0, proto_len = 0, iface_len = 0;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s|sss", &ip1, &ip1_len, &ip2, &ip2_len, &iface, &iface_len, &proto, &proto_len) == FAILURE) {
		RETURN_NULL();
	}

	if ((dev = open("/dev/pf", O_RDWR)) < 0)
		RETURN_NULL();

	killed = sources = dests = 0;

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
		else if (!strncmp(proto, "tcp", strlen("udp")))
			psk.psk_proto = IPPROTO_UDP;
		else if (!strncmp(proto, "tcp", strlen("icmpv6")))
			psk.psk_proto = IPPROTO_ICMPV6;
		else if (!strncmp(proto, "tcp", strlen("icmp")))
			psk.psk_proto = IPPROTO_ICMP;
	}

	if (pfctl_addrprefix(ip1, &psk.psk_src.addr.v.a.mask) < 0)
		RETURN_NULL();

	if ((ret_ga = getaddrinfo(ip1, NULL, NULL, &res[0]))) {
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
			dests = 0;
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

				dests++;

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
/* Stolen from ipfw2.c code */
static unsigned long long
pfSense_align_uint64(const uint64_t *pll)
{
	uint64_t ret;

	bcopy (pll, &ret, sizeof(ret));
	return ret;
}

PHP_FUNCTION(pfSense_pipe_action)
{
	int ac, do_pipe = 1, param_len = 0;
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

PHP_FUNCTION(pfSense_ipfw_Tableaction)
{
#if (__FreeBSD_version >= 1000000)
	ip_fw3_opheader *op3;
	ipfw_table_xentry *xent;
	socklen_t size;
	long mask = 0, table = 0, pipe = 0, zone = 0;
	char *ip, *mac = NULL;
	int ip_len, addrlen, mac_len = 0;
	long action = IP_FW_TABLE_XADD;
	int err;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "llls|lsl", &zone, &action, &table, &ip, &ip_len, &mask, &mac, &mac_len, &pipe) == FAILURE) {
		RETURN_FALSE;
	}

	if (action != IP_FW_TABLE_XDEL && action != IP_FW_TABLE_XADD && action != IP_FW_TABLE_XZEROENTRY)
		RETURN_FALSE;

	size = sizeof(*op3) + sizeof(*xent);

	if ((op3 = (ip_fw3_opheader *)emalloc(size)) == NULL)
		RETURN_FALSE;

	memset(op3, 0, size);
	op3->ctxid = (uint16_t)zone;
	op3->opcode = action;
	xent = (ipfw_table_xentry *)(op3 + 1);
	xent->tbl = (u_int16_t)table;

	if (strchr(ip, ':')) {
		if (!inet_pton(AF_INET6, ip, &xent->k.addr6)) {
			efree(op3);
			RETURN_FALSE;
		}
		addrlen = sizeof(struct in6_addr);
	} else if (!inet_pton(AF_INET, ip, &xent->k.addr6)) {
		efree(op3);
		RETURN_FALSE;
	}

	if (!strchr(ip, ':')) {
		xent->flags = IPFW_TCF_INET;
		addrlen = sizeof(uint32_t);
	}

	if (mask)
		xent->masklen = (u_int8_t)mask;
	else
		xent->masklen = 32;

	if (pipe)
		xent->value = (u_int32_t)pipe;

	if (mac_len > 0) {
		if (ether_aton_r(mac, (struct ether_addr *)&xent->mac_addr) == NULL) {
			efree(op3);
			php_printf("Failed mac\n");
			RETURN_FALSE;
		}
		//xent->masklen += ETHER_ADDR_LEN;
	}

	xent->type = IPFW_TABLE_CIDR;
	xent->len = offsetof(ipfw_table_xentry, k) + addrlen;
	err = setsockopt(PFSENSE_G(ipfw), IPPROTO_IP, IP_FW3, op3, size);
	if (err < 0 && err != EEXIST) {
		efree(op3);
		php_printf("Failed setsockopt");
		RETURN_FALSE;
	}
	efree(op3);

	RETURN_TRUE;
#else
	struct {
		char context[64]; /* IP_FW_CTX_MAXNAME */
		ipfw_table_entry ent;
	} option;
	socklen_t size;
	long mask = 0, table = 0, pipe = 0;
	char *ip, *zone, *mac = NULL;
	int ip_len, zone_len, mac_len = 0;
	long action = IP_FW_TABLE_ADD;
	int err;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "slls|lsl", &zone, &zone_len, &action, &table, &ip, &ip_len, &mask, &mac, &mac_len, &pipe) == FAILURE) {
		RETURN_FALSE;
	}

	memset(&option, 0, sizeof(option));
	sprintf(option.context, "%s", zone);

	if (action != IP_FW_TABLE_DEL && action != IP_FW_TABLE_ADD && action != IP_FW_TABLE_ZERO_ENTRY_STATS)
		RETURN_FALSE;

	if (strchr(ip, ':')) {
		if (!inet_pton(AF_INET6, ip, &option.ent.addr))
			RETURN_FALSE;
	} else if (!inet_pton(AF_INET, ip, &option.ent.addr)) {
		RETURN_FALSE;
	}

	if (mask)
		option.ent.masklen = (u_int8_t)mask;
	else
		option.ent.masklen = 32;
	if (pipe)
		option.ent.value = (u_int32_t)pipe;

	if (mac_len > 0) {
		if (ether_aton_r(mac, (struct ether_addr *)&option.ent.mac_addr) == NULL)
			RETURN_FALSE;
	}
	size = sizeof(option);
	option.ent.tbl = (u_int16_t)table;
	err = setsockopt(PFSENSE_G(ipfw), IPPROTO_IP, (int)action, &option, size);
	if (err < 0 && err != EEXIST)
		RETURN_FALSE;

	RETURN_TRUE;
#endif
}

PHP_FUNCTION(pfSense_ipfw_getTablestats)
{
#if (__FreeBSD_version >= 1000000)
	ip_fw3_opheader *op3;
	ipfw_table_xentry *xent;
	socklen_t size;
	long mask = 0, table = 0, zone = 0;
	char *ip, *mac = NULL;
	int ip_len, addrlen, mac_len = 0;
	long action = IP_FW_TABLE_XADD;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "llls|sl", &zone, &action, &table, &ip, &ip_len, &mac, &mac_len, &mask) == FAILURE) {
		RETURN_FALSE;
	}

	if (action != IP_FW_TABLE_XLISTENTRY)
		RETURN_FALSE;

	size = sizeof(*op3) + sizeof(*xent);

	if ((op3 = (ip_fw3_opheader *)emalloc(size)) == NULL)
		RETURN_FALSE;

	memset(op3, 0, size);
	op3->ctxid = (uint16_t)zone;
	op3->opcode = IP_FW_TABLE_XLISTENTRY;
	xent = (ipfw_table_xentry *)(op3 + 1);
	xent->tbl = (u_int16_t)table;

	if (strchr(ip, ':')) {
		if (!inet_pton(AF_INET6, ip, &xent->k.addr6)) {
			efree(op3);
			RETURN_FALSE;
		}
		addrlen = sizeof(struct in6_addr);
	} else if (!inet_pton(AF_INET, ip, &xent->k.addr6)) {
		efree(op3);
		RETURN_FALSE;
	}

	if (!strchr(ip, ':')) {
		xent->flags = IPFW_TCF_INET;
		addrlen = sizeof(uint32_t);
	}

	if (mask)
		xent->masklen = (u_int8_t)mask;
	else
		xent->masklen = 32;

	if (mac_len > 0) {
		if (ether_aton_r(mac, (struct ether_addr *)&xent->mac_addr) == NULL)
			RETURN_FALSE;
		//xent->masklen += ETHER_ADDR_LEN;
	}

	xent->type = IPFW_TABLE_CIDR;
	xent->len = offsetof(ipfw_table_xentry, k) + addrlen;
	if (getsockopt(PFSENSE_G(ipfw), IPPROTO_IP, IP_FW3, op3, &size) < 0) {
		efree(op3);
		RETURN_FALSE;
	}

	xent = (ipfw_table_xentry *)(op3);
	array_init(return_value);
	add_assoc_long(return_value, "packets", pfSense_align_uint64(&xent->packets));
	add_assoc_long(return_value, "bytes", pfSense_align_uint64(&xent->bytes));
	add_assoc_long(return_value, "timestamp", xent->timestamp);
	add_assoc_long(return_value, "dnpipe", xent->value);

	efree(op3);
#else
	struct {
		char context[64]; /* IP_FW_CTX_MAXNAME */
		ipfw_table_entry ent;
	} option;
	socklen_t size;
	long mask = 0, table = 0;
	char *ip, *name;
	int ip_len, name_len;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "sls|l", &name, &name_len, &table, &ip, &ip_len, &mask) == FAILURE) {
		RETURN_FALSE;
	}


	memset(&option, 0, sizeof(option));
	sprintf(option.context, "%s", name);

	if (strchr(ip, ':')) {
		if (!inet_pton(AF_INET6, ip, &option.ent.addr))
			RETURN_FALSE;
	} else if (!inet_pton(AF_INET, ip, &option.ent.addr)) {
		RETURN_FALSE;
	}

	if (mask)
		option.ent.masklen = (u_int8_t)mask;
	else
		option.ent.masklen = 32;
	size = sizeof(option);
	option.ent.tbl = (u_int16_t)table;
	if (getsockopt(PFSENSE_G(ipfw), IPPROTO_IP, IP_FW_TABLE_GET_ENTRY, &option, &size) < 0)
		RETURN_FALSE;

	array_init(return_value);
	add_assoc_long(return_value, "packets", pfSense_align_uint64(&option.ent.packets));
	add_assoc_long(return_value, "bytes", pfSense_align_uint64(&option.ent.bytes));
	add_assoc_long(return_value, "timestamp", option.ent.timestamp);
	add_assoc_long(return_value, "dnpipe", option.ent.value);
#endif
}
#endif

#ifdef DHCP_INTEGRATION
PHP_FUNCTION(pfSense_open_dhcpd)
{
	omapi_data *data;
	char *key, *addr, *name;
	int key_len, addr_len, name_len;
	long port;
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
	int mac_len, ip_len, name_len;
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
	int mac_len;
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
	int ip_len, ifname_len = 0;

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
	free(buf);

	if (found_entry == 0)
		RETURN_NULL();

	array_init(return_value);
	bzero(outputbuf, sizeof outputbuf);
	ether_ntoa_r((struct ether_addr *)LLADDR(sdl), outputbuf);
	add_assoc_string(return_value, "macaddr", outputbuf, 1);
}

PHP_FUNCTION(pfSense_getall_interface_addresses)
{
	struct ifaddrs *ifdata, *mb;
	struct sockaddr_in *tmp;
	struct sockaddr_in6 *tmp6;
	char outputbuf[132];
	char *ifname;
	int ifname_len;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &ifname, &ifname_len) == FAILURE)
		RETURN_NULL();

	getifaddrs(&ifdata);
	if (ifdata == NULL)
		RETURN_NULL();

	array_init(return_value);

	for(mb = ifdata; mb != NULL; mb = mb->ifa_next) {
		if (mb == NULL)
			continue;
		if (ifname_len != strlen(mb->ifa_name))
			continue;
		if (strncmp(ifname, mb->ifa_name, ifname_len) != 0)
			continue;
		if (mb->ifa_addr == NULL)
			continue;

		switch (mb->ifa_addr->sa_family) {
		case AF_INET:
			bzero(outputbuf, sizeof outputbuf);
			tmp = (struct sockaddr_in *)mb->ifa_addr;
			inet_ntop(AF_INET, (void *)&tmp->sin_addr, outputbuf, sizeof(outputbuf));
			tmp = (struct sockaddr_in *)mb->ifa_netmask;
			unsigned char mask;
			const unsigned char *byte = (unsigned char *)&tmp->sin_addr.s_addr;
			int i = 0, n = sizeof(tmp->sin_addr.s_addr);
			while (n--) {
				mask = ((unsigned char)-1 >> 1) + 1;
					do {
						if (mask & byte[n])
							i++;
						mask >>= 1;
					} while (mask);
			}
			snprintf(outputbuf + strlen(outputbuf), sizeof(outputbuf) - strlen(outputbuf), "/%d", i);
			add_next_index_string(return_value, outputbuf, 1);
			break;
		case AF_INET6:
			bzero(outputbuf, sizeof outputbuf);
			tmp6 = (struct sockaddr_in6 *)mb->ifa_addr;
			if (getnameinfo((struct sockaddr *)tmp6, tmp6->sin6_len, outputbuf, sizeof(outputbuf), NULL, 0, NI_NUMERICHOST))
				inet_ntop(AF_INET6, (void *)&tmp6->sin6_addr, outputbuf, INET6_ADDRSTRLEN);
			tmp6 = (struct sockaddr_in6 *)mb->ifa_netmask;
			snprintf(outputbuf + strlen(outputbuf), sizeof(outputbuf) - strlen(outputbuf),
				"/%d", prefix(&tmp6->sin6_addr, sizeof(struct in6_addr)));
			add_next_index_string(return_value, outputbuf, 1);
			break;
		}
	}
}

PHP_FUNCTION(pfSense_get_interface_addresses)
{
	struct ifaddrs *ifdata, *mb;
	struct if_data *md;
	struct sockaddr_in *tmp;
	struct sockaddr_in6 *tmp6;
	struct sockaddr_dl *tmpdl;
	struct ifreq ifr;
	char outputbuf[128];
	char *ifname;
	int ifname_len, addresscnt = 0, addresscnt6 = 0;
	zval *caps;
	zval *encaps;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &ifname, &ifname_len) == FAILURE)
		RETURN_NULL();

	getifaddrs(&ifdata);
	if (ifdata == NULL)
		RETURN_NULL();

	array_init(return_value);

	for(mb = ifdata; mb != NULL; mb = mb->ifa_next) {
		if (mb == NULL)
			continue;
		if (ifname_len != strlen(mb->ifa_name))
			continue;
		if (strncmp(ifname, mb->ifa_name, ifname_len) != 0)
			continue;

		if (mb->ifa_flags & IFF_UP)
			add_assoc_string(return_value, "status", "up", 1);
		else
			add_assoc_string(return_value, "status", "down", 1);
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
		if (mb->ifa_data != NULL) {
			md = mb->ifa_data;
			if (md->ifi_link_state == LINK_STATE_UP)
				add_assoc_long(return_value, "linkstateup", 1);
			//add_assoc_long(return_value, "hwassistflag", md->ifi_hwassist);
			switch (md->ifi_type) {
			case IFT_IEEE80211:
				add_assoc_string(return_value, "iftype", "wireless", 1);
				break;
			case IFT_ETHER:
			case IFT_FASTETHER:
			case IFT_FASTETHERFX:
			case IFT_GIGABITETHERNET:
				add_assoc_string(return_value, "iftype", "ether", 1);
				break;
			case IFT_L2VLAN:
				add_assoc_string(return_value, "iftype", "vlan", 1);
				break;
			case IFT_BRIDGE:
				add_assoc_string(return_value, "iftype", "bridge", 1);
				break;
			case IFT_TUNNEL:
			case IFT_GIF:
#if (__FreeBSD_version < 1100000)
			case IFT_FAITH:
#endif
			case IFT_ENC:
			case IFT_PFLOG: 
			case IFT_PFSYNC:
				add_assoc_string(return_value, "iftype", "virtual", 1);
				break;
#if (__FreeBSD_version < 1000000)
			case IFT_CARP:
				add_assoc_string(return_value, "iftype", "carp", 1);
				break;
#endif
			default:
				add_assoc_string(return_value, "iftype", "other", 1);
			}
		}
		ALLOC_INIT_ZVAL(caps);
		ALLOC_INIT_ZVAL(encaps);
		array_init(caps);
		array_init(encaps);
		strncpy(ifr.ifr_name, ifname, sizeof(ifr.ifr_name));
		if (ioctl(PFSENSE_G(s), SIOCGIFMTU, (caddr_t)&ifr) == 0)
			add_assoc_long(return_value, "mtu", ifr.ifr_mtu);
		if (ioctl(PFSENSE_G(s), SIOCGIFCAP, (caddr_t)&ifr) == 0) {
			add_assoc_long(caps, "flags", ifr.ifr_reqcap);
			if (ifr.ifr_reqcap & IFCAP_POLLING)
				add_assoc_long(caps, "polling", 1);
			if (ifr.ifr_reqcap & IFCAP_RXCSUM)
				add_assoc_long(caps, "rxcsum", 1);
			if (ifr.ifr_reqcap & IFCAP_TXCSUM)
				add_assoc_long(caps, "txcsum", 1);
			if (ifr.ifr_reqcap & IFCAP_VLAN_MTU)
				add_assoc_long(caps, "vlanmtu", 1);
			if (ifr.ifr_reqcap & IFCAP_JUMBO_MTU)
				add_assoc_long(caps, "jumbomtu", 1);
			if (ifr.ifr_reqcap & IFCAP_VLAN_HWTAGGING)
				add_assoc_long(caps, "vlanhwtag", 1);
			if (ifr.ifr_reqcap & IFCAP_VLAN_HWCSUM)
				add_assoc_long(caps, "vlanhwcsum", 1);
			if (ifr.ifr_reqcap & IFCAP_TSO4)
				add_assoc_long(caps, "tso4", 1);
			if (ifr.ifr_reqcap & IFCAP_TSO6)
				add_assoc_long(caps, "tso6", 1);
			if (ifr.ifr_reqcap & IFCAP_LRO)
				add_assoc_long(caps, "lro", 1);
			if (ifr.ifr_reqcap & IFCAP_WOL_UCAST)
				add_assoc_long(caps, "wolucast", 1);
			if (ifr.ifr_reqcap & IFCAP_WOL_MCAST)
				add_assoc_long(caps, "wolmcast", 1);
			if (ifr.ifr_reqcap & IFCAP_WOL_MAGIC)
				add_assoc_long(caps, "wolmagic", 1);
			if (ifr.ifr_reqcap & IFCAP_TOE4)
				add_assoc_long(caps, "toe4", 1);
			if (ifr.ifr_reqcap & IFCAP_TOE6)
				add_assoc_long(caps, "toe6", 1);
			if (ifr.ifr_reqcap & IFCAP_VLAN_HWFILTER)
				add_assoc_long(caps, "vlanhwfilter", 1);
#if 0
			if (ifr.ifr_reqcap & IFCAP_POLLING_NOCOUNT)
				add_assoc_long(caps, "pollingnocount", 1);
#endif
			add_assoc_long(encaps, "flags", ifr.ifr_curcap);
			if (ifr.ifr_curcap & IFCAP_POLLING)
				add_assoc_long(encaps, "polling", 1);
			if (ifr.ifr_curcap & IFCAP_RXCSUM)
				add_assoc_long(encaps, "rxcsum", 1);
			if (ifr.ifr_curcap & IFCAP_TXCSUM)
				add_assoc_long(encaps, "txcsum", 1);
			if (ifr.ifr_curcap & IFCAP_VLAN_MTU)
				add_assoc_long(encaps, "vlanmtu", 1);
			if (ifr.ifr_curcap & IFCAP_JUMBO_MTU)
				add_assoc_long(encaps, "jumbomtu", 1);
			if (ifr.ifr_curcap & IFCAP_VLAN_HWTAGGING)
				add_assoc_long(encaps, "vlanhwtag", 1);
			if (ifr.ifr_curcap & IFCAP_VLAN_HWCSUM)
				add_assoc_long(encaps, "vlanhwcsum", 1);
			if (ifr.ifr_curcap & IFCAP_TSO4)
				add_assoc_long(encaps, "tso4", 1);
			if (ifr.ifr_curcap & IFCAP_TSO6)
				add_assoc_long(encaps, "tso6", 1);
			if (ifr.ifr_curcap & IFCAP_LRO)
				add_assoc_long(encaps, "lro", 1);
			if (ifr.ifr_curcap & IFCAP_WOL_UCAST)
				add_assoc_long(encaps, "wolucast", 1);
			if (ifr.ifr_curcap & IFCAP_WOL_MCAST)
				add_assoc_long(encaps, "wolmcast", 1);
			if (ifr.ifr_curcap & IFCAP_WOL_MAGIC)
				add_assoc_long(encaps, "wolmagic", 1);
			if (ifr.ifr_curcap & IFCAP_TOE4)
				add_assoc_long(encaps, "toe4", 1);
			if (ifr.ifr_curcap & IFCAP_TOE6)
				add_assoc_long(encaps, "toe6", 1);
			if (ifr.ifr_curcap & IFCAP_VLAN_HWFILTER)
				add_assoc_long(encaps, "vlanhwfilter", 1);
#if 0
			if (ifr.ifr_reqcap & IFCAP_POLLING_NOCOUNT)
				add_assoc_long(caps, "pollingnocount", 1);
#endif
		}
		add_assoc_zval(return_value, "caps", caps);
		add_assoc_zval(return_value, "encaps", encaps);
		if (mb->ifa_addr == NULL)
			continue;
		switch (mb->ifa_addr->sa_family) {
		case AF_INET:
			if (addresscnt > 0)
				break;
			bzero(outputbuf, sizeof outputbuf);
			tmp = (struct sockaddr_in *)mb->ifa_addr;
			inet_ntop(AF_INET, (void *)&tmp->sin_addr, outputbuf, sizeof(outputbuf));
			add_assoc_string(return_value, "ipaddr", outputbuf, 1);
			addresscnt++;
			tmp = (struct sockaddr_in *)mb->ifa_netmask;
			unsigned char mask;
			const unsigned char *byte = (unsigned char *)&tmp->sin_addr.s_addr;
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
			inet_ntop(AF_INET, (void *)&tmp->sin_addr, outputbuf, sizeof(outputbuf));
			add_assoc_string(return_value, "subnet", outputbuf, 1);

			if (mb->ifa_flags & IFF_BROADCAST) {
				bzero(outputbuf, sizeof outputbuf);
				tmp = (struct sockaddr_in *)mb->ifa_broadaddr;
				inet_ntop(AF_INET, (void *)&tmp->sin_addr, outputbuf, sizeof(outputbuf));
				add_assoc_string(return_value, "broadcast", outputbuf, 1);
			}

			if (mb->ifa_flags & IFF_POINTOPOINT) {
				tmp = (struct sockaddr_in *)mb->ifa_dstaddr;
				if (tmp != NULL && tmp->sin_family == AF_INET) {
					bzero(outputbuf, sizeof outputbuf);
					inet_ntop(AF_INET, (void *)&tmp->sin_addr, outputbuf, sizeof(outputbuf));
					add_assoc_string(return_value, "tunnel", outputbuf, 1);
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
                        inet_ntop(AF_INET6, (void *)&tmp6->sin6_addr, outputbuf, sizeof(outputbuf));
                        add_assoc_string(return_value, "ipaddr6", outputbuf, 1);
                        addresscnt6++;
                        tmp6 = (struct sockaddr_in6 *)mb->ifa_netmask;
                        add_assoc_long(return_value, "subnetbits6", prefix(&tmp6->sin6_addr, sizeof(struct in6_addr)));
                
                        if (mb->ifa_flags & IFF_POINTOPOINT) {
				tmp6 = (struct sockaddr_in6 *)mb->ifa_dstaddr;
				if (tmp6 != NULL && tmp6->sin6_family == AF_INET6) {
	                                bzero(outputbuf, sizeof outputbuf);
					inet_ntop(AF_INET6, (void *)&tmp6->sin6_addr, outputbuf, sizeof(outputbuf));
	                                add_assoc_string(return_value, "tunnel6", outputbuf, 1);
				}
                        }
		break;
		case AF_LINK:
			tmpdl = (struct sockaddr_dl *)mb->ifa_addr;
			bzero(outputbuf, sizeof outputbuf);
			ether_ntoa_r((struct ether_addr *)LLADDR(tmpdl), outputbuf);
			add_assoc_string(return_value, "macaddr", outputbuf, 1);
			md = (struct if_data *)mb->ifa_data;

		break;
		}
	}
	freeifaddrs(ifdata);
}

PHP_FUNCTION(pfSense_bridge_add_member) {
	char *ifname, *ifchld;
	int ifname_len, ifchld_len;
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
	int ifname_len, ifchld_len;
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
	int ifname_len, ifchld_len;
	struct ifdrv drv;
	struct ifbreq req;
	long flags = 0;

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
	long flags = 0;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "|l", &flags) == FAILURE)
		RETURN_NULL();

	getifaddrs(&ifdata);
	if (ifdata == NULL)
		RETURN_NULL();

	array_init(return_value);
	ifname = NULL;
	ifname_len = 0;
	for(mb = ifdata; mb != NULL; mb = mb->ifa_next) {
		if (mb == NULL)
			continue;

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

		add_next_index_string(return_value, mb->ifa_name, 1);
	}

	freeifaddrs(ifdata);
}

PHP_FUNCTION(pfSense_interface_create) {
	char *ifname;
	int ifname_len;
	struct ifreq ifr;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &ifname, &ifname_len) == FAILURE) {
		RETURN_NULL();
	}

	memset(&ifr, 0, sizeof(ifr));
	strlcpy(ifr.ifr_name, ifname, sizeof(ifr.ifr_name));
	if (ioctl(PFSENSE_G(s), SIOCIFCREATE2, &ifr) < 0) {
		array_init(return_value);
		add_assoc_string(return_value, "error", "Could not create interface", 1);
	} else
		RETURN_STRING(ifr.ifr_name, 1)
}

PHP_FUNCTION(pfSense_interface_destroy) {
	char *ifname;
	int ifname_len;
	struct ifreq ifr;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &ifname, &ifname_len) == FAILURE) {
		RETURN_NULL();
	}

	memset(&ifr, 0, sizeof(ifr));
	strlcpy(ifr.ifr_name, ifname, sizeof(ifr.ifr_name));
	if (ioctl(PFSENSE_G(s), SIOCIFDESTROY, &ifr) < 0) {
		array_init(return_value);
		add_assoc_string(return_value, "error", "Could not create interface", 1);
	} else
		RETURN_TRUE;
}

PHP_FUNCTION(pfSense_interface_setaddress) {
	char *ifname, *ip, *p = NULL;
	int ifname_len, ip_len;
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
		add_assoc_string(return_value, "error", "Could not set interface address", 1);
	} else
		RETURN_TRUE;
}

PHP_FUNCTION(pfSense_interface_deladdress) {
	char *ifname, *ip = NULL;
	int ifname_len, ip_len;

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
			add_assoc_string(return_value, "error", "Could not delete interface address", 1);
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
			add_assoc_string(return_value, "error", "Could not delete interface address", 1);
		} else
			RETURN_TRUE;
	}
}

PHP_FUNCTION(pfSense_interface_rename) {
	char *ifname, *newifname;
	int ifname_len, newifname_len;
	struct ifreq ifr;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "ss", &ifname, &ifname_len, &newifname, &newifname_len) == FAILURE) {
		RETURN_NULL();
	}

	memset(&ifr, 0, sizeof(ifr));
	strlcpy(ifr.ifr_name, ifname, sizeof(ifr.ifr_name));
	ifr.ifr_data = (caddr_t) newifname;
	if (ioctl(PFSENSE_G(s), SIOCSIFNAME, (caddr_t) &ifr) < 0) {
		array_init(return_value);
		add_assoc_string(return_value, "error", "Could not rename interface", 1);
	} else
		RETURN_TRUE;
}

PHP_FUNCTION(pfSense_ngctl_name) {
	char *ifname, *newifname;
	int ifname_len, newifname_len;

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

PHP_FUNCTION(pfSense_ngctl_attach) {
	char *ifname, *newifname;
	int ifname_len, newifname_len;
	struct ngm_name name;

	if (PFSENSE_G(csock) == -1)
		RETURN_NULL();

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "ss", &ifname, &ifname_len, &newifname, &newifname_len) == FAILURE) {
		RETURN_NULL();
	}

	snprintf(name.name, sizeof(name.name), "%s", newifname);
	/* Send message */
	if (NgSendMsg(PFSENSE_G(csock), ifname, NGM_GENERIC_COOKIE,
		NGM_ETHER_ATTACH, &name, sizeof(name)) < 0)
			RETURN_NULL();

	RETURN_TRUE;
}

PHP_FUNCTION(pfSense_ngctl_detach) {
	char *ifname, *newifname;
	int ifname_len, newifname_len;
	struct ngm_name name;

	if (PFSENSE_G(csock) == -1)
		RETURN_NULL();

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "ss", &ifname, &ifname_len, &newifname, &newifname_len) == FAILURE) {
		RETURN_NULL();
	}

	snprintf(name.name, sizeof(name.name), "%s", newifname);
	/* Send message */
	if (NgSendMsg(PFSENSE_G(csock), ifname, NGM_ETHER_COOKIE,
		NGM_ETHER_DETACH, &name, sizeof(name)) < 0)
			RETURN_NULL();

	RETURN_TRUE;
}

PHP_FUNCTION(pfSense_vlan_create) {
	char *ifname = NULL;
	char *parentifname = NULL;
	int ifname_len, parent_len;
	long tag, pcp; 
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
	int ifname_len;
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
	int ifname_len;
	long mtu;
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
	int flags, ifname_len;
	long value;

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
	int flags, ifname_len;
	long value;

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
	int ifname_len;
	int error = 0;
	int dev;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &ifname, &ifname_len) == FAILURE) {
		RETURN_NULL();
	}

	if ((dev = open("/dev/pf", O_RDWR)) < 0)
		RETURN_NULL();

	getifaddrs(&ifdata);
	if (ifdata == NULL) {
		close(dev);
		RETURN_NULL();
	}

	for(mb = ifdata; mb != NULL; mb = mb->ifa_next) {
		if (mb == NULL)
			continue;
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
		add_assoc_string(return_value, "interface", kif.pfik_name, 1);

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
	int ifname_len;
	int name[6];
	size_t len;
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
	add_assoc_long(return_value, "inpkts", (long)tmpd->ifi_ipackets);
	add_assoc_long(return_value, "inbytes", (long)tmpd->ifi_ibytes);
	add_assoc_long(return_value, "outpkts", (long)tmpd->ifi_opackets);
	add_assoc_long(return_value, "outbytes", (long)tmpd->ifi_obytes);
	add_assoc_long(return_value, "inerrs", (long)tmpd->ifi_ierrors);
	add_assoc_long(return_value, "outerrs", (long)tmpd->ifi_oerrors);
	add_assoc_long(return_value, "collisions", (long)tmpd->ifi_collisions);
	add_assoc_long(return_value, "inmcasts", (long)tmpd->ifi_imcasts);
	add_assoc_long(return_value, "outmcasts", (long)tmpd->ifi_omcasts);
	add_assoc_long(return_value, "unsuppproto", (long)tmpd->ifi_noproto);
	add_assoc_long(return_value, "mtu", (long)tmpd->ifi_mtu);
}

PHP_FUNCTION(pfSense_get_pf_rules) {
	int dev;
	struct pfioc_rule pr;
	uint32_t mnr, nr;
	zval *array;

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
		pr.nr = nr;
		if (ioctl(dev, DIOCGETRULE, &pr)) {
			add_assoc_string(return_value, "error", strerror(errno), 1);
			break;
		}

		ALLOC_INIT_ZVAL(array);
		array_init(array);
		add_assoc_long(array, "id", (long)pr.rule.nr);
		add_assoc_long(array, "tracker", (long)pr.rule.cuid);
		add_assoc_string(array, "label", pr.rule.label, 1);
		add_assoc_long(array, "evaluations", (long)pr.rule.evaluations);
		add_assoc_long(array, "packets", (long)(pr.rule.packets[0] + pr.rule.packets[1]));
		add_assoc_long(array, "bytes", (long)(pr.rule.bytes[0] + pr.rule.bytes[1]));
		add_assoc_long(array, "states", (long)pr.rule.u_states_cur);
		add_assoc_long(array, "pid", (long)pr.rule.cpid);
		add_assoc_long(array, "state creations", (long)pr.rule.u_states_tot);
		add_index_zval(return_value, pr.rule.nr, array);
	}
	close(dev);
}

PHP_FUNCTION(pfSense_get_pf_states) {
	char buf[128], *filter, *key;
	int dev, filter_if, filter_rl, found, i, min, sec;
	struct pfioc_states ps;
	struct pfsync_state *s;
	struct pfsync_state_peer *src, *dst;
	struct pfsync_state_key *sk, *nk;
	struct protoent *p;
	uint32_t expire, creation;
	uint64_t bytes[2], id, packets[2];
	unsigned int key_len;
	unsigned long index;
	zval *array, **data1, **data2, *zvar;
	HashTable *hash1, *hash2;
	HashPosition h1p, h2p;

	filter = NULL;
	filter_if = filter_rl = 0;
	zvar = NULL;
	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "|z", &zvar) == FAILURE)
		RETURN_NULL();
	if (zvar != NULL && Z_TYPE_P(zvar) == IS_ARRAY) {
		hash1 = Z_ARRVAL_P(zvar);
		for (zend_hash_internal_pointer_reset_ex(hash1, &h1p);
		    zend_hash_get_current_data_ex(hash1, (void**)&data1, &h1p) == SUCCESS;
		    zend_hash_move_forward_ex(hash1, &h1p)) {
			if (zend_hash_get_current_key_ex(hash1, &key, &key_len,
			    &index, 0, &h1p) != HASH_KEY_IS_LONG ||
			    Z_TYPE_PP(data1) != IS_ARRAY) {
				continue;
			}
			hash2 = Z_ARRVAL_PP(data1);
			zend_hash_internal_pointer_reset_ex(hash2, &h2p);
			if (zend_hash_get_current_data_ex(hash2, (void**)&data2, &h2p) != SUCCESS)
				RETURN_NULL();

			if (zend_hash_get_current_key_ex(hash2, &key, &key_len,
			    &index, 0, &h2p) != HASH_KEY_IS_STRING) {
				continue;
			}
			if (key_len == 10 && strcasecmp(key, "interface") == 0 &&
			    Z_TYPE_PP(data2) == IS_STRING) {
				filter_if = 1;
			} else if (key_len == 7 && strcasecmp(key, "ruleid") == 0 &&
			    Z_TYPE_PP(data2) == IS_LONG) {
				filter_rl = 1;
			} else if (key_len == 7 && strcasecmp(key, "filter") == 0 &&
			    Z_TYPE_PP(data2) == IS_STRING) {
				filter = Z_STRVAL_PP(data2);
			}
		}
		if (filter_if && filter_rl)
			RETURN_NULL();
	}

	if ((dev = open("/dev/pf", O_RDWR)) < 0)
		RETURN_NULL();
	if (get_pf_states(dev, &ps) == -1) {
		close(dev);
		RETURN_NULL();
	}
	if (ps.ps_len == 0) {
		free(ps.ps_buf);
		close(dev);
		RETURN_NULL();
	}

	s = ps.ps_states;
	array_init(return_value);
	for (i = 0; i < ps.ps_len; i += sizeof(*s), s++) {
		if (filter_if || filter_rl) {
			found = 0;
			hash1 = Z_ARRVAL_P(zvar);
			for (zend_hash_internal_pointer_reset_ex(hash1, &h1p);
			    zend_hash_get_current_data_ex(hash1, (void**)&data1, &h1p) == SUCCESS;
			    zend_hash_move_forward_ex(hash1, &h1p)) {
				hash2 = Z_ARRVAL_PP(data1);
				zend_hash_internal_pointer_reset_ex(hash2, &h2p);
				if (zend_hash_get_current_data_ex(hash2, (void**)&data2, &h2p) != SUCCESS) {
					free(ps.ps_buf);
					close(dev);
					RETURN_NULL();
				}
				if (filter_if) {
					if (strcasecmp(s->ifname, Z_STRVAL_PP(data2)) == 0)
						found = 1;
				} else if (filter_rl) {
					if (ntohl(s->rule) != -1 &&
					    (long)ntohl(s->rule) == Z_LVAL_PP(data2)) {
						found = 1;
					}
				}
			}
			if (!found)
				continue;
		}

	        if (s->direction == PF_OUT) {
			src = &s->src;
			dst = &s->dst;
			sk = &s->key[PF_SK_STACK];
			nk = &s->key[PF_SK_WIRE];
			if (s->proto == IPPROTO_ICMP || s->proto == IPPROTO_ICMPV6)
				sk->port[0] = nk->port[0];
		} else {
			src = &s->dst;
			dst = &s->src;
			sk = &s->key[PF_SK_WIRE];
			nk = &s->key[PF_SK_STACK];
			if (s->proto == IPPROTO_ICMP || s->proto == IPPROTO_ICMPV6)
				sk->port[1] = nk->port[1];
		}

		found = 0;
		ALLOC_INIT_ZVAL(array);
		array_init(array);

		add_assoc_string(array, "if", s->ifname, 1);
		if ((p = getprotobynumber(s->proto)) != NULL) {
			add_assoc_string(array, "proto", p->p_name, 1);
			if (filter != NULL && strstr(p->p_name, filter))
				found = 1;
		} else
			add_assoc_long(array, "proto", (long)s->proto);
		add_assoc_string(array, "direction",
		    ((s->direction == PF_OUT) ? "out" : "in"), 1);

		memset(buf, 0, sizeof(buf));
		pf_print_host(&nk->addr[1], nk->port[1], s->af, buf, sizeof(buf));
		add_assoc_string(array, ((s->direction == PF_OUT) ? "src" : "dst"), buf, 1);
		if (filter != NULL && !found && strstr(buf, filter))
			found = 1;

		if (PF_ANEQ(&nk->addr[1], &sk->addr[1], s->af) ||
		    nk->port[1] != sk->port[1]) {
			memset(buf, 0, sizeof(buf));
			pf_print_host(&sk->addr[1], sk->port[1], s->af, buf,
			    sizeof(buf));
			add_assoc_string(array,
			    ((s->direction == PF_OUT) ? "src-orig" : "dst-orig"), buf, 1);
			if (filter != NULL && !found && strstr(buf, filter))
				found = 1;
		}

		memset(buf, 0, sizeof(buf));
		pf_print_host(&nk->addr[0], nk->port[0], s->af, buf, sizeof(buf));
		add_assoc_string(array, ((s->direction == PF_OUT) ? "dst" : "src"), buf, 1);
		if (filter != NULL && !found && strstr(buf, filter))
			found = 1;

		if (PF_ANEQ(&nk->addr[0], &sk->addr[0], s->af) ||
		    nk->port[0] != sk->port[0]) {
			memset(buf, 0, sizeof(buf));
			pf_print_host(&sk->addr[0], sk->port[0], s->af, buf,
			    sizeof(buf));
			add_assoc_string(array,
			    ((s->direction == PF_OUT) ? "dst-orig" : "src-orig"), buf, 1);
			if (filter != NULL && !found && strstr(buf, filter))
				found = 1;
		}

		if (s->proto == IPPROTO_TCP) {
			if (src->state <= TCPS_TIME_WAIT &&
			    dst->state <= TCPS_TIME_WAIT) {
				snprintf(buf, sizeof(buf) - 1, "%s:%s",
				    tcpstates[src->state], tcpstates[dst->state]);
				add_assoc_string(array, "state", buf, 1);
				if (filter != NULL && !found &&
				    (strstr(tcpstates[src->state], filter) ||
				    strstr(tcpstates[dst->state], filter))) {
					found = 1;
				}
			} else if (src->state == PF_TCPS_PROXY_SRC ||
			    dst->state == PF_TCPS_PROXY_SRC)
				add_assoc_string(array, "state", "PROXY:SRC", 1);
			else if (src->state == PF_TCPS_PROXY_DST ||
			    dst->state == PF_TCPS_PROXY_DST)
				add_assoc_string(array, "state", "PROXY:DST", 1);
			else {
				snprintf(buf, sizeof(buf) - 1,
				    "<BAD STATE LEVELS %u:%u>",
				    src->state, dst->state);
				add_assoc_string(array, "state", buf, 1);
			}
		} else if (s->proto == IPPROTO_UDP && src->state < PFUDPS_NSTATES &&
		    dst->state < PFUDPS_NSTATES) {
			const char *states[] = PFUDPS_NAMES;

			snprintf(buf, sizeof(buf) - 1, "%s:%s",
			    states[src->state], states[dst->state]);
			add_assoc_string(array, "state", buf, 1);

			if (filter != NULL && !found &&
			    (strstr(states[src->state], filter) ||
			    strstr(states[dst->state], filter))) {
				found = 1;
			}
		} else if (s->proto != IPPROTO_ICMP && src->state < PFOTHERS_NSTATES &&
		    dst->state < PFOTHERS_NSTATES) {
			/* XXX ICMP doesn't really have state levels */
			const char *states[] = PFOTHERS_NAMES;

			snprintf(buf, sizeof(buf) - 1, "%s:%s",
			    states[src->state], states[dst->state]);
			add_assoc_string(array, "state", buf, 1);

			if (filter != NULL && !found &&
			    (strstr(states[src->state], filter) ||
			    strstr(states[dst->state], filter))) {
				found = 1;
			}
		} else {
			snprintf(buf, sizeof(buf) - 1, "%u:%u", src->state, dst->state);
			add_assoc_string(array, "state", buf, 1);
		}

		if (filter != NULL && !found) {
			zval_dtor(array);
			continue;
		}

		creation = ntohl(s->creation);
		sec = creation % 60;
		creation /= 60;
		min = creation % 60;
		creation /= 60;
		snprintf(buf, sizeof(buf) - 1, "%.2u:%.2u:%.2u", creation, min, sec);
		add_assoc_string(array, "age", buf, 1);
		expire = ntohl(s->expire);
		sec = expire % 60;
		expire /= 60;
		min = expire % 60;
		expire /= 60;
		snprintf(buf, sizeof(buf) - 1, "%.2u:%.2u:%.2u", expire, min, sec);
		add_assoc_string(array, "expires in", buf, 1);

		bcopy(s->packets[0], &packets[0], sizeof(uint64_t));
		bcopy(s->packets[1], &packets[1], sizeof(uint64_t));
		bcopy(s->bytes[0], &bytes[0], sizeof(uint64_t));
		bcopy(s->bytes[1], &bytes[1], sizeof(uint64_t));
		add_assoc_long(array, "packets total",
		    (long)(be64toh(packets[0]) + be64toh(packets[1])));
		add_assoc_long(array, "packets in", (long)be64toh(packets[0]));
		add_assoc_long(array, "packets out", (long)be64toh(packets[1]));
		add_assoc_long(array, "bytes total",
		    (long)(be64toh(bytes[0]) + be64toh(bytes[1])));
		add_assoc_long(array, "bytes in", (long)be64toh(bytes[0]));
		add_assoc_long(array, "bytes out", (long)be64toh(bytes[1]));
		if (ntohl(s->anchor) != -1)
			add_assoc_long(array, "anchor", (long)ntohl(s->anchor));
		if (ntohl(s->rule) != -1)
			add_assoc_long(array, "rule", (long)ntohl(s->rule));

		bcopy(&s->id, &id, sizeof(uint64_t));
		snprintf(buf, sizeof(buf) - 1, "%016jx", (uintmax_t)be64toh(id));
		add_assoc_string(array, "id", buf, 1);
		snprintf(buf, sizeof(buf) - 1, "%08x", ntohl(s->creatorid));
		add_assoc_string(array, "creatorid", buf, 1);

		add_next_index_zval(return_value, array);
	}
	free(ps.ps_buf);
	close(dev);
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
		add_assoc_string(return_value, "error", strerror(errno), 1);
	} else {


	bzero(&status, sizeof(status));
	if (ioctl(dev, DIOCGETSTATUS, &status)) {
		add_assoc_string(return_value, "error", strerror(errno), 1);
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

#if (__FreeBSD_version < 1000000)
		add_assoc_long(return_value, "stateid", (unsigned long long)status.stateid);
#endif

		add_assoc_long(return_value, "running", status.running);
		add_assoc_long(return_value, "states", status.states);
		add_assoc_long(return_value, "srcnodes", status.src_nodes);

		add_assoc_long(return_value, "hostid", ntohl(status.hostid));
		for (i = 0; i < PF_MD5_DIGEST_LENGTH; i++) {
			buf[i + i] = hex[status.pf_chksum[i] >> 4];
			buf[i + i + 1] = hex[status.pf_chksum[i] & 0x0f];
		}
		buf[i + i] = '\0';
		add_assoc_string(return_value, "pfchecksum", buf, 1);
		printf("Checksum: 0x%s\n\n", buf);

		switch(status.debug) {
		case PF_DEBUG_NONE:
			add_assoc_string(return_value, "debuglevel", "none", 1);
			break;
		case PF_DEBUG_URGENT:
			add_assoc_string(return_value, "debuglevel", "urgent", 1);
			break;
		case PF_DEBUG_MISC:
			add_assoc_string(return_value, "debuglevel", "misc", 1);
			break;
		case PF_DEBUG_NOISY:
			add_assoc_string(return_value, "debuglevel", "noisy", 1);
			break;
		default:
			add_assoc_string(return_value, "debuglevel", "unknown", 1);
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
			add_assoc_string(return_value, "uptime", statline, 1);
		}
	}
	close(dev);
	}
}

PHP_FUNCTION(pfSense_sync) {
	sync();
}

PHP_FUNCTION(pfSense_fsync) {
	char *parent_dir = NULL;
	char *fname = NULL;
	int fname_len, fd;

	if (ZEND_NUM_ARGS() != 1) {
		RETURN_FALSE;
	}

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &fname, &fname_len) == FAILURE) {
		RETURN_FALSE;
	}

	if (strlen(fname) == 0) {
		RETURN_FALSE;
	}

	if ((fd = open(fname, O_RDONLY|O_CLOEXEC)) == -1) {
		php_printf("\tcan't open %s\n", fname);
		RETURN_FALSE;
	}

	if (fsync(fd) == -1) {
		php_printf("\tcan't fsync %s\n", fname);
		close(fd);
		RETURN_FALSE;
	}
	close(fd);

	if ((parent_dir = dirname(fname)) == NULL)
		RETURN_FALSE;

	if ((fd = open(parent_dir, O_RDONLY|O_CLOEXEC)) == -1)
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
	long			poll_timeout = 700;

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
			add_assoc_string(return_value, path, path, 1);
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
				add_assoc_string(return_value, path, path, 1);
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
	size_t len;	
	char *data;

	array_init(return_value);

	mib[0] = CTL_HW;
	mib[1] = HW_MACHINE;
	if (!sysctl(mib, 2, NULL, &len, NULL, 0)) {
		data = malloc(len);
		if (data != NULL) {
			if (!sysctl(mib, 2, data, &len, NULL, 0)) {
				add_assoc_string(return_value, "hwmachine", data, 1);
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
				add_assoc_string(return_value, "hwmodel", data, 1);
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
				add_assoc_string(return_value, "hwarch", data, 1);
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
	len = sizeof(idata);
	if (!sysctl(mib, 2, &idata, &len, NULL, 0))
		add_assoc_long(return_value, "physmem", idata);

	mib[0] = CTL_HW;
	mib[1] = HW_USERMEM;
	len = sizeof(idata);
	if (!sysctl(mib, 2, &idata, &len, NULL, 0))
		add_assoc_long(return_value, "usermem", idata);

	mib[0] = CTL_HW;
	mib[1] = HW_REALMEM;
	len = sizeof(idata);
	if (!sysctl(mib, 2, &idata, &len, NULL, 0))
		add_assoc_long(return_value, "realmem", idata);
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
				add_assoc_string(return_value, "hostuuid", data, 1);
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
				add_assoc_string(return_value, "hostname", data, 1);
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
				add_assoc_string(return_value, "osrelease", data, 1);
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
				add_assoc_string(return_value, "oskernel_version", data, 1);
				free(data);
			}
		}
	}

	mib[0] = CTL_KERN;
	mib[1] = KERN_BOOTTIME;
	len = sizeof(bootime);
	if (!sysctl(mib, 2, &bootime, &len, NULL, 0))
		add_assoc_string(return_value, "boottime", ctime(&bootime.tv_sec), 1);

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
	int done = 0, level = 0;
	zval *nestedarrs[32];

	nestedarrs[level] = (zval *) salist;

	while (!done) {
		name = value = NULL;
		vici_parse_t pres;
		pres = vici_parse(res);
		switch (pres) {
			case VICI_PARSE_BEGIN_SECTION:
				name = vici_parse_name(res);
				ALLOC_INIT_ZVAL(nestedarrs[level + 1]);
				array_init(nestedarrs[level + 1]);
				add_assoc_zval(nestedarrs[level], name, nestedarrs[level + 1]);
				Z_ADDREF_P(nestedarrs[level + 1]);
				level++;
				break;
			case VICI_PARSE_END_SECTION:
				nestedarrs[level] = NULL;
				level--;
				break;
			case VICI_PARSE_KEY_VALUE:
				name = vici_parse_name(res);
				value = vici_parse_value_str(res);
				add_assoc_string(nestedarrs[level], name, value, 1);
				break;
			case VICI_PARSE_BEGIN_LIST:
				name = vici_parse_name(res);
				ALLOC_INIT_ZVAL(nestedarrs[level + 1]);
				array_init(nestedarrs[level + 1]);
				add_assoc_zval(nestedarrs[level], name, nestedarrs[level + 1]);
				Z_ADDREF_P(nestedarrs[level + 1]);
				level++;
				break;
			case VICI_PARSE_END_LIST:
				nestedarrs[level] = NULL;
				level--;
				break;
			case VICI_PARSE_LIST_ITEM:
				value = vici_parse_value_str(res);
				add_next_index_string(nestedarrs[level], value, 1);
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
