/*
 * pfsense.c
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2023 Rubicon Communications, LLC (Netgate)
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
 * Functions copied from util.c and modem.c of mpd5 source are protected by
 * this copyright.
 * They are ExclusiveOpenDevice/ExclusiveCloseDevice and
 * OpenSerialDevice.
 * Copyright (c) 1995-1999 Whistle Communications, Inc. All rights reserved.
 * Subject to the following obligations and disclaimer of warranty,
 * use and redistribution of this software, in source or object code
 * forms, with or without modifications are expressly permitted by
 * Whistle Communications; provided, however, that:   (i) any and
 * all reproductions of the source or object code must include the
 * copyright notice above and the following disclaimer of warranties;
 * and (ii) no rights are granted, in any manner or form, to use
 * Whistle Communications, Inc. trademarks, including the mark "WHISTLE
 * COMMUNICATIONS" on advertising, endorsements, or otherwise except
 * as such appears in the above copyright notice or in the software.
 * THIS SOFTWARE IS BEING PROVIDED BY WHISTLE COMMUNICATIONS "AS IS",
 * AND TO THE MAXIMUM EXTENT PERMITTED BY LAW, WHISTLE COMMUNICATIONS
 * MAKES NO REPRESENTATIONS OR WARRANTIES, EXPRESS OR IMPLIED,
 * REGARDING THIS SOFTWARE, INCLUDING WITHOUT LIMITATION, ANY AND
 * ALL IMPLIED WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
 * PURPOSE, OR NON-INFRINGEMENT.  WHISTLE COMMUNICATIONS DOES NOT
 * WARRANT, GUARANTEE, OR MAKE ANY REPRESENTATIONS REGARDING THE USE
 * OF, OR THE RESULTS OF THE USE OF THIS SOFTWARE IN TERMS OF ITS
 * CORRECTNESS, ACCURACY, RELIABILITY OR OTHERWISE.  IN NO EVENT
 * SHALL WHISTLE COMMUNICATIONS BE LIABLE FOR ANY DAMAGES RESULTING
 * FROM OR ARISING OUT OF ANY USE OF THIS SOFTWARE, INCLUDING WITHOUT
 * LIMITATION, ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * PUNITIVE, OR CONSEQUENTIAL DAMAGES, PROCUREMENT OF SUBSTITUTE
 * GOODS OR SERVICES, LOSS OF USE, DATA OR PROFITS, HOWEVER CAUSED
 * AND UNDER ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF WHISTLE COMMUNICATIONS
 * IS ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

#ifdef HAVE_CONFIG_H
# include "config.h"
#endif

#include "php.h"
#include "ext/standard/info.h"
#include "php_pfSense.h"
#include "pfSense_arginfo.h"

#include "pfSense_private.h"

ZEND_DECLARE_MODULE_GLOBALS(pfSense)

/* For compatibility with older PHP versions */
#ifndef ZEND_PARSE_PARAMETERS_NONE
#define ZEND_PARSE_PARAMETERS_NONE() \
	ZEND_PARSE_PARAMETERS_START(0, 0) \
	ZEND_PARSE_PARAMETERS_END()
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
	char *php_ip1 = NULL, *php_ip2 = NULL;
	size_t ip1_len = 0, ip2_len = 0;

	char *ip1 = NULL, *ip2 = NULL;

	ZEND_PARSE_PARAMETERS_START(1, 2)
		Z_PARAM_STRING(php_ip1, ip1_len)
		Z_PARAM_OPTIONAL
		Z_PARAM_STRING(php_ip2, ip2_len)
	ZEND_PARSE_PARAMETERS_END();

	if ((dev = open("/dev/pf", O_RDWR)) < 0)
		RETURN_NULL();

	killed = sources = 0;

	memset(&psnk, 0, sizeof(psnk));
	memset(&psnk.psnk_src.addr.v.a.mask, 0xff,
	    sizeof(psnk.psnk_src.addr.v.a.mask));
	memset(&last_src, 0xff, sizeof(last_src));
	memset(&last_dst, 0xff, sizeof(last_dst));

	/* make copies to avoid scribbling over PHP */
	ip1 = strdup(php_ip1);

	/* optional param, could be null */
	if (php_ip2)
		ip2 = strdup(php_ip2);

	if (pfctl_addrprefix(ip1, &psnk.psnk_src.addr.v.a.mask) < 0) {
		RETVAL_NULL();
		goto cleanup1;
	}

	if ((ret_ga = getaddrinfo(ip1, NULL, NULL, &res[0])) != 0) {
		php_printf("getaddrinfo: %s", gai_strerror(ret_ga));
		RETVAL_NULL();
		goto cleanup1;
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
			if (pfctl_addrprefix(ip2,&psnk.psnk_dst.addr.v.a.mask) < 0 ) {
				RETVAL_NULL();
				goto cleanup2;
			}
			if ((ret_ga = getaddrinfo(ip2, NULL, NULL,
			    &res[1]))) {
				php_printf("getaddrinfo: %s", gai_strerror(ret_ga));
				RETVAL_NULL();
				goto cleanup2;
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

	RETVAL_TRUE;

cleanup2:
	freeaddrinfo(res[0]);
cleanup1:
	if (ip2)
		free(ip2);
	free(ip1);
	close(dev);
}

PHP_FUNCTION(pfSense_kill_states)
{
	struct pfctl_kill k;
	struct addrinfo *res[2], *resp[2];
	struct sockaddr last_src, last_dst;
	int ret_ga;
	unsigned int kcount;

	int dev;
	char *php_ip1 = NULL, *php_ip2 = NULL, *proto = NULL, *iface = NULL;
	size_t ip1_len = 0, ip2_len = 0, proto_len = 0, iface_len = 0;

	char *ip1 = NULL;
	char *ip2 = NULL;

	ZEND_PARSE_PARAMETERS_START(1, 4)
		Z_PARAM_STRING(php_ip1, ip1_len)
		Z_PARAM_OPTIONAL
		Z_PARAM_STRING(php_ip2, ip2_len)
		Z_PARAM_STRING(iface, iface_len)
		Z_PARAM_STRING(proto, proto_len)
	ZEND_PARSE_PARAMETERS_END();

	if ((dev = open("/dev/pf", O_RDWR)) < 0)
		RETURN_NULL();

	memset(&k, 0, sizeof(k));
	memset(&k.src.addr.v.a.mask, 0xff,
	    sizeof(k.src.addr.v.a.mask));
	memset(&last_src, 0xff, sizeof(last_src));
	memset(&last_dst, 0xff, sizeof(last_dst));

	/* make copies to avoid scribbling over PHP: Redmine #9270 */
	ip1 = strdup(php_ip1);

	/* optional param, could be null. Redmine #9270 */
	if (php_ip2)
		ip2 = strdup(php_ip2);

	if (iface != NULL && iface_len > 0 && strlcpy(k.ifname, iface,
	    sizeof(k.ifname)) >= sizeof(k.ifname))
		php_printf("invalid interface: %s", iface);

	if (proto != NULL && proto_len > 0) {
		if (!strncmp(proto, "tcp", strlen("tcp")))
			k.proto = IPPROTO_TCP;
		else if (!strncmp(proto, "udp", strlen("udp")))
			k.proto = IPPROTO_UDP;
		else if (!strncmp(proto, "icmpv6", strlen("icmpv6")))
			k.proto = IPPROTO_ICMPV6;
		else if (!strncmp(proto, "icmp", strlen("icmp")))
			k.proto = IPPROTO_ICMP;
	}

	if (pfctl_addrprefix(ip1, &k.src.addr.v.a.mask) < 0) {
		RETVAL_NULL();
		goto cleanup1;
	}

	if ((ret_ga = getaddrinfo(ip1, NULL, NULL, &res[0])) != 0) {
		php_printf("getaddrinfo: %s", gai_strerror(ret_ga));
		RETVAL_NULL();
		goto cleanup1;
	}

	for (resp[0] = res[0]; resp[0]; resp[0] = resp[0]->ai_next) {
		if (resp[0]->ai_addr == NULL)
			continue;
		/* We get lots of duplicates.  Catch the easy ones */
		if (memcmp(&last_src, resp[0]->ai_addr, sizeof(last_src)) == 0)
			continue;
		last_src = *(struct sockaddr *)resp[0]->ai_addr;

		k.af = resp[0]->ai_family;

		if (k.af == AF_INET)
			k.src.addr.v.a.addr.v4 =
			    ((struct sockaddr_in *)resp[0]->ai_addr)->sin_addr;
		else if (k.af == AF_INET6)
			k.src.addr.v.a.addr.v6 =
			    ((struct sockaddr_in6 *)resp[0]->ai_addr)->
			    sin6_addr;
		else {
			php_printf("Unknown address family %d", k.af);
			continue;
		}

		if (ip2 != NULL && ip2_len > 0) {
			memset(&k.dst.addr.v.a.mask, 0xff,
			    sizeof(k.dst.addr.v.a.mask));
			memset(&last_dst, 0xff, sizeof(last_dst));
			if (pfctl_addrprefix(ip2,&k.dst.addr.v.a.mask) < 0) {
				RETVAL_NULL();
				goto cleanup2;
			}
			if ((ret_ga = getaddrinfo(ip2, NULL, NULL,
			    &res[1]))) {
				php_printf("getaddrinfo: %s",
				    gai_strerror(ret_ga));
				RETVAL_NULL();
				goto cleanup2;
			}
			for (resp[1] = res[1]; resp[1];
			    resp[1] = resp[1]->ai_next) {
				if (resp[1]->ai_addr == NULL)
					continue;
				if (k.af != resp[1]->ai_family)
					continue;

				if (memcmp(&last_dst, resp[1]->ai_addr,
				    sizeof(last_dst)) == 0)
					continue;
				last_dst = *(struct sockaddr *)resp[1]->ai_addr;

				if (k.af == AF_INET)
					k.dst.addr.v.a.addr.v4 =
					    ((struct sockaddr_in *)resp[1]->
					    ai_addr)->sin_addr;
				else if (k.af == AF_INET6)
					k.dst.addr.v.a.addr.v6 =
					    ((struct sockaddr_in6 *)resp[1]->
					    ai_addr)->sin6_addr;
				else {
					php_printf("Unknown address family %d", k.af);
					continue;
				}

				if (pfctl_kill_states(dev, &k, &kcount))
					php_printf("Could not kill states\n");
			}
			freeaddrinfo(res[1]);
		} else {
			if (pfctl_kill_states(dev, &k, &kcount)) {
				php_printf("Could not kill states\n");
				break;
			}
		}
	}

	RETVAL_TRUE;

cleanup2:
	freeaddrinfo(res[0]);
cleanup1:
	if (ip2)
		free(ip2);
	free(ip1);
	close(dev);
}

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

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STRING(dev, devlen)
	ZEND_PARSE_PARAMETERS_END();

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

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STRING(dev, devlen)
		Z_PARAM_LONG(port)
	ZEND_PARSE_PARAMETERS_END();

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

	ZEND_PARSE_PARAMETERS_START(3, 3)
		Z_PARAM_STRING(dev, devlen)
		Z_PARAM_LONG(port)
		Z_PARAM_LONG(pvid)
	ZEND_PARSE_PARAMETERS_END();

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
	size_t devlen, statelen;
	zend_long port;

	ZEND_PARSE_PARAMETERS_START(3, 3)
		Z_PARAM_STRING(dev, devlen)
		Z_PARAM_LONG(port)
		Z_PARAM_STRING(state, statelen)
	ZEND_PARSE_PARAMETERS_END();

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

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STRING(dev, devlen)
		Z_PARAM_LONG(laggroup)
	ZEND_PARSE_PARAMETERS_END();

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

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STRING(dev, devlen)
		Z_PARAM_LONG(vlangroup)
	ZEND_PARSE_PARAMETERS_END();

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
	zval *zvar = NULL;
	zend_long laggroup;
	HashTable *hash1, *hash2;
	zval *val, *val2;
	zend_long lkey, lkey2;
	zend_string *skey, *skey2;

	ZEND_PARSE_PARAMETERS_START(2, 3)
		Z_PARAM_STRING(dev, devlen)
		Z_PARAM_LONG(laggroup)
		Z_PARAM_OPTIONAL
		Z_PARAM_ZVAL(zvar)
	ZEND_PARSE_PARAMETERS_END();

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
	zval *zvar = NULL;
	HashTable *hash1, *hash2;
	zval *val, *val2;
	zend_long lkey, lkey2;
	zend_string *skey, *skey2;

	ZEND_PARSE_PARAMETERS_START(3, 4)
		Z_PARAM_STRING(dev, devlen)
		Z_PARAM_LONG(vlangroup)
		Z_PARAM_LONG(vlan)
		Z_PARAM_OPTIONAL
		Z_PARAM_ZVAL(zvar)
	ZEND_PARSE_PARAMETERS_END();

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

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STRING(dev, devlen)
		Z_PARAM_STRING(mode, modelen)
	ZEND_PARSE_PARAMETERS_END();

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

	ZEND_PARSE_PARAMETERS_START(1, 2)
		Z_PARAM_STRING(ip, ip_len)
		Z_PARAM_OPTIONAL
		Z_PARAM_STRING(rifname, ifname_len)
	ZEND_PARSE_PARAMETERS_END();

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

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STRING(ifname, ifname_len)
	ZEND_PARSE_PARAMETERS_END();

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

/*
 * Modified from sbin/ifconfig/ifconfig.c - mergesort
 */
static struct ifaddrs *
sortifaddrs(struct ifaddrs *list,
    int (*compare)(struct ifaddrs *, struct ifaddrs *))
{
	struct ifaddrs *right, *temp, *last, *result, *next, *tail;
	
	right = list;
	temp = list;
	last = list;
	result = NULL;
	next = NULL;
	tail = NULL;

	if (!list || !list->ifa_next)
		return (list);

	if (list->ifa_next && !list->ifa_next->ifa_next) {
		if (compare(list, list->ifa_next) > 0) {
			result = list->ifa_next;
			list->ifa_next = NULL;
			result->ifa_next = list;
			return(result);
		}
		return(list);
	}

	while (temp && temp->ifa_next) {
		last = right;
		right = right->ifa_next;
		temp = temp->ifa_next->ifa_next;
	}

	last->ifa_next = NULL;

	list = sortifaddrs(list, compare);
	right = sortifaddrs(right, compare);

	while (list || right) {
		if (!right) {
			next = list;
			list = list->ifa_next;
		} else if (!list) {
			next = right;
			right = right->ifa_next;
		} else if (compare(list, right) < 1) {
			next = list;
			list = list->ifa_next;
		} else {
			next = right;
			right = right->ifa_next;
		}

		if (!result)
			result = next;
		else
			tail->ifa_next = next;

		tail = next;
	}

	return (result);
}

static int
cmpifaddrs(struct ifaddrs *a, struct ifaddrs *b)
{
	if (!b) {
		return(-1);
	} else if (!a) {
		return(1);
	} else {
		return (strcmp(a->ifa_name, b->ifa_name));
	}
}

/**
 * Fill zval array val with interface attributes in struct ifaddrs *mb
 */
static void
fill_interface_params(zval *val, struct ifaddrs *mb)
{
	zval caps;
	zval encaps;
	char outputbuf[128];
	struct sockaddr_dl *tmpdl;
	struct ifreq ifr;
	struct if_data *md;
	
	if (mb->ifa_flags & IFF_UP)
		add_assoc_string(val, "status", "up");
	else
		add_assoc_string(val, "status", "down");
	if (mb->ifa_flags & IFF_LINK0)
		add_assoc_long(val, "link0", 1);
	if (mb->ifa_flags & IFF_LINK1)
		add_assoc_long(val, "link1", 1);
	if (mb->ifa_flags & IFF_LINK2)
		add_assoc_long(val, "link2", 1);
	if (mb->ifa_flags & IFF_MULTICAST)
		add_assoc_long(val, "multicast", 1);
	if (mb->ifa_flags & IFF_LOOPBACK)
		add_assoc_long(val, "loopback", 1);
	if (mb->ifa_flags & IFF_POINTOPOINT)
		add_assoc_long(val, "pointtopoint", 1);
	if (mb->ifa_flags & IFF_PROMISC)
		add_assoc_long(val, "promisc", 1);
	if (mb->ifa_flags & IFF_PPROMISC)
		add_assoc_long(val, "permanentpromisc", 1);
	if (mb->ifa_flags & IFF_OACTIVE)
		add_assoc_long(val, "oactive", 1);
	if (mb->ifa_flags & IFF_ALLMULTI)
		add_assoc_long(val, "allmulti", 1);
	if (mb->ifa_flags & IFF_SIMPLEX)
		add_assoc_long(val, "simplex", 1);
	memset(&ifr, 0, sizeof(ifr));
	strncpy(ifr.ifr_name, mb->ifa_name, sizeof(ifr.ifr_name));
	if (mb->ifa_data != NULL) {
		md = mb->ifa_data;
		if (md->ifi_link_state == LINK_STATE_UP)
			add_assoc_long(val, "linkstateup", 1);
		switch (md->ifi_type) {
		case IFT_IEEE80211:
			add_assoc_string(val, "iftype",
			    "wireless");
			break;
		case IFT_ETHER:
		case IFT_FASTETHER:
		case IFT_FASTETHERFX:
		case IFT_GIGABITETHERNET:
			if (ioctl(PFSENSE_G(s), SIOCG80211STATS,
			    (caddr_t)&ifr) == 0) {
				add_assoc_string(val, "iftype",
				    "wireless");
				/* Reset ifr after use. */
				memset(&ifr, 0, sizeof(ifr));
				strncpy(ifr.ifr_name, mb->ifa_name,
				    sizeof(ifr.ifr_name));
			} else {
				add_assoc_string(val, "iftype",
				    "ether");
			}
			break;
		case IFT_L2VLAN:
			add_assoc_string(val, "iftype",
			    "vlan");
			break;
		case IFT_BRIDGE:
			add_assoc_string(val, "iftype",
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
			add_assoc_string(val, "iftype",
			    "virtual");
			break;
		default:
			add_assoc_string(val, "iftype",
			    "other");
		}
	}

	/* Interface-wide parameters */
	array_init(&caps);
	array_init(&encaps);
	if (ioctl(PFSENSE_G(s), SIOCGIFMTU, (caddr_t)&ifr) == 0)
		add_assoc_long(val, "mtu", ifr.ifr_mtu);
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

	add_assoc_zval(val, "caps", &caps);
	add_assoc_zval(val, "encaps", &encaps);

	tmpdl = (struct sockaddr_dl *)mb->ifa_addr;
	if (tmpdl->sdl_alen == ETHER_ADDR_LEN) {
		bzero(outputbuf, sizeof outputbuf);
		ether_ntoa_r((struct ether_addr *)LLADDR(tmpdl), outputbuf);
		add_assoc_string(val, "macaddr", outputbuf);
	}
	if (tmpdl->sdl_type == IFT_ETHER) {
		memcpy(&ifr.ifr_addr, mb->ifa_addr,
		    sizeof(mb->ifa_addr->sa_len));
		ifr.ifr_addr.sa_family = AF_LOCAL;
	}
	if (ioctl(PFSENSE_G(s), SIOCGHWADDR, &ifr) == 0) {
		bzero(outputbuf, sizeof outputbuf);
		ether_ntoa_r((const struct ether_addr *)&ifr.ifr_addr.sa_data,
		    outputbuf);
		add_assoc_string(val, "hwaddr", outputbuf);
	}
}

/**
 * Alternate hybrid of pfSense_getall_interface_addresses and
 * pfSense_get_interface_addresses. Return iface information and array of v4 and
 * v6 address
 */
PHP_FUNCTION(pfSense_get_ifaddrs)
{
	struct ifaddrs *ifdata, *sifdata, *mb;
	struct in6_ifreq ifr6;
	int llflag;
	char outputbuf[128];
	char *ifname;
	size_t ifname_len;
	zval addrs4, addrs6;
	
	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STRING(ifname, ifname_len)
	ZEND_PARSE_PARAMETERS_END();

	if (getifaddrs(&ifdata) == -1)
		RETURN_NULL();

	sifdata = sortifaddrs(ifdata, cmpifaddrs);

	for (mb = sifdata; mb != NULL; mb = mb->ifa_next) {

		if (ifname_len != strlen(mb->ifa_name))
			continue;
		if (strncmp(ifname, mb->ifa_name, ifname_len) == 0)
			break;
	}

	/* We didn't find our iface */
	if (mb == NULL)
		goto out;
	
	array_init(return_value);
	array_init(&addrs4);
	array_init(&addrs6);

	fill_interface_params(return_value, mb);
	
	/* loop until iface name changes or we exhaust the list */
	for (; mb != NULL; mb = mb->ifa_next) {
		zval addr;
		struct sockaddr_in *tmp = NULL;
		struct sockaddr_in6 *tmp6 = NULL;

		if (strcmp(ifname, mb->ifa_name) !=0)
			break;
		switch (mb->ifa_addr->sa_family) {
		case AF_INET:
			bzero(&addr, sizeof(addr));
			array_init(&addr);
			bzero(outputbuf, sizeof outputbuf);
			tmp = (struct sockaddr_in *)mb->ifa_addr;
			inet_ntop(AF_INET, (void *)&tmp->sin_addr, outputbuf,
			    sizeof(outputbuf));
			add_assoc_string(&addr, "addr", outputbuf);
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
			add_assoc_long(&addr, "subnetbits", i);

			bzero(outputbuf, sizeof outputbuf);
			inet_ntop(AF_INET, (void *)&tmp->sin_addr, outputbuf,
			    sizeof(outputbuf));
			add_assoc_string(&addr, "subnet", outputbuf);

			if (mb->ifa_flags & IFF_BROADCAST) {
				bzero(outputbuf, sizeof outputbuf);
				tmp = (struct sockaddr_in *)mb->ifa_broadaddr;
				inet_ntop(AF_INET, (void *)&tmp->sin_addr,
				    outputbuf, sizeof(outputbuf));
				add_assoc_string(&addr, "broadcast",
				    outputbuf);
			}

			if (mb->ifa_flags & IFF_POINTOPOINT) {
				tmp = (struct sockaddr_in *)mb->ifa_dstaddr;
				if (tmp != NULL && tmp->sin_family == AF_INET) {
					bzero(outputbuf, sizeof outputbuf);
					inet_ntop(AF_INET,
					    (void *)&tmp->sin_addr, outputbuf,
					    sizeof(outputbuf));
					add_assoc_string(&addr, "tunnel",
					    outputbuf);
				}
			}
			add_next_index_zval(&addrs4, &addr);
			break;
		case AF_INET6:
			bzero(&addr, sizeof(addr));
			array_init(&addr);
			bzero(outputbuf, sizeof outputbuf);
			tmp6 = (struct sockaddr_in6 *)mb->ifa_addr;
			if (IN6_IS_ADDR_LINKLOCAL(&tmp6->sin6_addr))
				break;
			inet_ntop(AF_INET6, (void *)&tmp6->sin6_addr, outputbuf,
			    sizeof(outputbuf));
			add_assoc_string(&addr, "addr", outputbuf);

			memset(&ifr6, 0, sizeof(ifr6));
			strncpy(ifr6.ifr_name, mb->ifa_name,
			    sizeof(ifr6.ifr_name));
			memcpy(&ifr6.ifr_ifru.ifru_addr, tmp6, tmp6->sin6_len);
			if (ioctl(PFSENSE_G(inets6),
			    SIOCGIFAFLAG_IN6, &ifr6) == 0) {
				llflag = ifr6.ifr_ifru.ifru_flags6;
				if ((llflag & IN6_IFF_TENTATIVE) != 0)
					add_assoc_long(&addr, "tentative", 1);
			}

			tmp6 = (struct sockaddr_in6 *)mb->ifa_netmask;
			add_assoc_long(&addr, "subnetbits",
			    prefix(&tmp6->sin6_addr, sizeof(struct in6_addr)));

			if (mb->ifa_flags & IFF_POINTOPOINT) {
				tmp6 = (struct sockaddr_in6 *)mb->ifa_dstaddr;
				if (tmp6 != NULL &&
				    tmp6->sin6_family == AF_INET6) {
					bzero(outputbuf, sizeof outputbuf);
					inet_ntop(AF_INET6,
					    (void *)&tmp6->sin6_addr, outputbuf,
					    sizeof(outputbuf));
					add_assoc_string(&addr, "tunnel",
					    outputbuf);
				}
			}
			add_next_index_zval(&addrs6, &addr);
			break;
		default:
			continue;
		};
	}
	add_assoc_zval(return_value, "addrs", &addrs4);
	add_assoc_zval(return_value, "addrs6", &addrs6);

out:
	freeifaddrs(ifdata);
}

PHP_FUNCTION(pfSense_get_interface_addresses)
{
	struct ifaddrs *ifdata, *mb;
	struct sockaddr_in *tmp;
	struct sockaddr_in6 *tmp6;
	struct in6_ifreq ifr6;

	char outputbuf[128];
	char *ifname;
	size_t ifname_len;
	int llflag, addresscnt, addresscnt6;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STRING(ifname, ifname_len)
	ZEND_PARSE_PARAMETERS_END();

	if (getifaddrs(&ifdata) == -1)
		RETURN_NULL();

	/* addr list should be sorted by if_name with sortifaddrs (see
	 * ifconfig.c), some parameters are specific to the interface and need
	 * only be copied once (see ifconfig.c all interface listing, it only
	 * uses the first struct ifaddrs for a new interface name for listing
	 * interface params) */
	
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
		fill_interface_params(return_value, mb);
	}		
	freeifaddrs(ifdata);
}

PHP_FUNCTION(pfSense_bridge_add_member) {
	char *ifname, *ifchld;
	size_t ifname_len, ifchld_len;
	struct ifdrv drv;
	struct ifbreq req;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STRING(ifname, ifname_len)
		Z_PARAM_STRING(ifchld, ifchld_len)
	ZEND_PARSE_PARAMETERS_END();

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

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STRING(ifname, ifname_len)
		Z_PARAM_STRING(ifchld, ifchld_len)
	ZEND_PARSE_PARAMETERS_END();

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

	ZEND_PARSE_PARAMETERS_START(3, 3)
		Z_PARAM_STRING(ifname, ifname_len)
		Z_PARAM_STRING(ifchld, ifchld_len)
		Z_PARAM_LONG(flags)
	ZEND_PARSE_PARAMETERS_END();

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

PHP_FUNCTION(pfSense_interface_listget)
{
	struct ifaddrs *ifdata, *mb;
	char *ifname = NULL;
	int ifname_len = 0;
	zend_long flags = 0;

	ZEND_PARSE_PARAMETERS_START(0, 1)
		Z_PARAM_OPTIONAL
		Z_PARAM_LONG(flags)
	ZEND_PARSE_PARAMETERS_END();

	if (getifaddrs(&ifdata) == -1)
		RETURN_NULL();

	array_init(return_value);

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

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STRING(ifname, ifname_len)
	ZEND_PARSE_PARAMETERS_END();

	if (interface_create(ifname, SIOCIFCREATE, &str, return_value) == 0) {
		RETURN_STR(str);
	}
}

PHP_FUNCTION(pfSense_interface_create2) {
	char *ifname;
	size_t ifname_len;
	zend_string *str;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STRING(ifname, ifname_len)
	ZEND_PARSE_PARAMETERS_END();

	if (interface_create(ifname, SIOCIFCREATE2, &str, return_value) == 0) {
		RETURN_STR(str);
	}
}

PHP_FUNCTION(pfSense_interface_destroy) {
	char *ifname;
	size_t ifname_len;
	struct ifreq ifr;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STRING(ifname, ifname_len)
	ZEND_PARSE_PARAMETERS_END();

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

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STRING(ifname, ifname_len)
		Z_PARAM_STRING(ip, ip_len)
	ZEND_PARSE_PARAMETERS_END();

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

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STRING(ifname, ifname_len)
		Z_PARAM_STRING(ip, ip_len)
	ZEND_PARSE_PARAMETERS_END();

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

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STRING(ifname, ifname_len)
		Z_PARAM_STRING(newifname, newifname_len)
	ZEND_PARSE_PARAMETERS_END();

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

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STRING(ifname, ifname_len)
		Z_PARAM_STRING(newifname, newifname_len)
	ZEND_PARSE_PARAMETERS_END();

	if (PFSENSE_G(csock) == -1)
		RETURN_NULL();

	/* Send message */
	if (NgNameNode(PFSENSE_G(csock), ifname, "%s", newifname) < 0)
		RETURN_NULL();

	RETURN_TRUE;
}

PHP_FUNCTION(pfSense_interface_setpcp)
{
	char *ifname = NULL;
	size_t ifname_len;
	zend_long pcp;
	struct ifreq ifr;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STRING(ifname, ifname_len)
		Z_PARAM_LONG(pcp)
	ZEND_PARSE_PARAMETERS_END();

	memset(&ifr, 0, sizeof(ifr));
	strlcpy(ifr.ifr_name, ifname, sizeof(ifr.ifr_name));
	ifr.ifr_vlan_pcp = (u_short) pcp;

	if (ioctl(PFSENSE_G(s), SIOCSLANPCP, (caddr_t) &ifr) == -1)
		RETURN_FALSE;

	RETURN_TRUE;
}

PHP_FUNCTION(pfSense_vlan_create) {
	char *ifname = NULL;
	char *parentifname = NULL;
	size_t ifname_len, parent_len;
	zend_long tag, pcp;
	struct ifreq ifr;
	struct vlanreq params;

	ZEND_PARSE_PARAMETERS_START(4, 4)
		Z_PARAM_STRING(ifname, ifname_len)
		Z_PARAM_STRING(parentifname, parent_len)
		Z_PARAM_LONG(tag)
		Z_PARAM_LONG(pcp)
	ZEND_PARSE_PARAMETERS_END();

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

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STRING(ifname, ifname_len)
	ZEND_PARSE_PARAMETERS_END();

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

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STRING(ifname, ifname_len)
		Z_PARAM_LONG(mtu)
	ZEND_PARSE_PARAMETERS_END();

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

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STRING(ifname, ifname_len)
		Z_PARAM_LONG(value)
	ZEND_PARSE_PARAMETERS_END();

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

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STRING(ifname, ifname_len)
		Z_PARAM_LONG(value)
	ZEND_PARSE_PARAMETERS_END();

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

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STRING(ifname, ifname_len)
	ZEND_PARSE_PARAMETERS_END();

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

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STRING(ifname, ifname_len)
	ZEND_PARSE_PARAMETERS_END();

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

PHP_FUNCTION(pfSense_get_pf_rules)
{
	bool ethrules = 0;
	char *path = "";
	size_t path_len;
	int dev, i;
	struct pfctl_rules_info info;
	struct pfctl_eth_rules_info einfo;
	struct pfctl_rule rule;
	struct pfctl_eth_rule erule;
	uint32_t nr;
	zval zrule, zlabels;
	char anchor_call[MAXPATHLEN];

	ZEND_PARSE_PARAMETERS_START(0, 2)
		Z_PARAM_OPTIONAL
		Z_PARAM_BOOL(ethrules)
		Z_PARAM_STRING(path, path_len)
	ZEND_PARSE_PARAMETERS_END();

	if ((dev = open("/dev/pf", O_RDWR)) < 0)
		RETURN_FALSE;

	if (ethrules)
		goto eth_rules;

	if (pfctl_get_rules_info(dev, &info, PF_PASS, path) != 0) {
		RETVAL_FALSE;
		goto cleanup;
	}

	array_init(return_value);
	for (nr = 0; nr < info.nr; nr++) {
		if (pfctl_get_rule(dev, nr, info.ticket, path, PF_PASS,
		    &rule, anchor_call) != 0) {
			RETVAL_FALSE;
			goto cleanup;
		}

		array_init(&zrule);
		add_assoc_long(&zrule, "id", (zend_ulong) rule.nr);
		add_assoc_long(&zrule, "tracker", (zend_ulong) rule.ridentifier);
		add_assoc_long(&zrule, "ridentifier", (zend_ulong) rule.ridentifier);
		add_assoc_long(&zrule, "evaluations", (zend_ulong) rule.evaluations);
		add_assoc_long(&zrule, "packets", (zend_ulong) (rule.packets[0] + rule.packets[1]));
		add_assoc_long(&zrule, "bytes", (zend_ulong) (rule.bytes[0] + rule.bytes[1]));
		add_assoc_long(&zrule, "states", (zend_ulong) rule.states_cur);
		add_assoc_long(&zrule, "states_cur", (zend_ulong) rule.states_cur);
		add_assoc_long(&zrule, "pid", (zend_ulong) rule.cpid);
		add_assoc_long(&zrule, "cpid", (zend_ulong) rule.cpid);
		add_assoc_long(&zrule, "state creations", (zend_ulong) rule.states_tot);
		add_assoc_long(&zrule, "states_tot", (zend_ulong) rule.states_tot);

		array_init(&zlabels);
		i = 0;
		while (rule.label[i][0])
			add_next_index_string(&zlabels, rule.label[i++]);
		add_assoc_zval(&zrule, "labels", &zlabels);

		add_next_index_zval(return_value, &zrule);
	}

	goto cleanup;

eth_rules:
	if (pfctl_get_eth_rules_info(dev, &einfo, path) != 0) {
		RETVAL_FALSE;
		goto cleanup;
	}

	array_init(return_value);
	for (nr = 0; nr < einfo.nr; nr++) {
		if (pfctl_get_eth_rule(dev, nr, einfo.ticket, path,
		    &erule, false, anchor_call) != 0) {
			RETVAL_FALSE;
			goto cleanup;
		}

		array_init(&zrule);
		add_assoc_long(&zrule, "id", (zend_ulong) erule.nr);
		add_assoc_long(&zrule, "tracker", (zend_ulong) erule.ridentifier);
		add_assoc_long(&zrule, "ridentifier", (zend_ulong) erule.ridentifier);
		add_assoc_long(&zrule, "evaluations", (zend_ulong) erule.evaluations);
		add_assoc_long(&zrule, "packets", (zend_ulong) (erule.packets[0] + erule.packets[1]));
		add_assoc_long(&zrule, "bytes", (zend_ulong) (erule.bytes[0] + erule.bytes[1]));

		array_init(&zlabels);
		i = 0;
		while (erule.label[i][0])
			add_next_index_string(&zlabels, erule.label[i++]);
		add_assoc_zval(&zrule, "labels", &zlabels);

		add_next_index_zval(return_value, &zrule);
	}

cleanup:
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

	ZEND_PARSE_PARAMETERS_START(0, 1)
		Z_PARAM_OPTIONAL
		Z_PARAM_ZVAL(zvar)
	ZEND_PARSE_PARAMETERS_END();

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

	ZEND_PARSE_PARAMETERS_NONE();

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
		/* printf("Checksum: 0x%s\n\n", buf); */

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

PHP_FUNCTION(pfSense_sync)
{
	ZEND_PARSE_PARAMETERS_NONE();
	sync();
}

PHP_FUNCTION(pfSense_fsync)
{
	char *fname, *parent_dir;
	size_t fname_len;
	int fd;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STRING(fname, fname_len)
	ZEND_PARSE_PARAMETERS_END();

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

PHP_FUNCTION(pfSense_get_modem_devices)
{
	struct termios		attr, origattr;
	struct pollfd		pfd;
	glob_t			g;
	char			buf[2048] = { 0 };
	char			*path;
	int			nw = 0, i, fd, retries;
	zend_bool		show_info = 0;
	zend_long		poll_timeout = 700;

	ZEND_PARSE_PARAMETERS_START(0, 2)
		Z_PARAM_BOOL(show_info)
		Z_PARAM_LONG(poll_timeout)
	ZEND_PARSE_PARAMETERS_END();

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

PHP_FUNCTION(pfSense_get_os_hw_data)
{
	int mib[4], idata;
	u_long ldata;
	size_t len;
	char *data;

	ZEND_PARSE_PARAMETERS_NONE();

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

PHP_FUNCTION(pfSense_get_os_kern_data)
{
	int mib[4], idata;
	size_t len;
	char *data;
	struct timeval bootime;

	ZEND_PARSE_PARAMETERS_NONE();

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

static void
build_ipsec_sa_array(void *salist, char *label, vici_res_t *res)
{
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

PHP_FUNCTION(pfSense_ipsec_list_sa)
{

	vici_conn_t *conn;
	vici_req_t *req;
	vici_res_t *res;

	ZEND_PARSE_PARAMETERS_NONE();

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

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STRING(path, path_len)
		Z_PARAM_STRING(type, type_len)
	ZEND_PARSE_PARAMETERS_END();

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

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STRING(path, path_len)
	ZEND_PARSE_PARAMETERS_END();

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
	zval counter;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STRING(path, path_len)
	ZEND_PARSE_PARAMETERS_END();

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
			array_init(&counter);
			add_assoc_long(&counter, "direction", (zend_ulong)rule.direction);
			add_assoc_long(&counter, "evaluations", (zend_ulong)rule.evaluations);
			switch (rule.direction) {
				case PF_IN:
					add_assoc_long(&counter, "input_pkts", (zend_ulong)rule.packets[0]);
					add_assoc_long(&counter, "input_bytes", (zend_ulong)rule.bytes[0]);
					break;
				case PF_OUT:
					add_assoc_long(&counter, "output_pkts", (zend_ulong)rule.packets[1]);
					add_assoc_long(&counter, "output_bytes", (zend_ulong)rule.bytes[1]);
					break;
				default:
					add_assoc_long(&counter, "input_pkts", (zend_ulong)rule.packets[0]);
					add_assoc_long(&counter, "input_bytes", (zend_ulong)rule.bytes[0]);
					add_assoc_long(&counter, "output_pkts", (zend_ulong)rule.packets[1]);
					add_assoc_long(&counter, "output_bytes", (zend_ulong)rule.bytes[1]);
			}
			add_next_index_zval(return_value, &counter);
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
	uint32_t if_rulesets[] = { PF_SCRUB, PF_PASS };

	int dev = 0;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STRING(path, path_len)
	ZEND_PARSE_PARAMETERS_END();

	if ((dev = open("/dev/pf", O_RDWR)) < 0)
		RETURN_NULL();

	if (path_len > MAXPATHLEN)
		goto error_out;

	/* Zero eth rule counters */
	if (pfctl_get_eth_rules_info(dev, &einfo, path))
		goto error_out;
	for (int nr = 0; nr < einfo.nr; nr++) {
		if (pfctl_get_eth_rule(dev, nr, einfo.ticket, path, &erule, true, anchor_call) != 0)
			goto error_out;
	}

	/* Zero all other rules */
	for (int nrs = 0; nrs < nitems(if_rulesets); nrs++) {
		if (pfctl_get_rules_info(dev, &info, if_rulesets[nrs], path))
			goto error_out;
		for (int nr = 0; nr < info.nr; nr++) {
			if (pfctl_get_clear_rule(dev, nr, info.ticket, path, if_rulesets[nrs], &rule, anchor_call,
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

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STRING(path, path_len)
	ZEND_PARSE_PARAMETERS_END();

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
		add_next_index_long(return_value, (zend_long)rule.last_active_timestamp);
	}

error_out:
	close(dev);
}
#endif

PHP_FUNCTION(pfSense_kenv_dump) {
	char *buf, *bp, *cp;
	int size;

	ZEND_PARSE_PARAMETERS_NONE();

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

PHP_MINIT_FUNCTION(pfsense)
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
	REGISTER_LONG_CONSTANT("IFF_PPROMISC", IFF_PPROMISC, CONST_PERSISTENT | CONST_CS);
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

	REGISTER_LONG_CONSTANT("IFNET_PCP_NONE", IFNET_PCP_NONE, CONST_PERSISTENT | CONST_CS);

	return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(pfsense)
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

/* {{{ PHP_RINIT_FUNCTION */
PHP_RINIT_FUNCTION(pfsense)
{
#if defined(ZTS) && defined(COMPILE_DL_PFSENSE)
	ZEND_TSRMLS_CACHE_UPDATE();
#endif

	return SUCCESS;
}
/* }}} */

/* {{{ PHP_MINFO_FUNCTION */
PHP_MINFO_FUNCTION(pfsense)
{
	php_info_print_table_start();
	php_info_print_table_header(2, "pfsense support", "enabled");
	php_info_print_table_end();
}
/* }}} */

/* {{{ pfsense_module_entry */
zend_module_entry pfsense_module_entry = {
	STANDARD_MODULE_HEADER,
	"pfSense",						/* Extension name */
	ext_functions,					/* zend_function_entry */
	PHP_MINIT(pfsense),				/* PHP_MINIT - Module initialization */
	PHP_MSHUTDOWN(pfsense),			/* PHP_MSHUTDOWN - Module shutdown */
	PHP_RINIT(pfsense),				/* PHP_RINIT - Request initialization */
	NULL,							/* PHP_RSHUTDOWN - Request shutdown */
	PHP_MINFO(pfsense),				/* PHP_MINFO - Module info */
	PHP_PFSENSE_VERSION,			/* Version */
    PHP_MODULE_GLOBALS(pfSense),  	/* Module globals */
    NULL,         		 			/* PHP_GINIT – Globals initialization */
    NULL,                      		/* PHP_GSHUTDOWN – Globals shutdown */
    NULL,
    STANDARD_MODULE_PROPERTIES_EX
};
/* }}} */

#ifdef COMPILE_DL_PFSENSE
# ifdef ZTS
ZEND_TSRMLS_CACHE_DEFINE()
# endif
ZEND_GET_MODULE(pfsense)
#endif
