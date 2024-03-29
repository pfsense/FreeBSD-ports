/*
 * pfsense_private.h
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2022-2024 Rubicon Communications, LLC (Netgate)
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

#ifndef _PFSENSE_PRIVATE_H
#define _PFSENSE_PRIVATE_H

#include "php_pfSense.h"

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

#ifdef ETHERSWITCH_FUNCTIONS
#include <net/if_media.h>
#include "etherswitch.h"
#endif

#include <libvici.h>

ZEND_BEGIN_MODULE_GLOBALS(pfSense)
	int s;
	int inets;
	int inets6;
	int csock;
ZEND_END_MODULE_GLOBALS(pfSense)

ZEND_EXTERN_MODULE_GLOBALS(pfSense)

#define PFSENSE_G(v) ZEND_MODULE_GLOBALS_ACCESSOR(pfSense, v)

#endif
